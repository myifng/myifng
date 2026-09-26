<?php
/**
 * Phase 1: नींव की टेबल
 * roles, permissions, role_permissions, users, user_permissions, password_resets,
 * login_attempts, login_history, settings, audit_logs, languages, modules
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $sql = [
            "CREATE TABLE IF NOT EXISTS `{p}roles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(80) NOT NULL,
                `slug` VARCHAR(80) NOT NULL,
                `description` VARCHAR(255) NULL,
                `level` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_roles_slug` (`slug`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}permissions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `module` VARCHAR(60) NOT NULL,
                `action` VARCHAR(30) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_permissions_name` (`name`),
                KEY `idx_permissions_module` (`module`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}role_permissions` (
                `role_id` INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`role_id`, `permission_id`),
                KEY `idx_rp_permission` (`permission_id`),
                CONSTRAINT `fk_{p}rp_role` FOREIGN KEY (`role_id`) REFERENCES `{p}roles` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `{p}permissions` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `mobile` VARCHAR(20) NULL,
                `password` VARCHAR(255) NOT NULL,
                `role_id` INT UNSIGNED NOT NULL,
                `avatar` VARCHAR(255) NULL,
                `bio` VARCHAR(500) NULL,
                `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
                `language` VARCHAR(10) NOT NULL DEFAULT 'hi',
                `last_login_at` DATETIME NULL,
                `last_login_ip` VARCHAR(45) NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `deleted_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_users_email` (`email`),
                KEY `idx_users_role` (`role_id`),
                KEY `idx_users_status` (`status`, `deleted_at`),
                CONSTRAINT `fk_{p}users_role` FOREIGN KEY (`role_id`) REFERENCES `{p}roles` (`id`) ON DELETE RESTRICT
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}user_permissions` (
                `user_id` INT UNSIGNED NOT NULL,
                `permission_id` INT UNSIGNED NOT NULL,
                `allow` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`user_id`, `permission_id`),
                KEY `idx_up_permission` (`permission_id`),
                CONSTRAINT `fk_{p}up_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}up_permission` FOREIGN KEY (`permission_id`) REFERENCES `{p}permissions` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}password_resets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pr_token` (`token_hash`),
                KEY `idx_pr_user` (`user_id`),
                CONSTRAINT `fk_{p}pr_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}login_attempts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(45) NOT NULL,
                `email` VARCHAR(190) NULL,
                `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_la_ip` (`ip`, `attempted_at`),
                KEY `idx_la_email` (`email`, `attempted_at`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}login_history` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `ip` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `status` ENUM('success','failed','blocked','logout') NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_lh_user` (`user_id`, `created_at`),
                CONSTRAINT `fk_{p}lh_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}settings` (
                `name` VARCHAR(100) NOT NULL,
                `value` MEDIUMTEXT NULL,
                `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
                `type` VARCHAR(20) NOT NULL DEFAULT 'text',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`name`),
                KEY `idx_settings_group` (`group_name`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NULL,
                `user_name` VARCHAR(120) NULL,
                `role` VARCHAR(80) NULL,
                `action` VARCHAR(60) NOT NULL,
                `module` VARCHAR(60) NOT NULL,
                `record_id` VARCHAR(64) NULL,
                `description` VARCHAR(500) NULL,
                `old_values` LONGTEXT NULL,
                `new_values` LONGTEXT NULL,
                `ip` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `url` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_audit_created` (`created_at`),
                KEY `idx_audit_module` (`module`, `created_at`),
                KEY `idx_audit_user` (`user_id`, `created_at`),
                CONSTRAINT `fk_{p}audit_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}languages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(10) NOT NULL,
                `name` VARCHAR(60) NOT NULL,
                `native_name` VARCHAR(60) NOT NULL,
                `direction` ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `status` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` SMALLINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_languages_code` (`code`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}modules` (
                `module_key` VARCHAR(60) NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `installed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`module_key`)
            ) $opts",
        ];
        foreach ($sql as $statement) {
            $db->pdo()->exec($db->sql($statement));
        }
    }
};
