<?php
declare(strict_types=1);

namespace App\Services;

/**
 * बैकअप (§69): डेटाबेस (.sql.gz, शुद्ध PHP), फ़ाइलें (uploads की ZIP), या दोनों।
 * फ़ाइलें storage/backups में (वेब से बंद)। रीस्टोर: phpMyAdmin से .sql.gz इम्पोर्ट (README)।
 */
final class BackupService
{
    public const TYPES = ['db' => ['डेटाबेस', 'fa-database'], 'files' => ['फ़ाइलें (अपलोड)', 'fa-images'], 'full' => ['पूरा (डेटाबेस + फ़ाइलें)', 'fa-box-archive']];
    private const CHUNK = 1000;

    public static function dir(): string
    {
        $d = BASE_PATH . '/storage/backups';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        if (!is_file($d . '/.htaccess')) {
            @file_put_contents($d . '/.htaccess', "Require all denied\n");
        }
        if (!is_file($d . '/index.html')) {
            @file_put_contents($d . '/index.html', '');
        }
        return $d;
    }

    /** बैकअप बनाएँ; ['ok' => bool, 'id' => int, 'message' => string] */
    public static function create(string $type, ?int $userId, string $trigger = 'manual', string $note = ''): array
    {
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'id' => 0, 'message' => 'प्रकार सही नहीं।'];
        }
        if ($type !== 'db' && !class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'id' => 0, 'message' => 'सर्वर पर PHP का zip extension नहीं है; फ़ाइल बैकअप नहीं बन सकता (डेटाबेस बैकअप बन सकता है)।'];
        }
        // एक समय में एक बैकअप (15 मिनट से पुराना "running" अटका माना जाए)
        if (db()->value("SELECT id FROM {p}backups WHERE status = 'running' AND created_at > NOW() - INTERVAL 15 MINUTE")) {
            return ['ok' => false, 'id' => 0, 'message' => 'एक बैकअप पहले से चल रहा है। थोड़ी देर बाद कोशिश करें।'];
        }
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');
        $id = db()->insert('backups', ['type' => $type, 'status' => 'running', 'trigger' => $trigger, 'note' => mb_substr(trim(strip_tags($note)), 0, 255) ?: null,
            'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
        $base = 'backup-' . $type . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $dir = self::dir();
        $tmp = [];
        try {
            if ($type === 'db') {
                $file = $base . '.sql.gz';
                self::dumpDatabase($dir . '/' . $file);
            } else {
                $file = $base . '.zip';
                $zip = new \ZipArchive();
                if ($zip->open($dir . '/' . $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('ZIP फ़ाइल नहीं बन सकी (storage/backups लिखने लायक है?)');
                }
                if ($type === 'full') {
                    $sql = $dir . '/' . $base . '.tmp.sql.gz';
                    $tmp[] = $sql;
                    self::dumpDatabase($sql);
                    $zip->addFile($sql, 'database.sql.gz');
                }
                $n = self::addDir($zip, BASE_PATH . '/public/uploads', 'uploads');
                if (setting('backup_include_private', '1') === '1') {
                    $n += self::addDir($zip, BASE_PATH . '/storage/private', 'private');
                }
                $zip->addFromString('README.txt', "बैकअप: " . setting('site_name') . "\nसमय: " . date('Y-m-d H:i:s') . "\nप्रकार: " . self::TYPES[$type][0]
                    . "\nवर्ज़न: " . config('app.version') . "\nफ़ाइलें: $n\n\nरीस्टोर: uploads/ को public/uploads/ में, private/ को storage/private/ में रखें;"
                    . " database.sql.gz को phpMyAdmin → Import से चलाएँ।\n");
                if (!$zip->close()) {
                    throw new \RuntimeException('ZIP बंद करते समय दिक्कत (डिस्क भरी है?)');
                }
            }
            $path = $dir . '/' . $file;
            db()->update('backups', ['file' => $file, 'size' => (int) filesize($path), 'checksum' => hash_file('sha256', $path), 'status' => 'done', 'finished_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            self::prune();
            return ['ok' => true, 'id' => $id, 'message' => self::TYPES[$type][0] . ' का बैकअप बन गया (' . self::size((int) filesize($path)) . ')।'];
        } catch (\Throwable $e) {
            if (isset($file) && is_file($dir . '/' . $file)) {
                @unlink($dir . '/' . $file);
            }
            db()->update('backups', ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'finished_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            logger()->error('Backup: ' . $e->getMessage());
            return ['ok' => false, 'id' => $id, 'message' => 'बैकअप नहीं बना: ' . $e->getMessage()];
        } finally {
            foreach ($tmp as $t) {
                @unlink($t);
            }
        }
    }

    /** इस साइट की (prefix वाली) टेबल का SQL dump, gzip में, टुकड़ों में */
    public static function dumpDatabase(string $path): void
    {
        $pdo = db()->pdo();
        $prefix = db()->prefix();
        $gz = gzopen($path, 'wb6');
        if (!$gz) {
            throw new \RuntimeException('बैकअप फ़ाइल नहीं खुल सकी');
        }
        $w = static function (string $s) use ($gz): void {
            if (gzwrite($gz, $s) === false) {
                throw new \RuntimeException('लिखते समय दिक्कत (डिस्क भरी है?)');
            }
        };
        $w("-- " . setting('site_name') . " डेटाबेस बैकअप\n-- समय: " . date('Y-m-d H:i:s') . "\n-- वर्ज़न: " . config('app.version') . "\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            if ($prefix !== '' && !str_starts_with($table, $prefix)) {
                continue; // एक ही DB में दूसरी साइट/ऐप की टेबल नहीं
            }
            $q = '`' . str_replace('`', '``', $table) . '`';
            $create = $pdo->query("SHOW CREATE TABLE $q")->fetch(\PDO::FETCH_NUM)[1];
            $w("--\n-- टेबल $table\n--\nDROP TABLE IF EXISTS $q;\n$create;\n\n");
            $cols = null;
            for ($off = 0; ; $off += self::CHUNK) {
                $rows = $pdo->query("SELECT * FROM $q LIMIT " . self::CHUNK . " OFFSET $off")->fetchAll(\PDO::FETCH_ASSOC);
                if (!$rows) {
                    break;
                }
                $cols ??= '(`' . implode('`, `', array_map(static fn($c) => str_replace('`', '``', $c), array_keys($rows[0]))) . '`)';
                $vals = [];
                foreach ($rows as $r) {
                    $vals[] = '(' . implode(', ', array_map(static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
                }
                $w("INSERT INTO $q $cols VALUES\n" . implode(",\n", $vals) . ";\n");
                if (count($rows) < self::CHUNK) {
                    break;
                }
            }
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);
    }

    private static function addDir(\ZipArchive $zip, string $dir, string $as): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !$f->isLink()) {
                $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', $dir)) + 1);
                $zip->addFile($f->getPathname(), $as . '/' . $rel);
                $n++;
            }
        }
        return $n;
    }

    /** "रखने की संख्या" से पुराने बैकअप हटाएँ (सफल बैकअप में से) */
    public static function prune(): int
    {
        $keep = max(1, min(100, (int) setting('backup_keep', '7')));
        $old = db()->all("SELECT id, file FROM {p}backups WHERE status = 'done' ORDER BY created_at DESC, id DESC LIMIT 1000 OFFSET $keep");
        foreach ($old as $b) {
            self::remove((int) $b['id']);
        }
        // असफल/अटके पुराने रिकॉर्ड (30 दिन)
        db()->query("DELETE FROM {p}backups WHERE status IN ('failed','running') AND created_at < NOW() - INTERVAL 30 DAY");
        return count($old);
    }

    public static function remove(int $id): bool
    {
        $b = db()->first('SELECT * FROM {p}backups WHERE id = ?', [$id]);
        if (!$b) {
            return false;
        }
        if ($b['file'] && ($p = self::path($b['file'])) && is_file($p)) {
            @unlink($p);
        }
        db()->query('DELETE FROM {p}backups WHERE id = ?', [$id]);
        return true;
    }

    /** सुरक्षित रास्ता (नाम का पैटर्न पक्का) */
    public static function path(string $file): ?string
    {
        return preg_match('/^backup-(db|files|full)-\d{8}-\d{6}-[a-f0-9]{8}\.(sql\.gz|zip)$/', $file) ? self::dir() . '/' . $file : null;
    }

    /** शेड्यूल: जवाब भेजने के बाद, घंटे में एक बार जाँच; समय हो तो डेटाबेस बैकअप */
    public static function scheduled(): void
    {
        $s = setting('backup_schedule', 'off');
        if (!in_array($s, ['daily', 'weekly'], true)) {
            return;
        }
        $last = db()->value("SELECT MAX(created_at) FROM {p}backups WHERE `trigger` = 'schedule' AND status = 'done'");
        $gap = $s === 'daily' ? 86400 - 1800 : 7 * 86400 - 1800;
        if (!$last || strtotime((string) $last) < time() - $gap) {
            $r = self::create(setting('backup_schedule_type', 'db') === 'full' ? 'full' : 'db', null, 'schedule', 'अपने-आप (' . ($s === 'daily' ? 'रोज़' : 'हफ़्ते') . ')');
            AuditService::log('backup', 'backups', $r['id'], 'शेड्यूल बैकअप: ' . $r['message']);
        }
    }

    public static function size(int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 2) . ' GB' : ($bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB');
    }
}
