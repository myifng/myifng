<?php
/**
 * Phase 10: रीडायरेक्ट, 404 लॉग, ख़बर में SEO के नए खाने (फ़ोकस कीवर्ड, सोशल शेयर, FAQ), टैग का SEO
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}redirects` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `source` VARCHAR(500) NOT NULL,
                `source_hash` CHAR(40) NOT NULL,
                `match_type` ENUM('exact','prefix') NOT NULL DEFAULT 'exact',
                `target` VARCHAR(1000) NULL,
                `code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_hit_at` DATETIME NULL,
                `is_auto` TINYINT(1) NOT NULL DEFAULT 0,
                `entity` VARCHAR(40) NULL,
                `note` VARCHAR(255) NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_redirect_source` (`source_hash`),
                KEY `idx_redirect_status` (`status`, `is_auto`),
                CONSTRAINT `fk_{p}redirect_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}not_found_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `path` VARCHAR(500) NOT NULL,
                `path_hash` CHAR(40) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `bot_hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `referrer` VARCHAR(500) NULL,
                `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
                `user_agent` VARCHAR(255) NULL,
                `first_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `status` ENUM('new','ignored','fixed') NOT NULL DEFAULT 'new',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_nf_path` (`path_hash`),
                KEY `idx_nf_seen` (`status`, `last_seen`)
            ) $opts",
        ] as $sql) {
            $db->query($sql);
        }

        $cols = static fn(string $t): array => array_column($db->all('SHOW COLUMNS FROM `' . $db->table($t) . '`'), 'Field');
        $news = $cols('news');
        foreach ([
            'focus_keyword' => 'VARCHAR(100) NULL AFTER `meta_keywords`',
            'og_title' => 'VARCHAR(190) NULL AFTER `focus_keyword`',
            'og_description' => 'VARCHAR(320) NULL AFTER `og_title`',
            'og_image' => 'VARCHAR(255) NULL AFTER `og_description`',
            'faq' => 'TEXT NULL AFTER `og_image`',
        ] as $c => $def) {
            if (!in_array($c, $news, true)) {
                $db->query('ALTER TABLE `' . $db->table('news') . "` ADD COLUMN `$c` $def");
            }
        }
        $tags = $cols('tags');
        foreach (['meta_title' => 'VARCHAR(190) NULL', 'meta_description' => 'VARCHAR(320) NULL'] as $c => $def) {
            if (!in_array($c, $tags, true)) {
                $db->query('ALTER TABLE `' . $db->table('tags') . "` ADD COLUMN `$c` $def");
            }
        }
    }
};
