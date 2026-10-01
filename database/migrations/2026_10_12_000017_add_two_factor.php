<?php
/**
 * दो-चरण लॉगिन (ईमेल OTP): लॉगिन इतिहास में OTP की स्थितियाँ + "इस डिवाइस को याद रखें"
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->query("ALTER TABLE {p}login_history MODIFY `status` ENUM('success','failed','blocked','logout','otp_sent','otp_failed') NOT NULL");
        $db->query("CREATE TABLE IF NOT EXISTS `{p}trusted_devices` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `user_agent` VARCHAR(255) NULL,
            `ip` VARCHAR(45) NULL,
            `expires_at` DATETIME NOT NULL,
            `last_used_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_td_token` (`token_hash`),
            KEY `idx_td_user` (`user_id`),
            CONSTRAINT `fk_{p}td_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
