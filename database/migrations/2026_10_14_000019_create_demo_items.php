<?php
/**
 * डेमो डेटा का हिसाब: इंस्टॉलर ने जो नमूना पंक्तियाँ/फ़ाइलें जोड़ीं, उनकी सूची
 * ताकि लाइव होने से पहले एडमिन एक क्लिक में सारा डेमो डेटा हटा सके।
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS `{p}demo_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tbl` VARCHAR(64) NOT NULL,
            `row_id` INT UNSIGNED NULL,
            `file` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_demo_tbl` (`tbl`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
