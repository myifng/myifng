<?php
/**
 * Phase 4: न्यूज़ CMS, वर्कफ़्लो, वर्ज़न हिस्ट्री, असाइनमेंट डेस्क
 * टेबल: news, news_topics, news_tags, news_related, news_gallery, news_revisions, news_remarks, assignments
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}assignments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(190) NOT NULL,
                `description` TEXT NULL,
                `reporter_id` INT UNSIGNED NULL,
                `category_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
                `deadline` DATETIME NULL,
                `instructions` TEXT NULL,
                `attachments` TEXT NULL,
                `status` ENUM('open','accepted','in_progress','submitted','completed','cancelled') NOT NULL DEFAULT 'open',
                `news_id` INT UNSIGNED NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_asg_reporter` (`reporter_id`, `status`, `deadline`),
                CONSTRAINT `fk_{p}asg_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}asg_category` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}asg_location` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}asg_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `subtitle` VARCHAR(255) NULL,
                `slug` VARCHAR(200) NOT NULL,
                `summary` VARCHAR(600) NULL,
                `content` MEDIUMTEXT NULL,
                `featured_image` VARCHAR(255) NULL,
                `image_caption` VARCHAR(300) NULL,
                `image_credit` VARCHAR(150) NULL,
                `video_url` VARCHAR(500) NULL,
                `audio_file` VARCHAR(255) NULL,
                `category_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `reporter_id` INT UNSIGNED NULL,
                `editor_id` INT UNSIGNED NULL,
                `assignment_id` INT UNSIGNED NULL,
                `source` VARCHAR(150) NULL,
                `news_credit` VARCHAR(150) NULL,
                `status` ENUM('draft','submitted','review','fact_check','approved','scheduled','published','rejected','disabled','archived') NOT NULL DEFAULT 'draft',
                `is_breaking` TINYINT(1) NOT NULL DEFAULT 0,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `is_trending` TINYINT(1) NOT NULL DEFAULT 0,
                `is_editor_pick` TINYINT(1) NOT NULL DEFAULT 0,
                `is_exclusive` TINYINT(1) NOT NULL DEFAULT 0,
                `is_live` TINYINT(1) NOT NULL DEFAULT 0,
                `is_sponsored` TINYINT(1) NOT NULL DEFAULT 0,
                `published_at` DATETIME NULL,
                `scheduled_at` DATETIME NULL,
                `correction_note` VARCHAR(500) NULL,
                `corrected_at` DATETIME NULL,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `meta_keywords` VARCHAR(255) NULL,
                `canonical_url` VARCHAR(500) NULL,
                `robots` VARCHAR(40) NOT NULL DEFAULT 'index,follow',
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `word_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `language_id` INT UNSIGNED NULL,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `deleted_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_news_slug` (`slug`),
                KEY `idx_news_public` (`status`, `deleted_at`, `published_at`),
                KEY `idx_news_category` (`category_id`, `status`, `published_at`),
                KEY `idx_news_location` (`location_id`, `status`, `published_at`),
                KEY `idx_news_reporter` (`reporter_id`, `status`),
                KEY `idx_news_scheduled` (`status`, `scheduled_at`),
                CONSTRAINT `fk_{p}news_category` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}news_location` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}news_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}news_editor` FOREIGN KEY (`editor_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}news_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `{p}assignments` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}news_lang` FOREIGN KEY (`language_id`) REFERENCES `{p}languages` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_topics` (
                `news_id` INT UNSIGNED NOT NULL,
                `topic_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`news_id`, `topic_id`),
                KEY `idx_nt_topic` (`topic_id`),
                CONSTRAINT `fk_{p}nt_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}nt_topic` FOREIGN KEY (`topic_id`) REFERENCES `{p}topics` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_tags` (
                `news_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`news_id`, `tag_id`),
                KEY `idx_ntg_tag` (`tag_id`),
                CONSTRAINT `fk_{p}ntg_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}ntg_tag` FOREIGN KEY (`tag_id`) REFERENCES `{p}tags` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_related` (
                `news_id` INT UNSIGNED NOT NULL,
                `related_id` INT UNSIGNED NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`news_id`, `related_id`),
                CONSTRAINT `fk_{p}nr_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}nr_related` FOREIGN KEY (`related_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_gallery` (
                `news_id` INT UNSIGNED NOT NULL,
                `media_id` INT UNSIGNED NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`news_id`, `media_id`),
                CONSTRAINT `fk_{p}ng_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}ng_media` FOREIGN KEY (`media_id`) REFERENCES `{p}media` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_revisions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `news_id` INT UNSIGNED NOT NULL,
                `version` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `summary` VARCHAR(600) NULL,
                `content` MEDIUMTEXT NULL,
                `data` MEDIUMTEXT NULL,
                `status` VARCHAR(20) NOT NULL,
                `reason` VARCHAR(500) NULL,
                `is_correction` TINYINT(1) NOT NULL DEFAULT 0,
                `changed_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_rev_version` (`news_id`, `version`),
                CONSTRAINT `fk_{p}rev_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rev_user` FOREIGN KEY (`changed_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}news_remarks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `news_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `type` ENUM('note','feedback','status') NOT NULL DEFAULT 'note',
                `message` TEXT NULL,
                `from_status` VARCHAR(20) NULL,
                `to_status` VARCHAR(20) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_rem_news` (`news_id`, `id`),
                CONSTRAINT `fk_{p}rem_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rem_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }
        // assignments.news_id → news (दोनों टेबल बनने के बाद)
        if (!$db->first("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?", [$db->table('assignments'), 'fk_' . $db->prefix() . 'asg_news'])) {
            $db->pdo()->exec($db->sql('ALTER TABLE `{p}assignments` ADD CONSTRAINT `fk_{p}asg_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL'));
        }
    }
};
