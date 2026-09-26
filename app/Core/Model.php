<?php
declare(strict_types=1);

namespace App\Core;

/**
 * साधारण टेबल मॉडल: एक टेबल का find/create/update/delete।
 * जटिल क्वेरी Repositories में लिखी जाती हैं। पंक्तियाँ array के रूप में लौटती हैं।
 */
abstract class Model
{
    protected static string $table;
    protected static array $fillable = [];
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    protected static function db(): Database
    {
        return app('db');
    }

    public static function table(): string
    {
        return static::$table;
    }

    public static function find(int $id, bool $withTrashed = false): ?array
    {
        $sql = 'SELECT * FROM {p}' . static::$table . ' WHERE id = ?';
        if (static::$softDeletes && !$withTrashed) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return static::db()->first($sql . ' LIMIT 1', [$id]);
    }

    public static function findOrFail(int $id): array
    {
        return static::find($id) ?? throw new HttpException(404);
    }

    public static function firstWhere(string $column, mixed $value): ?array
    {
        $sql = 'SELECT * FROM {p}' . static::$table . " WHERE `$column` = ?";
        if (static::$softDeletes) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return static::db()->first($sql . ' LIMIT 1', [$value]);
    }

    /** where(['status' => 'active'], 'name ASC') */
    public static function where(array $conditions = [], string $order = 'id DESC', int $limit = 0): array
    {
        [$where, $params] = static::buildWhere($conditions);
        $sql = 'SELECT * FROM {p}' . static::$table . $where . ' ORDER BY ' . $order . ($limit ? ' LIMIT ' . $limit : '');
        return static::db()->all($sql, $params);
    }

    public static function count(array $conditions = []): int
    {
        [$where, $params] = static::buildWhere($conditions);
        return (int) static::db()->value('SELECT COUNT(*) FROM {p}' . static::$table . $where, $params);
    }

    protected static function buildWhere(array $conditions): array
    {
        $parts = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            if ($val === null) {
                $parts[] = "`$col` IS NULL";
            } else {
                $parts[] = "`$col` = ?";
                $params[] = $val;
            }
        }
        if (static::$softDeletes) {
            $parts[] = 'deleted_at IS NULL';
        }
        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $params];
    }

    protected static function fill(array $data): array
    {
        return static::$fillable ? array_intersect_key($data, array_flip(static::$fillable)) : $data;
    }

    public static function create(array $data): int
    {
        $data = static::fill($data);
        if (static::$timestamps) {
            $data['created_at'] ??= date('Y-m-d H:i:s');
            $data['updated_at'] ??= date('Y-m-d H:i:s');
        }
        return static::db()->insert(static::$table, $data);
    }

    public static function update(int $id, array $data): int
    {
        $data = static::fill($data);
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return $data ? static::db()->update(static::$table, $data, 'id = ?', [$id]) : 0;
    }

    /** soft delete वाले मॉडल में सिर्फ़ deleted_at भरता है */
    public static function delete(int $id): int
    {
        if (static::$softDeletes) {
            return static::db()->update(static::$table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        }
        return static::db()->delete(static::$table, 'id = ?', [$id]);
    }

    public static function forceDelete(int $id): int
    {
        return static::db()->delete(static::$table, 'id = ?', [$id]);
    }

    public static function restore(int $id): int
    {
        return static::db()->update(static::$table, ['deleted_at' => null], 'id = ?', [$id]);
    }
}
