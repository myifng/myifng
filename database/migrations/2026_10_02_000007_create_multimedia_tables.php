<?php
/**
 * Phase 7: ब्रेकिंग कंट्रोल रूम, लाइव ब्लॉग, लाइव टीवी, वीडियो, फ़ोटो गैलरी, वेब स्टोरी, ऑडियो/पॉडकास्ट
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        // वीडियो/गैलरी/स्टोरी/ऑडियो के साझा कॉलम
        $common = "`category_id` INT UNSIGNED NULL,
                `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `published_at` DATETIME NULL,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
        $fk = fn(string $t) => "CONSTRAINT `fk_{p}{$t}_cat` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}{$t}_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL";

        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}breaking_news` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `type` ENUM('breaking','flash','alert') NOT NULL DEFAULT 'breaking',
                `news_id` INT UNSIGNED NULL,
                `url` VARCHAR(500) NULL,
                `priority` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `show_ticker` TINYINT(1) NOT NULL DEFAULT 1,
                `show_banner` TINYINT(1) NOT NULL DEFAULT 0,
                `mobile_alert` TINYINT(1) NOT NULL DEFAULT 0,
                `push` TINYINT(1) NOT NULL DEFAULT 0,
                `push_status` ENUM('none','queued','sent','failed') NOT NULL DEFAULT 'none',
                `starts_at` DATETIME NOT NULL,
                `ends_at` DATETIME NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_brk_live` (`status`, `starts_at`, `ends_at`),
                CONSTRAINT `fk_{p}brk_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}brk_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}live_blogs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `news_id` INT UNSIGNED NOT NULL,
                `status` ENUM('live','paused','ended') NOT NULL DEFAULT 'live',
                `started_at` DATETIME NOT NULL,
                `ended_at` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_live_news` (`news_id`),
                CONSTRAINT `fk_{p}lb_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}lb_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}live_updates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `live_blog_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NULL,
                `body` TEXT NULL,
                `image` VARCHAR(255) NULL,
                `embed_url` VARCHAR(500) NULL,
                `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
                `is_key` TINYINT(1) NOT NULL DEFAULT 0,
                `author_id` INT UNSIGNED NULL,
                `posted_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lu_blog` (`live_blog_id`, `posted_at`),
                CONSTRAINT `fk_{p}lu_blog` FOREIGN KEY (`live_blog_id`) REFERENCES `{p}live_blogs` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}lu_author` FOREIGN KEY (`author_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}live_tv_channels` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(170) NOT NULL,
                `source_type` ENUM('youtube','embed','stream') NOT NULL DEFAULT 'youtube',
                `source_url` VARCHAR(500) NOT NULL,
                `logo` VARCHAR(255) NULL,
                `description` VARCHAR(500) NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_live` TINYINT(1) NOT NULL DEFAULT 1,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ltv_slug` (`slug`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}live_tv_programs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `channel_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(190) NOT NULL,
                `host` VARCHAR(150) NULL,
                `description` VARCHAR(500) NULL,
                `days` VARCHAR(20) NOT NULL DEFAULT '0,1,2,3,4,5,6',
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ltp_channel` (`channel_id`, `start_time`),
                CONSTRAINT `fk_{p}ltp_channel` FOREIGN KEY (`channel_id`) REFERENCES `{p}live_tv_channels` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}video_playlists` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(170) NOT NULL,
                `type` ENUM('playlist','show') NOT NULL DEFAULT 'playlist',
                `description` VARCHAR(1000) NULL,
                `cover` VARCHAR(255) NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_vpl_slug` (`slug`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}videos` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `type` ENUM('video','short','interview','ground_report','show') NOT NULL DEFAULT 'video',
                `source` ENUM('youtube','upload','embed') NOT NULL DEFAULT 'youtube',
                `source_url` VARCHAR(500) NULL,
                `file` VARCHAR(255) NULL,
                `duration` INT UNSIGNED NULL,
                `cover` VARCHAR(255) NULL,
                `playlist_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `credit` VARCHAR(150) NULL,
                $common,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_video_slug` (`slug`),
                KEY `idx_video_public` (`status`, `published_at`),
                KEY `idx_video_type` (`type`, `status`, `published_at`),
                KEY `idx_video_playlist` (`playlist_id`, `published_at`),
                CONSTRAINT `fk_{p}video_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `{p}video_playlists` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}video_loc` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                {$fk('video')}
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}galleries` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `cover` VARCHAR(255) NULL,
                `photographer` VARCHAR(150) NULL,
                `location_id` INT UNSIGNED NULL,
                $common,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_gallery_slug` (`slug`),
                KEY `idx_gallery_public` (`status`, `published_at`),
                CONSTRAINT `fk_{p}gallery_loc` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                {$fk('gallery')}
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}gallery_photos` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `gallery_id` INT UNSIGNED NOT NULL,
                `image` VARCHAR(255) NOT NULL,
                `caption` VARCHAR(500) NULL,
                `photographer` VARCHAR(150) NULL,
                `location` VARCHAR(150) NULL,
                `credit` VARCHAR(150) NULL,
                `copyright` VARCHAR(150) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_gp_gallery` (`gallery_id`, `sort_order`),
                CONSTRAINT `fk_{p}gp_gallery` FOREIGN KEY (`gallery_id`) REFERENCES `{p}galleries` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}web_stories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `description` VARCHAR(500) NULL,
                `cover` VARCHAR(255) NULL,
                $common,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_story_slug` (`slug`),
                KEY `idx_story_public` (`status`, `published_at`),
                {$fk('story')}
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}web_story_slides` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `story_id` INT UNSIGNED NOT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `media` VARCHAR(255) NULL,
                `media_type` ENUM('image','video') NOT NULL DEFAULT 'image',
                `heading` VARCHAR(200) NULL,
                `body` VARCHAR(600) NULL,
                `cta_label` VARCHAR(60) NULL,
                `cta_url` VARCHAR(500) NULL,
                `text_position` ENUM('top','center','bottom') NOT NULL DEFAULT 'bottom',
                `theme` ENUM('dark','light','brand') NOT NULL DEFAULT 'dark',
                `duration` TINYINT UNSIGNED NOT NULL DEFAULT 7,
                PRIMARY KEY (`id`),
                KEY `idx_slide_story` (`story_id`, `sort_order`),
                CONSTRAINT `fk_{p}slide_story` FOREIGN KEY (`story_id`) REFERENCES `{p}web_stories` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}podcast_series` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `cover` VARCHAR(255) NULL,
                `author` VARCHAR(150) NULL,
                `category_id` INT UNSIGNED NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_series_slug` (`slug`),
                CONSTRAINT `fk_{p}series_cat` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}audio_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `description` TEXT NULL,
                `type` ENUM('news','episode') NOT NULL DEFAULT 'news',
                `series_id` INT UNSIGNED NULL,
                `episode_no` INT UNSIGNED NULL,
                `file` VARCHAR(255) NULL,
                `external_url` VARCHAR(500) NULL,
                `duration` INT UNSIGNED NULL,
                `cover` VARCHAR(255) NULL,
                `transcript` MEDIUMTEXT NULL,
                `news_id` INT UNSIGNED NULL,
                `tts_status` ENUM('none','pending','done','failed') NOT NULL DEFAULT 'none',
                $common,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_audio_slug` (`slug`),
                KEY `idx_audio_public` (`status`, `published_at`),
                KEY `idx_audio_series` (`series_id`, `episode_no`),
                CONSTRAINT `fk_{p}audio_series` FOREIGN KEY (`series_id`) REFERENCES `{p}podcast_series` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}audio_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL,
                {$fk('audio')}
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        // फ़ुटर "उपयोगी लिंक" में मल्टीमीडिया पेज (पहले से न हों तो)
        $menuId = (int) $db->value("SELECT id FROM {p}menus WHERE location = 'footer_4'");
        if ($menuId) {
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            foreach (['वीडियो' => 'videos', 'फ़ोटो गैलरी' => 'photos', 'वेब स्टोरी' => 'web-stories', 'पॉडकास्ट' => 'audio', 'लाइव टीवी' => 'live-tv'] as $title => $u) {
                if (!$db->value("SELECT id FROM {p}menu_items WHERE menu_id = ? AND type = 'custom' AND url = ?", [$menuId, $u])) {
                    $db->insert('menu_items', ['menu_id' => $menuId, 'title' => $title, 'type' => 'custom', 'url' => $u, 'sort_order' => ++$order]);
                }
            }
            foreach (glob(BASE_PATH . '/storage/cache/menus/*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }

        // पुरानी सेटिंग का लाइव टीवी लिंक → पहला चैनल (एक बार)
        $old = (string) $db->value("SELECT value FROM {p}settings WHERE name = 'live_tv_url'");
        if ($old !== '' && (int) $db->value('SELECT COUNT(*) FROM {p}live_tv_channels') === 0 && preg_match('~^https://~', $old)) {
            $db->insert('live_tv_channels', ['name' => 'लाइव टीवी', 'slug' => 'live', 'source_type' => 'youtube', 'source_url' => $old, 'is_default' => 1, 'is_live' => 1,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
};
