<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * PDO रैपर। SQL में {p} लिखें, वह टेबल प्रीफ़िक्स से बदल जाता है।
 * हर क्वेरी prepared statement से चलती है।
 */
final class Database
{
    private PDO $pdo;
    /** इस अनुरोध में कितनी query और कुल समय (धीमे अनुरोध के लॉग के लिए) */
    public static int $queries = 0;
    public static float $queryMs = 0.0;

    public function __construct(private array $cfg)
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int) ($cfg['port'] ?? 3306), $cfg['name']);
        $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prefix(): string
    {
        return (string) ($this->cfg['prefix'] ?? '');
    }

    /** पूरा टेबल नाम: table('users') → sb_users */
    public function table(string $name): string
    {
        return $this->prefix() . $name;
    }

    public function sql(string $sql): string
    {
        return str_replace('{p}', $this->prefix(), $sql);
    }

    public function setTimezone(string $offset): void
    {
        $this->pdo->exec('SET time_zone = ' . $this->pdo->quote($offset));
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($this->sql($sql));
        $i = 0;
        foreach ($params as $k => $v) {
            $key = is_int($k) ? ++$i : (str_starts_with((string) $k, ':') ? $k : ':' . $k);
            if (is_bool($v)) {
                $v = (int) $v;
            }
            $type = match (true) {
                is_int($v) => PDO::PARAM_INT,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($key, $v, $type);
        }
        $t = microtime(true);
        $st->execute();
        self::$queries++;
        self::$queryMs += (microtime(true) - $t) * 1000;
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $r = $this->query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $this->table($table) . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(static fn($c) => "`$c` = ?", array_keys($data)));
        return $this->query('UPDATE `' . $this->table($table) . '` SET ' . $set . ' WHERE ' . $where, [...array_values($data), ...$params])->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query('DELETE FROM `' . $this->table($table) . '` WHERE ' . $where, $params)->rowCount();
    }

    /** IN (?,?,?) के प्लेसहोल्डर */
    public static function in(array $items): string
    {
        return implode(',', array_fill(0, max(1, count($items)), '?'));
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
