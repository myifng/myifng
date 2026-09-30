<?php
/**
 * Phase 14: फ़ैक्ट चेक, चुनाव केंद्र, खेल केंद्र
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $o = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            // ---------- फ़ैक्ट चेक ----------
            "CREATE TABLE IF NOT EXISTS `{p}fact_checks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `claim` TEXT NOT NULL,
                `claim_by` VARCHAR(190) NULL,
                `claim_url` VARCHAR(500) NULL,
                `claim_date` DATE NULL,
                `claim_medium` VARCHAR(20) NOT NULL DEFAULT 'social',
                `verdict` ENUM('true','false','misleading','partly_true','unverified') NOT NULL DEFAULT 'unverified',
                `summary` VARCHAR(500) NULL,
                `evidence` MEDIUMTEXT NULL,
                `explanation` MEDIUMTEXT NULL,
                `sources` TEXT NULL,
                `image` VARCHAR(255) NULL,
                `category_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `news_id` INT UNSIGNED NULL,
                `author_id` INT UNSIGNED NULL,
                `reviewer_id` INT UNSIGNED NULL,
                `status` ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
                `published_at` DATETIME NULL,
                `update_note` VARCHAR(500) NULL,
                `updated_verdict_at` DATETIME NULL,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `views` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_fc_slug` (`slug`),
                KEY `idx_fc_pub` (`status`, `published_at`),
                KEY `idx_fc_verdict` (`verdict`),
                KEY `idx_fc_news` (`news_id`),
                CONSTRAINT `fk_{p}fc_cat` FOREIGN KEY (`category_id`) REFERENCES `{p}categories` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}fc_loc` FOREIGN KEY (`location_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}fc_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL
            ) $o",

            // ---------- चुनाव ----------
            "CREATE TABLE IF NOT EXISTS `{p}election_parties` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `short_name` VARCHAR(20) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `color` VARCHAR(7) NOT NULL DEFAULT '#777777',
                `symbol` VARCHAR(255) NULL,
                `alliance` VARCHAR(60) NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ep_slug` (`slug`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}elections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(150) NOT NULL,
                `type` ENUM('lok_sabha','vidhan_sabha','bypoll','local') NOT NULL DEFAULT 'vidhan_sabha',
                `state_id` INT UNSIGNED NULL,
                `year` SMALLINT UNSIGNED NOT NULL,
                `poll_dates` VARCHAR(190) NULL,
                `counting_date` DATE NULL,
                `status` ENUM('draft','upcoming','polling','counting','declared') NOT NULL DEFAULT 'draft',
                `total_seats` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `majority` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `topic_id` INT UNSIGNED NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `description` TEXT NULL,
                `source_note` VARCHAR(190) NULL,
                `synced_at` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_el_slug` (`slug`),
                KEY `idx_el_status` (`status`),
                CONSTRAINT `fk_{p}el_state` FOREIGN KEY (`state_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}el_topic` FOREIGN KEY (`topic_id`) REFERENCES `{p}topics` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}election_seats` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(150) NOT NULL,
                `number` SMALLINT UNSIGNED NULL,
                `house` ENUM('ls','vs','local') NOT NULL DEFAULT 'vs',
                `state_id` INT UNSIGNED NULL,
                `district_id` INT UNSIGNED NULL,
                `reserved` ENUM('gen','sc','st') NOT NULL DEFAULT 'gen',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_es_slug` (`house`, `state_id`, `slug`),
                KEY `idx_es_state` (`state_id`, `house`),
                CONSTRAINT `fk_{p}es_state` FOREIGN KEY (`state_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}es_dist` FOREIGN KEY (`district_id`) REFERENCES `{p}locations` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}election_candidates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `election_id` INT UNSIGNED NOT NULL,
                `seat_id` INT UNSIGNED NOT NULL,
                `party_id` INT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `photo` VARCHAR(255) NULL,
                `age` TINYINT UNSIGNED NULL,
                `gender` ENUM('m','f','o') NULL,
                `education` VARCHAR(120) NULL,
                `is_incumbent` TINYINT(1) NOT NULL DEFAULT 0,
                `is_key` TINYINT(1) NOT NULL DEFAULT 0,
                `votes` INT UNSIGNED NOT NULL DEFAULT 0,
                `withdrawn` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_ec_seat` (`election_id`, `seat_id`),
                KEY `idx_ec_party` (`election_id`, `party_id`),
                CONSTRAINT `fk_{p}ec_el` FOREIGN KEY (`election_id`) REFERENCES `{p}elections` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}ec_seat` FOREIGN KEY (`seat_id`) REFERENCES `{p}election_seats` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}ec_party` FOREIGN KEY (`party_id`) REFERENCES `{p}election_parties` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}election_results` (
                `election_id` INT UNSIGNED NOT NULL,
                `seat_id` INT UNSIGNED NOT NULL,
                `status` ENUM('awaited','counting','declared') NOT NULL DEFAULT 'awaited',
                `electors` INT UNSIGNED NULL,
                `total_votes` INT UNSIGNED NOT NULL DEFAULT 0,
                `rounds` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `total_rounds` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `leader_id` INT UNSIGNED NULL,
                `runner_id` INT UNSIGNED NULL,
                `margin` INT UNSIGNED NOT NULL DEFAULT 0,
                `declared_at` DATETIME NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`election_id`, `seat_id`),
                KEY `idx_er_status` (`election_id`, `status`),
                CONSTRAINT `fk_{p}er_el` FOREIGN KEY (`election_id`) REFERENCES `{p}elections` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}er_seat` FOREIGN KEY (`seat_id`) REFERENCES `{p}election_seats` (`id`) ON DELETE CASCADE
            ) $o",

            // ---------- खेल ----------
            "CREATE TABLE IF NOT EXISTS `{p}sports_tournaments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(150) NOT NULL,
                `sport` VARCHAR(20) NOT NULL DEFAULT 'cricket',
                `season` VARCHAR(40) NULL,
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `status` ENUM('draft','upcoming','live','completed') NOT NULL DEFAULT 'upcoming',
                `topic_id` INT UNSIGNED NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `points_win` TINYINT UNSIGNED NOT NULL DEFAULT 2,
                `points_draw` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `description` TEXT NULL,
                `logo` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_st_slug` (`slug`),
                CONSTRAINT `fk_{p}st_topic` FOREIGN KEY (`topic_id`) REFERENCES `{p}topics` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}sports_teams` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `short_name` VARCHAR(12) NOT NULL,
                `slug` VARCHAR(150) NOT NULL,
                `sport` VARCHAR(20) NOT NULL DEFAULT 'cricket',
                `logo` VARCHAR(255) NULL,
                `color` VARCHAR(7) NOT NULL DEFAULT '#1f5fbf',
                `country` VARCHAR(60) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_stm_slug` (`slug`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}sports_players` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `team_id` INT UNSIGNED NULL,
                `name` VARCHAR(150) NOT NULL,
                `role` VARCHAR(60) NULL,
                `photo` VARCHAR(255) NULL,
                `jersey` VARCHAR(5) NULL,
                `is_captain` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sp_team` (`team_id`),
                CONSTRAINT `fk_{p}sp_team` FOREIGN KEY (`team_id`) REFERENCES `{p}sports_teams` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}sports_matches` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tournament_id` INT UNSIGNED NULL,
                `sport` VARCHAR(20) NOT NULL DEFAULT 'cricket',
                `team1_id` INT UNSIGNED NULL,
                `team2_id` INT UNSIGNED NULL,
                `title` VARCHAR(190) NULL,
                `stage` VARCHAR(60) NULL,
                `group_name` VARCHAR(30) NULL,
                `venue` VARCHAR(190) NULL,
                `start_at` DATETIME NOT NULL,
                `status` ENUM('scheduled','live','break','completed','abandoned','postponed') NOT NULL DEFAULT 'scheduled',
                `score1` VARCHAR(60) NULL,
                `score2` VARCHAR(60) NULL,
                `status_text` VARCHAR(190) NULL,
                `result` VARCHAR(255) NULL,
                `winner_id` INT UNSIGNED NULL,
                `is_draw` TINYINT(1) NOT NULL DEFAULT 0,
                `toss` VARCHAR(190) NULL,
                `potm` VARCHAR(150) NULL,
                `news_id` INT UNSIGNED NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sm_time` (`status`, `start_at`),
                KEY `idx_sm_tour` (`tournament_id`, `start_at`),
                CONSTRAINT `fk_{p}sm_tour` FOREIGN KEY (`tournament_id`) REFERENCES `{p}sports_tournaments` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}sm_t1` FOREIGN KEY (`team1_id`) REFERENCES `{p}sports_teams` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}sm_t2` FOREIGN KEY (`team2_id`) REFERENCES `{p}sports_teams` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}sm_news` FOREIGN KEY (`news_id`) REFERENCES `{p}news` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}sports_commentary` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `match_id` INT UNSIGNED NOT NULL,
                `marker` VARCHAR(12) NULL,
                `type` VARCHAR(10) NOT NULL DEFAULT 'info',
                `text` VARCHAR(1000) NOT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_sc_match` (`match_id`, `id`),
                CONSTRAINT `fk_{p}sc_match` FOREIGN KEY (`match_id`) REFERENCES `{p}sports_matches` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}sports_standings` (
                `tournament_id` INT UNSIGNED NOT NULL,
                `team_id` INT UNSIGNED NOT NULL,
                `group_name` VARCHAR(30) NOT NULL DEFAULT '',
                `played` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `won` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `lost` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `drawn` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `no_result` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `points` SMALLINT NOT NULL DEFAULT 0,
                `nrr` DECIMAL(6,3) NULL,
                `gd` SMALLINT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`tournament_id`, `team_id`),
                CONSTRAINT `fk_{p}ss_tour` FOREIGN KEY (`tournament_id`) REFERENCES `{p}sports_tournaments` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}ss_team` FOREIGN KEY (`team_id`) REFERENCES `{p}sports_teams` (`id`) ON DELETE CASCADE
            ) $o",
        ] as $sql) {
            $db->query($sql);
        }

        // कुछ जानी-मानी पार्टियाँ (बदल/हटा सकते हैं)
        if (!(int) $db->value('SELECT COUNT(*) FROM {p}election_parties')) {
            foreach ([['भारतीय जनता पार्टी', 'BJP', 'bjp', '#f47216', 'NDA'], ['भारतीय राष्ट्रीय कांग्रेस', 'INC', 'inc', '#19aaed', 'INDIA'],
                ['समाजवादी पार्टी', 'SP', 'sp', '#e0262c', 'INDIA'], ['बहुजन समाज पार्टी', 'BSP', 'bsp', '#22409a', null],
                ['आम आदमी पार्टी', 'AAP', 'aap', '#0066a4', 'INDIA'], ['राष्ट्रीय लोक दल', 'RLD', 'rld', '#0a7d3b', 'NDA'],
                ['निर्दलीय', 'IND', 'ind', '#8a8a8a', null]] as $i => [$n, $s, $sl, $c, $a]) {
                $db->insert('election_parties', ['name' => $n, 'short_name' => $s, 'slug' => $sl, 'color' => $c, 'alliance' => $a, 'sort_order' => $i]);
            }
        }

        // पहले से इंस्टॉल साइट: Editor रोल को चुनाव/खेल डेस्क (मतगणना, लाइव स्कोर); हटाना/प्रबंधन Admin के पास
        $editor = (int) $db->value("SELECT id FROM {p}roles WHERE slug = 'editor'");
        if ($editor) {
            foreach (['elections.view', 'elections.create', 'elections.edit', 'sports.view', 'sports.create', 'sports.edit'] as $perm) {
                $pid = (int) $db->value('SELECT id FROM {p}permissions WHERE name = ?', [$perm]);
                if ($pid && !$db->value('SELECT 1 FROM {p}role_permissions WHERE role_id = ? AND permission_id = ?', [$editor, $pid])) {
                    $db->insert('role_permissions', ['role_id' => $editor, 'permission_id' => $pid]);
                }
            }
        }

        // फ़ुटर "उपयोगी लिंक" में नए पेज
        $menuId = (int) $db->value("SELECT id FROM {p}menus WHERE location = 'footer_4'");
        if ($menuId) {
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            foreach (['फ़ैक्ट चेक' => 'fact-check', 'चुनाव' => 'elections', 'खेल स्कोर' => 'sports', 'लोकल न्यूज़' => 'local', 'ट्रेंडिंग' => 'trending'] as $title => $u) {
                if (!$db->value("SELECT id FROM {p}menu_items WHERE menu_id = ? AND type = 'custom' AND url = ?", [$menuId, $u])) {
                    $db->insert('menu_items', ['menu_id' => $menuId, 'title' => $title, 'type' => 'custom', 'url' => $u, 'sort_order' => ++$order]);
                }
            }
            foreach (glob(BASE_PATH . '/storage/cache/menus/*.cache') ?: [] as $f) {
                @unlink($f);
            }
        }
    }
};
