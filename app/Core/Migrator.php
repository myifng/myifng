<?php
declare(strict_types=1);

namespace App\Core;

/**
 * database/migrations की फ़ाइलें क्रम से चलाता है। जो चल चुकी हैं वे migrations टेबल में दर्ज रहती हैं।
 * इंस्टॉलर और भविष्य के अपडेट दोनों इसे इस्तेमाल करते हैं।
 */
final class Migrator
{
    public function __construct(private Database $db, private string $dir)
    {
    }

    private function ensureTable(): void
    {
        $this->db->pdo()->exec($this->db->sql('CREATE TABLE IF NOT EXISTS `{p}migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(190) NOT NULL,
            `batch` INT UNSIGNED NOT NULL,
            `ran_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), UNIQUE KEY `migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'));
    }

    public function files(): array
    {
        $files = glob($this->dir . '/*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    public function pending(): array
    {
        $this->ensureTable();
        $ran = array_column($this->db->all('SELECT migration FROM {p}migrations'), 'migration');
        return array_values(array_filter($this->files(), static fn($f) => !in_array(basename($f, '.php'), $ran, true)));
    }

    /** बाकी migrations चलाएँ। लौटाता है: चलाई गई फ़ाइलों के नाम */
    public function migrate(): array
    {
        $pending = $this->pending();
        if (!$pending) {
            return [];
        }
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM {p}migrations') + 1;
        $done = [];
        foreach ($pending as $file) {
            $migration = require $file;
            $migration->up($this->db);
            $this->db->insert('migrations', ['migration' => basename($file, '.php'), 'batch' => $batch]);
            $done[] = basename($file, '.php');
        }
        return $done;
    }
}
