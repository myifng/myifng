<?php
/**
 * Phase 15: बैकअप का इतिहास + रिपोर्ट/लॉगिन-इतिहास/एडमिन सूची के इंडेक्स
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS `{p}backups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` ENUM('db','files','full') NOT NULL,
            `file` VARCHAR(190) NULL,
            `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `checksum` CHAR(64) NULL,
            `status` ENUM('running','done','failed') NOT NULL DEFAULT 'running',
            `note` VARCHAR(255) NULL,
            `error` VARCHAR(500) NULL,
            `trigger` ENUM('manual','schedule') NOT NULL DEFAULT 'manual',
            `created_by` INT UNSIGNED NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `finished_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_bk_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $idx = [
            ['news_remarks', 'idx_rem_type_time', '`type`, `created_at`'],
            ['news', 'idx_news_updated', '`status`, `updated_at`'],
            ['login_history', 'idx_lh_time', '`created_at`'],
            ['login_history', 'idx_lh_status', '`status`, `created_at`'],
            ['audit_logs', 'idx_audit_action', '`action`, `created_at`'],
        ];
        foreach ($idx as [$t, $name, $cols]) {
            if (!$db->first("SHOW INDEX FROM {p}$t WHERE Key_name = ?", [$name])) {
                $db->query("ALTER TABLE {p}$t ADD INDEX `$name` ($cols)");
            }
        }
    }
};
