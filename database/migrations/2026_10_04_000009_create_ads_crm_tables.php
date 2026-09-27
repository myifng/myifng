<?php
/**
 * Phase 9: विज्ञापन (स्लॉट, विज्ञापन, गिनती) और विज्ञापनदाता CRM (कैंपेन, इनवॉइस, भुगतान)
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}advertisers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `company` VARCHAR(190) NOT NULL,
                `contact_name` VARCHAR(150) NULL,
                `email` VARCHAR(190) NULL,
                `phone` VARCHAR(20) NULL,
                `gstin` VARCHAR(20) NULL,
                `address` VARCHAR(400) NULL,
                `city` VARCHAR(120) NULL,
                `status` ENUM('lead','active','inactive') NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `user_id` INT UNSIGNED NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_adv_status` (`status`),
                CONSTRAINT `fk_{p}adv_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}adv_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_campaigns` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `advertiser_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `pricing` ENUM('fixed','cpm','cpc') NOT NULL DEFAULT 'fixed',
                `rate` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `budget` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `status` ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_camp_adv` (`advertiser_id`, `status`),
                CONSTRAINT `fk_{p}camp_adv` FOREIGN KEY (`advertiser_id`) REFERENCES `{p}advertisers` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_slots` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slot_key` VARCHAR(60) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `placement` VARCHAR(30) NOT NULL DEFAULT 'custom',
                `size` VARCHAR(40) NULL,
                `max_ads` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `description` VARCHAR(300) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slot_key` (`slot_key`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ads` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `campaign_id` INT UNSIGNED NULL,
                `name` VARCHAR(190) NOT NULL,
                `type` ENUM('image','html','adsense','video','link') NOT NULL DEFAULT 'image',
                `image` VARCHAR(255) NULL,
                `mobile_image` VARCHAR(255) NULL,
                `code` MEDIUMTEXT NULL,
                `video_url` VARCHAR(500) NULL,
                `target_url` VARCHAR(500) NULL,
                `text` VARCHAR(300) NULL,
                `devices` ENUM('all','desktop','mobile') NOT NULL DEFAULT 'all',
                `category_ids` VARCHAR(500) NULL,
                `location_ids` VARCHAR(500) NULL,
                `start_at` DATETIME NULL,
                `end_at` DATETIME NULL,
                `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                `max_impressions` INT UNSIGNED NULL,
                `max_clicks` INT UNSIGNED NULL,
                `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `status` ENUM('active','paused','draft') NOT NULL DEFAULT 'active',
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ads_live` (`status`, `start_at`, `end_at`),
                KEY `idx_ads_campaign` (`campaign_id`),
                CONSTRAINT `fk_{p}ads_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `{p}ad_campaigns` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}ads_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_placements` (
                `ad_id` INT UNSIGNED NOT NULL,
                `slot_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`ad_id`, `slot_id`),
                KEY `idx_place_slot` (`slot_id`),
                CONSTRAINT `fk_{p}place_ad` FOREIGN KEY (`ad_id`) REFERENCES `{p}ads` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}place_slot` FOREIGN KEY (`slot_id`) REFERENCES `{p}ad_slots` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_stats_daily` (
                `ad_id` INT UNSIGNED NOT NULL,
                `day` DATE NOT NULL,
                `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`ad_id`, `day`),
                KEY `idx_stats_day` (`day`),
                CONSTRAINT `fk_{p}stats_ad` FOREIGN KEY (`ad_id`) REFERENCES `{p}ads` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_invoices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_no` VARCHAR(30) NOT NULL,
                `advertiser_id` INT UNSIGNED NOT NULL,
                `campaign_id` INT UNSIGNED NULL,
                `issue_date` DATE NOT NULL,
                `due_date` DATE NULL,
                `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
                `tax` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `paid` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `status` ENUM('draft','sent','partial','paid','cancelled') NOT NULL DEFAULT 'draft',
                `notes` TEXT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_invoice_no` (`invoice_no`),
                KEY `idx_inv_adv` (`advertiser_id`, `status`),
                CONSTRAINT `fk_{p}inv_adv` FOREIGN KEY (`advertiser_id`) REFERENCES `{p}advertisers` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_{p}inv_camp` FOREIGN KEY (`campaign_id`) REFERENCES `{p}ad_campaigns` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_invoice_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_id` INT UNSIGNED NOT NULL,
                `description` VARCHAR(300) NOT NULL,
                `qty` DECIMAL(10,2) NOT NULL DEFAULT 1,
                `rate` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `sort_order` SMALLINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_item_inv` (`invoice_id`),
                CONSTRAINT `fk_{p}item_inv` FOREIGN KEY (`invoice_id`) REFERENCES `{p}ad_invoices` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}ad_payments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `invoice_id` INT UNSIGNED NOT NULL,
                `advertiser_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `paid_on` DATE NOT NULL,
                `method` ENUM('cash','bank','upi','cheque','online','other') NOT NULL DEFAULT 'bank',
                `reference` VARCHAR(120) NULL,
                `notes` VARCHAR(300) NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pay_date` (`paid_on`),
                KEY `idx_pay_adv` (`advertiser_id`),
                CONSTRAINT `fk_{p}pay_inv` FOREIGN KEY (`invoice_id`) REFERENCES `{p}ad_invoices` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}pay_adv` FOREIGN KEY (`advertiser_id`) REFERENCES `{p}advertisers` (`id`) ON DELETE CASCADE
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        // पहले से बने स्लॉट (सिस्टम; हटाए नहीं जा सकते, बंद किए जा सकते हैं)
        $slots = [
            ['header', 'हेडर (लोगो के बगल)', 'header', '728×90', 1],
            ['below_header', 'हेडर के नीचे (पूरी चौड़ाई)', 'below_header', '970×90 / 728×90', 1],
            ['home_top', 'होमपेज: ऊपर', 'homepage', '970×250', 1],
            ['home_middle', 'होमपेज: बीच', 'homepage', '728×90', 1],
            ['home_bottom', 'होमपेज: नीचे', 'homepage', '728×90', 1],
            ['sidebar_top', 'साइडबार: ऊपर', 'sidebar', '300×250', 1],
            ['sidebar_bottom', 'साइडबार: नीचे', 'sidebar', '300×600', 1],
            ['article_top', 'ख़बर: शीर्षक के नीचे', 'article_top', '728×90', 1],
            ['article_inline', 'ख़बर: बीच में', 'article_inline', '300×250 / 728×90', 1],
            ['article_bottom', 'ख़बर: नीचे', 'article_bottom', '728×90', 1],
            ['category_top', 'श्रेणी / लोकेशन / टॉपिक पेज: ऊपर', 'category', '728×90', 1],
            ['mobile_sticky', 'मोबाइल: नीचे चिपका', 'mobile_sticky', '320×50', 1],
            ['popup', 'पॉपअप', 'popup', '300×250 / 600×400', 1],
            ['footer', 'फ़ुटर के ऊपर', 'footer', '970×90 / 728×90', 1],
        ];
        $now = date('Y-m-d H:i:s');
        foreach ($slots as [$key, $name, $place, $size, $max]) {
            if (!$db->value('SELECT id FROM {p}ad_slots WHERE slot_key = ?', [$key])) {
                $db->insert('ad_slots', ['slot_key' => $key, 'name' => $name, 'placement' => $place, 'size' => $size, 'max_ads' => $max, 'is_system' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // नई अनुमति ads.manage (HTML/AdSense कोड): बनी हुई साइट पर Admin रोल को भी
        if (!$db->value("SELECT id FROM {p}permissions WHERE name = 'ads.manage'")) {
            $db->insert('permissions', ['module' => 'ads', 'action' => 'manage', 'name' => 'ads.manage']);
        }
        $pid = (int) $db->value("SELECT id FROM {p}permissions WHERE name = 'ads.manage'");
        foreach ($db->all("SELECT id FROM {p}roles WHERE slug = 'admin'") as $r) {
            if (!$db->value('SELECT 1 FROM {p}role_permissions WHERE role_id = ? AND permission_id = ?', [$r['id'], $pid])) {
                $db->insert('role_permissions', ['role_id' => $r['id'], 'permission_id' => $pid]);
            }
        }
    }
};
