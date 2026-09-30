<?php
/**
 * Phase 13: एनालिटिक्स (कच्चे हिट + रोज़ का सारांश), शेयर गिनती, ट्रेंडिंग ओवरराइड
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $o = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            // हर पेज-व्यू (कुछ दिन रखे जाते हैं); visitor = रोज़ बदलने वाला हैश, कुकी नहीं
            "CREATE TABLE IF NOT EXISTS `{p}analytics_hits` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `created_at` DATETIME NOT NULL,
                `day` DATE NOT NULL,
                `visitor` CHAR(16) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `page_type` VARCHAR(20) NOT NULL DEFAULT 'page',
                `content_id` INT UNSIGNED NULL,
                `category_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `reporter_id` INT UNSIGNED NULL,
                `device` VARCHAR(10) NOT NULL DEFAULT 'desktop',
                `source` VARCHAR(10) NOT NULL DEFAULT 'direct',
                `referrer` VARCHAR(120) NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ah_time` (`created_at`),
                KEY `idx_ah_day` (`day`, `visitor`),
                KEY `idx_ah_content` (`page_type`, `content_id`, `created_at`)
            ) $o",
            // रोज़ का सारांश: dim = all/type/news/category/location/reporter/device/source/referrer/hour
            "CREATE TABLE IF NOT EXISTS `{p}analytics_daily` (
                `day` DATE NOT NULL,
                `dim` VARCHAR(12) NOT NULL,
                `dim_key` VARCHAR(120) NOT NULL DEFAULT '',
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`day`, `dim`, `dim_key`),
                KEY `idx_ad_dim` (`dim`, `dim_key`, `day`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}analytics_shares` (
                `day` DATE NOT NULL,
                `news_id` INT UNSIGNED NOT NULL,
                `network` VARCHAR(12) NOT NULL,
                `shares` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`day`, `news_id`, `network`),
                KEY `idx_as_news` (`news_id`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}trending_overrides` (
                `news_id` INT UNSIGNED NOT NULL,
                `action` ENUM('pin','hide') NOT NULL,
                `until` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`news_id`),
                CONSTRAINT `fk_{p}tro_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE
            ) $o",
        ] as $sql) {
            $db->query($sql);
        }

        if (!$db->first("SHOW COLUMNS FROM {p}news LIKE 'shares'")) {
            $db->query('ALTER TABLE {p}news ADD `shares` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `views`');
        }
    }
};
