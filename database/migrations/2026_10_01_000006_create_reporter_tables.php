<?php
/**
 * Phase 6: रिपोर्टर भर्ती, आवेदन, रिपोर्टर, दस्तावेज़ (ID कार्ड/पत्र), ब्यूरो, OTP
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}bureaus` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `type` ENUM('head_office','state','district','tehsil','city') NOT NULL DEFAULT 'district',
                `parent_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `address` VARCHAR(300) NULL,
                `phone` VARCHAR(20) NULL,
                `email` VARCHAR(190) NULL,
                `chief_user_id` INT UNSIGNED NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_bureau_parent` (`parent_id`),
                CONSTRAINT `fk_{p}bureau_parent` FOREIGN KEY (`parent_id`) REFERENCES `{p}bureaus` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_{p}bureau_location` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}bureau_chief` FOREIGN KEY (`chief_user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}reporters` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `reporter_code` VARCHAR(30) NOT NULL,
                `application_id` INT UNSIGNED NULL,
                `designation` VARCHAR(100) NOT NULL,
                `reporter_type` VARCHAR(30) NOT NULL DEFAULT 'district',
                `beat_category_id` INT UNSIGNED NULL,
                `area_location_id` INT UNSIGNED NULL,
                `district_id` INT UNSIGNED NULL,
                `state_id` INT UNSIGNED NULL,
                `bureau_id` INT UNSIGNED NULL,
                `joining_date` DATE NOT NULL,
                `valid_until` DATE NOT NULL,
                `status` ENUM('active','suspended','resigned','expired') NOT NULL DEFAULT 'active',
                `photo` VARCHAR(255) NULL,
                `guardian_name` VARCHAR(120) NULL,
                `dob` DATE NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `blood_group` VARCHAR(5) NULL,
                `address` VARCHAR(400) NULL,
                `kyc` TEXT NULL,
                `notes` TEXT NULL,
                `status_reason` VARCHAR(300) NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_reporter_user` (`user_id`),
                UNIQUE KEY `uq_reporter_code` (`reporter_code`),
                KEY `idx_reporter_status` (`status`, `valid_until`),
                KEY `idx_reporter_bureau` (`bureau_id`),
                CONSTRAINT `fk_{p}rep_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_{p}rep_beat` FOREIGN KEY (`beat_category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}rep_area` FOREIGN KEY (`area_location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}rep_district` FOREIGN KEY (`district_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}rep_state` FOREIGN KEY (`state_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}rep_bureau` FOREIGN KEY (`bureau_id`) REFERENCES `{p}bureaus` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}reporter_applications` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `app_no` VARCHAR(30) NOT NULL,
                `full_name` VARCHAR(120) NOT NULL,
                `guardian_name` VARCHAR(120) NOT NULL,
                `dob` DATE NOT NULL,
                `gender` ENUM('male','female','other') NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `whatsapp` VARCHAR(20) NULL,
                `email` VARCHAR(190) NOT NULL,
                `address` VARCHAR(400) NOT NULL,
                `state_id` INT UNSIGNED NULL,
                `district_id` INT UNSIGNED NULL,
                `city` VARCHAR(120) NULL,
                `pincode` VARCHAR(6) NOT NULL,
                `preferred_area_id` INT UNSIGNED NULL,
                `reporter_type` VARCHAR(30) NOT NULL,
                `experience_years` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `previous_org` VARCHAR(190) NULL,
                `education` VARCHAR(150) NULL,
                `languages` VARCHAR(150) NULL,
                `about` TEXT NULL,
                `documents` TEXT NULL,
                `status` ENUM('new','under_review','document_pending','verification_pending','approved','rejected','on_hold') NOT NULL DEFAULT 'new',
                `assigned_to` INT UNSIGNED NULL,
                `public_note` VARCHAR(500) NULL,
                `reporter_id` INT UNSIGNED NULL,
                `ip` VARCHAR(45) NULL,
                `consent_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_app_no` (`app_no`),
                KEY `idx_app_status` (`status`, `created_at`),
                KEY `idx_app_mobile` (`mobile`),
                CONSTRAINT `fk_{p}app_state` FOREIGN KEY (`state_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}app_district` FOREIGN KEY (`district_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}app_area` FOREIGN KEY (`preferred_area_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}app_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}app_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `{p}reporters` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}application_remarks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `application_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `type` ENUM('note','status','correction') NOT NULL DEFAULT 'note',
                `message` TEXT NULL,
                `from_status` VARCHAR(30) NULL,
                `to_status` VARCHAR(30) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_apprem_app` (`application_id`),
                CONSTRAINT `fk_{p}apprem_app` FOREIGN KEY (`application_id`) REFERENCES `{p}reporter_applications` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}apprem_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}reporter_documents` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `reporter_id` INT UNSIGNED NOT NULL,
                `type` ENUM('id_card','authorization','appointment','press_certificate','experience') NOT NULL,
                `doc_no` VARCHAR(40) NOT NULL,
                `issued_at` DATE NOT NULL,
                `valid_until` DATE NULL,
                `issued_by` INT UNSIGNED NULL,
                `status` ENUM('active','revoked') NOT NULL DEFAULT 'active',
                `revoked_reason` VARCHAR(300) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_doc_no` (`doc_no`),
                KEY `idx_doc_reporter` (`reporter_id`, `type`, `status`),
                CONSTRAINT `fk_{p}rdoc_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `{p}reporters` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rdoc_user` FOREIGN KEY (`issued_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}otp_codes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `purpose` VARCHAR(30) NOT NULL,
                `identifier` VARCHAR(100) NOT NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `ip` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_otp_lookup` (`purpose`, `identifier`, `created_at`)
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        // फ़ुटर "उपयोगी लिंक" में (पहले से न हों तो)
        $menuId = (int) $db->value("SELECT id FROM {p}menus WHERE location = 'footer_4'");
        if ($menuId) {
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            foreach (['रिपोर्टर बनें' => 'join-as-reporter', 'रिपोर्टर सत्यापन' => 'verify-reporter'] as $title => $u) {
                if (!$db->value("SELECT id FROM {p}menu_items WHERE menu_id = ? AND type = 'custom' AND url = ?", [$menuId, $u])) {
                    $db->insert('menu_items', ['menu_id' => $menuId, 'title' => $title, 'type' => 'custom', 'url' => $u, 'sort_order' => ++$order]);
                }
            }
            foreach (glob(BASE_PATH . '/storage/cache/menus/*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }

        // मुख्यालय ब्यूरो (एक बार)
        if ((int) $db->value('SELECT COUNT(*) FROM {p}bureaus') === 0) {
            $db->insert('bureaus', ['name' => 'मुख्यालय', 'type' => 'head_office', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
};
