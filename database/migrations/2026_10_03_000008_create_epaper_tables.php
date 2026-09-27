<?php
/**
 * Phase 8: ई-पेपर (संस्करण, अंक, पेज, हॉटस्पॉट)
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}epaper_editions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(170) NOT NULL,
                `type` ENUM('main','state','district','city') NOT NULL DEFAULT 'main',
                `location_id` INT UNSIGNED NULL,
                `description` VARCHAR(500) NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_edition_slug` (`slug`),
                CONSTRAINT `fk_{p}edition_loc` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}epaper_issues` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `edition_id` INT UNSIGNED NOT NULL,
                `issue_date` DATE NOT NULL,
                `title` VARCHAR(190) NULL,
                `dir` VARCHAR(120) NOT NULL,
                `pdf` VARCHAR(255) NULL,
                `pdf_size` INT UNSIGNED NULL,
                `cover` VARCHAR(255) NULL,
                `page_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `access` ENUM('free','premium') NOT NULL DEFAULT 'free',
                `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
                `publish_at` DATETIME NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_issue_date` (`edition_id`, `issue_date`),
                KEY `idx_issue_public` (`status`, `publish_at`),
                CONSTRAINT `fk_{p}issue_edition` FOREIGN KEY (`edition_id`) REFERENCES `{p}epaper_editions` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}issue_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}epaper_pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `issue_id` INT UNSIGNED NOT NULL,
                `page_no` SMALLINT UNSIGNED NOT NULL,
                `image` VARCHAR(255) NOT NULL,
                `thumb` VARCHAR(255) NULL,
                `width` SMALLINT UNSIGNED NULL,
                `height` SMALLINT UNSIGNED NULL,
                `label` VARCHAR(100) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_page_issue` (`issue_id`, `page_no`),
                CONSTRAINT `fk_{p}page_issue` FOREIGN KEY (`issue_id`) REFERENCES `{p}epaper_issues` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}epaper_hotspots` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `page_id` INT UNSIGNED NOT NULL,
                `x` DECIMAL(6,3) NOT NULL,
                `y` DECIMAL(6,3) NOT NULL,
                `w` DECIMAL(6,3) NOT NULL,
                `h` DECIMAL(6,3) NOT NULL,
                `news_id` INT UNSIGNED NULL,
                `url` VARCHAR(500) NULL,
                `label` VARCHAR(190) NULL,
                PRIMARY KEY (`id`),
                KEY `idx_hs_page` (`page_id`),
                CONSTRAINT `fk_{p}hs_page` FOREIGN KEY (`page_id`) REFERENCES `{p}epaper_pages` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}hs_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        // एक मुख्य संस्करण (एक बार)
        if ((int) $db->value('SELECT COUNT(*) FROM {p}epaper_editions') === 0) {
            $db->insert('epaper_editions', ['name' => 'मुख्य संस्करण', 'slug' => 'main', 'type' => 'main', 'is_default' => 1,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }

        // Phase 7 ने फ़ुटर में "लाइव टीवी" दोबारा जोड़ दिया था (मेनू-प्रकार वाला लिंक पहले से था): दोहराव हटाएँ
        foreach ($db->all("SELECT DISTINCT menu_id FROM {p}menu_items WHERE type = 'live_tv'") as $m) {
            $db->query("DELETE FROM {p}menu_items WHERE menu_id = ? AND type = 'custom' AND url = 'live-tv'", [$m['menu_id']]);
        }

        // फ़ुटर "उपयोगी लिंक" में ई-पेपर
        $menuId = (int) $db->value("SELECT id FROM {p}menus WHERE location = 'footer_4'");
        if ($menuId && !$db->value("SELECT id FROM {p}menu_items WHERE menu_id = ? AND ((type = 'custom' AND url = 'epaper') OR type = 'epaper')", [$menuId])) {
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            $db->insert('menu_items', ['menu_id' => $menuId, 'title' => 'ई-पेपर', 'type' => 'custom', 'url' => 'epaper', 'sort_order' => $order + 1]);
            foreach (glob(BASE_PATH . '/storage/cache/menus/*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }
    }
};
