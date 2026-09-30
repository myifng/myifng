<?php
/**
 * Phase 11: पाठक खाते, टिप्पणियाँ, पोल, न्यूज़लेटर, नोटिफ़िकेशन सेंटर, वेब पुश
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $o = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}readers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NULL,
                `location_id` INT UNSIGNED NULL,
                `status` ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
                `email_verified_at` DATETIME NULL,
                `prefs` TEXT NULL,
                `history_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `trusted` TINYINT(1) NOT NULL DEFAULT 0,
                `last_login_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_reader_email` (`email`),
                KEY `idx_reader_status` (`status`),
                CONSTRAINT `fk_{p}reader_loc` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}reader_tokens` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reader_id` INT UNSIGNED NOT NULL,
                `type` ENUM('verify','reset') NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_rt_hash` (`token_hash`),
                CONSTRAINT `fk_{p}rt_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}reader_bookmarks` (
                `reader_id` INT UNSIGNED NOT NULL,
                `news_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`reader_id`, `news_id`),
                CONSTRAINT `fk_{p}rb_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rb_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}reader_follows` (
                `reader_id` INT UNSIGNED NOT NULL,
                `type` ENUM('category','reporter','location','topic') NOT NULL,
                `target_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`reader_id`, `type`, `target_id`),
                KEY `idx_rf_target` (`type`, `target_id`),
                CONSTRAINT `fk_{p}rf_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}reader_history` (
                `reader_id` INT UNSIGNED NOT NULL,
                `news_id` INT UNSIGNED NOT NULL,
                `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`reader_id`, `news_id`),
                KEY `idx_rh_time` (`reader_id`, `read_at`),
                CONSTRAINT `fk_{p}rh_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rh_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE
            ) $o",

            "CREATE TABLE IF NOT EXISTS `{p}comments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `news_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED NULL,
                `reader_id` INT UNSIGNED NULL,
                `user_id` INT UNSIGNED NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(190) NULL,
                `body` TEXT NOT NULL,
                `status` ENUM('pending','approved','spam','rejected') NOT NULL DEFAULT 'pending',
                `ip_hash` CHAR(64) NULL,
                `user_agent` VARCHAR(255) NULL,
                `approved_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_cm_news` (`news_id`, `status`, `created_at`),
                KEY `idx_cm_status` (`status`, `created_at`),
                CONSTRAINT `fk_{p}cm_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}cm_parent` FOREIGN KEY (`parent_id`) REFERENCES `{p}comments` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}cm_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}cm_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}comment_blocks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` ENUM('email','ip','reader','word') NOT NULL,
                `value` VARCHAR(190) NOT NULL,
                `note` VARCHAR(255) NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_cb` (`type`, `value`)
            ) $o",

            "CREATE TABLE IF NOT EXISTS `{p}polls` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `question` VARCHAR(300) NOT NULL,
                `description` VARCHAR(500) NULL,
                `multiple` TINYINT(1) NOT NULL DEFAULT 0,
                `show_results` ENUM('always','after_vote','after_close') NOT NULL DEFAULT 'after_vote',
                `require_login` TINYINT(1) NOT NULL DEFAULT 0,
                `status` ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
                `start_at` DATETIME NULL,
                `end_at` DATETIME NULL,
                `total_votes` INT UNSIGNED NOT NULL DEFAULT 0,
                `voters` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_poll_status` (`status`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}poll_options` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `poll_id` INT UNSIGNED NOT NULL,
                `label` VARCHAR(200) NOT NULL,
                `votes` INT UNSIGNED NOT NULL DEFAULT 0,
                `sort_order` SMALLINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_po_poll` (`poll_id`, `sort_order`),
                CONSTRAINT `fk_{p}po_poll` FOREIGN KEY (`poll_id`) REFERENCES `{p}polls` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}poll_votes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `poll_id` INT UNSIGNED NOT NULL,
                `voter_hash` CHAR(64) NOT NULL,
                `reader_id` INT UNSIGNED NULL,
                `options` VARCHAR(100) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pv_voter` (`poll_id`, `voter_hash`),
                KEY `idx_pv_reader` (`poll_id`, `reader_id`),
                CONSTRAINT `fk_{p}pv_poll` FOREIGN KEY (`poll_id`) REFERENCES `{p}polls` (`id`) ON DELETE CASCADE
            ) $o",

            "CREATE TABLE IF NOT EXISTS `{p}newsletter_lists` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `description` VARCHAR(300) NULL,
                `category_id` INT UNSIGNED NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `is_public` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk_{p}nl_cat` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}newsletter_subscribers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(190) NOT NULL,
                `name` VARCHAR(120) NULL,
                `status` ENUM('pending','subscribed','unsubscribed','bounced') NOT NULL DEFAULT 'pending',
                `token` CHAR(40) NOT NULL,
                `source` VARCHAR(40) NULL,
                `reader_id` INT UNSIGNED NULL,
                `confirmed_at` DATETIME NULL,
                `unsubscribed_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ns_email` (`email`),
                UNIQUE KEY `uq_ns_token` (`token`),
                KEY `idx_ns_status` (`status`),
                CONSTRAINT `fk_{p}ns_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}newsletter_list_subscribers` (
                `list_id` INT UNSIGNED NOT NULL,
                `subscriber_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`list_id`, `subscriber_id`),
                KEY `idx_nls_sub` (`subscriber_id`),
                CONSTRAINT `fk_{p}nls_list` FOREIGN KEY (`list_id`) REFERENCES `{p}newsletter_lists` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}nls_sub` FOREIGN KEY (`subscriber_id`) REFERENCES `{p}newsletter_subscribers` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}newsletter_templates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `html` MEDIUMTEXT NOT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}newsletter_campaigns` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject` VARCHAR(200) NOT NULL,
                `preheader` VARCHAR(200) NULL,
                `content` MEDIUMTEXT NULL,
                `include_top` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `template_id` INT UNSIGNED NULL,
                `list_ids` VARCHAR(255) NULL,
                `status` ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
                `scheduled_at` DATETIME NULL,
                `started_at` DATETIME NULL,
                `finished_at` DATETIME NULL,
                `recipients` INT UNSIGNED NOT NULL DEFAULT 0,
                `sent` INT UNSIGNED NOT NULL DEFAULT 0,
                `failed` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_nc_status` (`status`, `scheduled_at`),
                CONSTRAINT `fk_{p}nc_tpl` FOREIGN KEY (`template_id`) REFERENCES `{p}newsletter_templates` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}newsletter_sends` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `campaign_id` INT UNSIGNED NOT NULL,
                `subscriber_id` INT UNSIGNED NOT NULL,
                `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
                `sent_at` DATETIME NULL,
                `error` VARCHAR(255) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_nsend` (`campaign_id`, `subscriber_id`),
                KEY `idx_nsend_q` (`status`, `campaign_id`),
                CONSTRAINT `fk_{p}nsend_c` FOREIGN KEY (`campaign_id`) REFERENCES `{p}newsletter_campaigns` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}nsend_s` FOREIGN KEY (`subscriber_id`) REFERENCES `{p}newsletter_subscribers` (`id`) ON DELETE CASCADE
            ) $o",

            "CREATE TABLE IF NOT EXISTS `{p}notifications` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `recipient_type` ENUM('user','reader') NOT NULL,
                `recipient_id` INT UNSIGNED NOT NULL,
                `event` VARCHAR(40) NOT NULL,
                `title` VARCHAR(200) NOT NULL,
                `body` VARCHAR(500) NULL,
                `url` VARCHAR(500) NULL,
                `read_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_nt_rcpt` (`recipient_type`, `recipient_id`, `read_at`, `created_at`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}notification_outbox` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `channel` VARCHAR(20) NOT NULL,
                `event` VARCHAR(40) NOT NULL,
                `recipient` VARCHAR(500) NOT NULL,
                `payload` TEXT NOT NULL,
                `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `send_after` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `sent_at` DATETIME NULL,
                `error` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ob_q` (`status`, `send_after`),
                KEY `idx_ob_event` (`event`, `created_at`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}push_subscriptions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `endpoint` VARCHAR(700) NOT NULL,
                `endpoint_hash` CHAR(64) NOT NULL,
                `p256dh` VARCHAR(200) NOT NULL,
                `auth` VARCHAR(60) NOT NULL,
                `reader_id` INT UNSIGNED NULL,
                `topics` VARCHAR(255) NULL,
                `user_agent` VARCHAR(255) NULL,
                `fails` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_sent_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_push_ep` (`endpoint_hash`),
                CONSTRAINT `fk_{p}push_reader` FOREIGN KEY (`reader_id`) REFERENCES `{p}readers` (`id`) ON DELETE SET NULL
            ) $o",
        ] as $sql) {
            $db->query($sql);
        }

        if (!in_array('allow_comments', array_column($db->all('SHOW COLUMNS FROM `' . $db->table('news') . '`'), 'Field'), true)) {
            $db->query('ALTER TABLE `' . $db->table('news') . '` ADD COLUMN `allow_comments` TINYINT(1) NOT NULL DEFAULT 1 AFTER `robots`');
        }

        // डिफ़ॉल्ट न्यूज़लेटर सूची और टेम्पलेट
        if (!$db->value('SELECT COUNT(*) FROM {p}newsletter_lists')) {
            $db->insert('newsletter_lists', ['name' => 'रोज़ की बड़ी ख़बरें', 'description' => 'हर सुबह दिन की सबसे ज़रूरी ख़बरें', 'is_default' => 1, 'is_public' => 1]);
        }
        if (!$db->value('SELECT COUNT(*) FROM {p}newsletter_templates')) {
            $db->insert('newsletter_templates', ['name' => 'साधारण (डिफ़ॉल्ट)', 'is_default' => 1, 'html' => self::TEMPLATE]);
        }

        // Editor को टिप्पणी मॉडरेशन और पोल
        foreach (['comments.view', 'comments.approve', 'comments.edit', 'polls.view', 'polls.create', 'polls.edit'] as $perm) {
            $pid = (int) $db->value('SELECT id FROM {p}permissions WHERE name = ?', [$perm]);
            if (!$pid) {
                continue;
            }
            foreach ($db->all("SELECT id FROM {p}roles WHERE slug = 'editor'") as $r) {
                if (!$db->value('SELECT 1 FROM {p}role_permissions WHERE role_id = ? AND permission_id = ?', [$r['id'], $pid])) {
                    $db->insert('role_permissions', ['role_id' => $r['id'], 'permission_id' => $pid]);
                }
            }
        }
    }

    private const TEMPLATE = <<<'HTML'
<!doctype html>
<html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{subject}}</title></head>
<body style="margin:0;padding:0;background:#f2f2f2;font-family:Arial,'Noto Sans Devanagari',sans-serif;color:#1c1b1d">
<span style="display:none;max-height:0;overflow:hidden">{{preheader}}</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f2"><tr><td align="center" style="padding:16px 8px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden">
<tr><td style="background:{{brand_color}};padding:16px 20px;color:#ffffff;font-size:22px;font-weight:bold">{{logo}}</td></tr>
<tr><td style="padding:20px;font-size:16px;line-height:1.6">{{content}}</td></tr>
<tr><td style="padding:0 20px 20px">{{top_news}}</td></tr>
<tr><td style="padding:16px 20px;background:#fafafa;color:#6d6873;font-size:12px;line-height:1.5">आपको यह ईमेल इसलिए मिला क्योंकि आपने {{site_name}} का न्यूज़लेटर लिया है।<br><a href="{{unsubscribe_url}}" style="color:#6d6873">अनसब्सक्राइब करें</a> · <a href="{{site_url}}" style="color:#6d6873">{{site_name}}</a></td></tr>
</table></td></tr></table></body></html>
HTML;
};
