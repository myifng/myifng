<?php
/**
 * Phase 3: श्रेणी, टॉपिक, टैग, लोकेशन, मीडिया लाइब्रेरी
 * टेबल: categories, topics, tags, locations, media_folders, media + डिफ़ॉल्ट डेटा (दोबारा चलने पर दोहराव नहीं)
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `parent_id` INT UNSIGNED NULL,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(140) NOT NULL,
                `description` VARCHAR(500) NULL,
                `icon` VARCHAR(60) NULL,
                `image` VARCHAR(255) NULL,
                `color` VARCHAR(7) NULL,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `show_in_menu` TINYINT(1) NOT NULL DEFAULT 1,
                `show_on_home` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `language_id` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_categories_slug` (`slug`),
                KEY `idx_categories_parent` (`parent_id`, `sort_order`),
                CONSTRAINT `fk_{p}categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `{p}categories` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_{p}categories_lang` FOREIGN KEY (`language_id`) REFERENCES `{p}languages` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}topics` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` ENUM('topic','special') NOT NULL DEFAULT 'topic',
                `name` VARCHAR(150) NOT NULL,
                `slug` VARCHAR(170) NOT NULL,
                `description` VARCHAR(1000) NULL,
                `image` VARCHAR(255) NULL,
                `banner` VARCHAR(255) NULL,
                `color` VARCHAR(7) NULL,
                `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_topics_slug` (`slug`),
                KEY `idx_topics_type` (`type`, `status`, `sort_order`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}tags` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `description` VARCHAR(500) NULL,
                `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_tags_slug` (`slug`),
                KEY `idx_tags_name` (`name`)
            ) $opts",

            // path: URL वाला पता (uttar-pradesh/maharajganj); देश और मंडल का path NULL (वे URL में नहीं)
            "CREATE TABLE IF NOT EXISTS `{p}locations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `parent_id` INT UNSIGNED NULL,
                `type` ENUM('country','state','division','district','tehsil','block','city','locality') NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `name_en` VARCHAR(120) NULL,
                `slug` VARCHAR(120) NOT NULL,
                `path` VARCHAR(400) NULL,
                `code` VARCHAR(20) NULL,
                `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
                `show_in_menu` TINYINT(1) NOT NULL DEFAULT 0,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `sort_order` INT NOT NULL DEFAULT 0,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_locations_path` (`path`),
                KEY `idx_locations_parent` (`parent_id`, `sort_order`),
                KEY `idx_locations_type` (`type`, `status`),
                KEY `idx_locations_name` (`name`),
                CONSTRAINT `fk_{p}locations_parent` FOREIGN KEY (`parent_id`) REFERENCES `{p}locations` (`id`) ON DELETE RESTRICT
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}media_folders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `parent_id` INT UNSIGNED NULL,
                `name` VARCHAR(100) NOT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_mf_parent` (`parent_id`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}media` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `folder_id` INT UNSIGNED NULL,
                `file` VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) NULL,
                `mime` VARCHAR(100) NOT NULL,
                `kind` ENUM('image','video','audio','document') NOT NULL,
                `size` INT UNSIGNED NOT NULL DEFAULT 0,
                `width` INT UNSIGNED NULL,
                `height` INT UNSIGNED NULL,
                `variants` TEXT NULL,
                `title` VARCHAR(190) NULL,
                `alt` VARCHAR(255) NULL,
                `caption` VARCHAR(500) NULL,
                `credit` VARCHAR(150) NULL,
                `keywords` VARCHAR(255) NULL,
                `uploaded_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `deleted_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_media_file` (`file`),
                KEY `idx_media_list` (`deleted_at`, `kind`, `created_at`),
                KEY `idx_media_folder` (`folder_id`),
                CONSTRAINT `fk_{p}media_folder` FOREIGN KEY (`folder_id`) REFERENCES `{p}media_folders` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}media_user` FOREIGN KEY (`uploaded_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        $this->seedCategories($db);
        $this->seedLocations($db);
        $this->seedMenus($db);
        $this->seedSettings($db);
    }

    /** 14 मुख्य श्रेणियाँ, कुछ उप-श्रेणियों के साथ */
    private function seedCategories(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}categories') > 0) {
            return;
        }
        $lang = $db->value("SELECT id FROM {p}languages WHERE code = 'hi'");
        $cats = [
            ['देश', 'desh', 'fa-flag', '#d71920', 1, []],
            ['प्रदेश', 'pradesh', 'fa-map', '#0f766e', 1, []],
            ['दुनिया', 'world', 'fa-earth-asia', '#1d4ed8', 1, []],
            ['राजनीति', 'politics', 'fa-landmark', '#7c3aed', 1, []],
            ['खेल', 'sports', 'fa-futbol', '#16a34a', 1, [['क्रिकेट', 'cricket'], ['फ़ुटबॉल', 'football'], ['अन्य खेल', 'other-sports']]],
            ['मनोरंजन', 'entertainment', 'fa-film', '#db2777', 1, [['बॉलीवुड', 'bollywood'], ['टीवी', 'tv'], ['OTT', 'ott']]],
            ['बिज़नेस', 'business', 'fa-chart-line', '#0369a1', 1, [['शेयर बाज़ार', 'share-market'], ['पर्सनल फ़ाइनेंस', 'personal-finance']]],
            ['टेक्नोलॉजी', 'technology', 'fa-microchip', '#4f46e5', 1, [['मोबाइल', 'mobile'], ['गैजेट', 'gadgets']]],
            ['शिक्षा', 'education', 'fa-graduation-cap', '#b45309', 0, [['नौकरी', 'jobs'], ['परीक्षा परिणाम', 'results']]],
            ['स्वास्थ्य', 'health', 'fa-heart-pulse', '#dc2626', 0, []],
            ['धर्म', 'religion', 'fa-om', '#ea580c', 0, [['राशिफल', 'horoscope']]],
            ['अपराध', 'crime', 'fa-handcuffs', '#374151', 0, []],
            ['लाइफ़स्टाइल', 'lifestyle', 'fa-spa', '#0d9488', 0, [['खान-पान', 'food'], ['यात्रा', 'travel']]],
            ['कृषि', 'agriculture', 'fa-wheat-awn', '#65a30d', 0, []],
        ];
        foreach ($cats as $i => [$name, $slug, $icon, $color, $home, $children]) {
            $id = $db->insert('categories', [
                'name' => $name, 'slug' => $slug, 'icon' => $icon, 'color' => $color, 'show_on_home' => $home,
                'show_in_menu' => $i < 10 ? 1 : 0, 'sort_order' => $i + 1, 'language_id' => $lang, 'meta_title' => $name . ' समाचार',
            ]);
            foreach ($children as $j => [$cn, $cs]) {
                $db->insert('categories', [
                    'parent_id' => $id, 'name' => $cn, 'slug' => $cs, 'color' => $color, 'show_in_menu' => 1,
                    'sort_order' => $j + 1, 'language_id' => $lang, 'meta_title' => $cn . ' समाचार',
                ]);
            }
        }
    }

    /** भारत → 28 राज्य + 8 केंद्र शासित प्रदेश → उत्तर प्रदेश के 75 ज़िले */
    private function seedLocations(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}locations') > 0) {
            return;
        }
        $india = $db->insert('locations', ['type' => 'country', 'name' => 'भारत', 'name_en' => 'India', 'slug' => 'india', 'path' => null, 'code' => 'IN']);
        $states = [
            ['उत्तर प्रदेश', 'Uttar Pradesh', 'UP', 1], ['बिहार', 'Bihar', 'BR', 1], ['दिल्ली', 'Delhi', 'DL', 1], ['मध्य प्रदेश', 'Madhya Pradesh', 'MP', 1],
            ['राजस्थान', 'Rajasthan', 'RJ', 1], ['उत्तराखंड', 'Uttarakhand', 'UK', 1], ['हरियाणा', 'Haryana', 'HR', 0], ['पंजाब', 'Punjab', 'PB', 0],
            ['झारखंड', 'Jharkhand', 'JH', 0], ['छत्तीसगढ़', 'Chhattisgarh', 'CG', 0], ['महाराष्ट्र', 'Maharashtra', 'MH', 0], ['गुजरात', 'Gujarat', 'GJ', 0],
            ['हिमाचल प्रदेश', 'Himachal Pradesh', 'HP', 0], ['पश्चिम बंगाल', 'West Bengal', 'WB', 0], ['ओडिशा', 'Odisha', 'OD', 0], ['असम', 'Assam', 'AS', 0],
            ['आंध्र प्रदेश', 'Andhra Pradesh', 'AP', 0], ['तेलंगाना', 'Telangana', 'TS', 0], ['कर्नाटक', 'Karnataka', 'KA', 0], ['तमिलनाडु', 'Tamil Nadu', 'TN', 0],
            ['केरल', 'Kerala', 'KL', 0], ['गोवा', 'Goa', 'GA', 0], ['अरुणाचल प्रदेश', 'Arunachal Pradesh', 'AR', 0], ['मणिपुर', 'Manipur', 'MN', 0],
            ['मेघालय', 'Meghalaya', 'ML', 0], ['मिज़ोरम', 'Mizoram', 'MZ', 0], ['नगालैंड', 'Nagaland', 'NL', 0], ['सिक्किम', 'Sikkim', 'SK', 0],
            ['त्रिपुरा', 'Tripura', 'TR', 0],
            // केंद्र शासित प्रदेश (URL में राज्य जैसे ही)
            ['जम्मू-कश्मीर', 'Jammu and Kashmir', 'JK', 0], ['लद्दाख', 'Ladakh', 'LA', 0], ['चंडीगढ़', 'Chandigarh', 'CH', 0], ['पुडुचेरी', 'Puducherry', 'PY', 0],
            ['अंडमान और निकोबार द्वीप समूह', 'Andaman and Nicobar Islands', 'AN', 0], ['लक्षद्वीप', 'Lakshadweep', 'LD', 0],
            ['दादरा और नगर हवेली और दमन और दीव', 'Dadra and Nagar Haveli and Daman and Diu', 'DH', 0],
        ];
        $slugify = static fn(string $en) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($en)), '-');
        $up = 0;
        foreach ($states as $i => [$hi, $en, $code, $popular]) {
            $slug = $slugify($en);
            $id = $db->insert('locations', [
                'parent_id' => $india, 'type' => 'state', 'name' => $hi, 'name_en' => $en, 'slug' => $slug, 'path' => $slug,
                'code' => $code, 'is_popular' => $popular, 'show_in_menu' => $popular, 'sort_order' => $i + 1, 'meta_title' => $hi . ' समाचार',
            ]);
            if ($code === 'UP') {
                $up = $id;
            }
        }

        $districts = [
            'आगरा' => 'Agra', 'अलीगढ़' => 'Aligarh', 'अंबेडकर नगर' => 'Ambedkar Nagar', 'अमेठी' => 'Amethi', 'अमरोहा' => 'Amroha', 'औरैया' => 'Auraiya',
            'अयोध्या' => 'Ayodhya', 'आज़मगढ़' => 'Azamgarh', 'बागपत' => 'Baghpat', 'बहराइच' => 'Bahraich', 'बलिया' => 'Ballia', 'बलरामपुर' => 'Balrampur',
            'बांदा' => 'Banda', 'बाराबंकी' => 'Barabanki', 'बरेली' => 'Bareilly', 'बस्ती' => 'Basti', 'भदोही' => 'Bhadohi', 'बिजनौर' => 'Bijnor',
            'बदायूं' => 'Budaun', 'बुलंदशहर' => 'Bulandshahr', 'चंदौली' => 'Chandauli', 'चित्रकूट' => 'Chitrakoot', 'देवरिया' => 'Deoria', 'एटा' => 'Etah',
            'इटावा' => 'Etawah', 'फर्रुखाबाद' => 'Farrukhabad', 'फतेहपुर' => 'Fatehpur', 'फ़िरोज़ाबाद' => 'Firozabad', 'गौतम बुद्ध नगर (नोएडा)' => 'Gautam Buddh Nagar',
            'ग़ाज़ियाबाद' => 'Ghaziabad', 'ग़ाज़ीपुर' => 'Ghazipur', 'गोंडा' => 'Gonda', 'गोरखपुर' => 'Gorakhpur', 'हमीरपुर' => 'Hamirpur', 'हापुड़' => 'Hapur',
            'हरदोई' => 'Hardoi', 'हाथरस' => 'Hathras', 'जालौन' => 'Jalaun', 'जौनपुर' => 'Jaunpur', 'झांसी' => 'Jhansi', 'कन्नौज' => 'Kannauj',
            'कानपुर देहात' => 'Kanpur Dehat', 'कानपुर नगर' => 'Kanpur Nagar', 'कासगंज' => 'Kasganj', 'कौशांबी' => 'Kaushambi', 'कुशीनगर' => 'Kushinagar',
            'लखीमपुर खीरी' => 'Lakhimpur Kheri', 'ललितपुर' => 'Lalitpur', 'लखनऊ' => 'Lucknow', 'महराजगंज' => 'Maharajganj', 'महोबा' => 'Mahoba',
            'मैनपुरी' => 'Mainpuri', 'मथुरा' => 'Mathura', 'मऊ' => 'Mau', 'मेरठ' => 'Meerut', 'मिर्ज़ापुर' => 'Mirzapur', 'मुरादाबाद' => 'Moradabad',
            'मुज़फ़्फ़रनगर' => 'Muzaffarnagar', 'पीलीभीत' => 'Pilibhit', 'प्रतापगढ़' => 'Pratapgarh', 'प्रयागराज' => 'Prayagraj', 'रायबरेली' => 'Raebareli',
            'रामपुर' => 'Rampur', 'सहारनपुर' => 'Saharanpur', 'संभल' => 'Sambhal', 'संत कबीर नगर' => 'Sant Kabir Nagar', 'शाहजहांपुर' => 'Shahjahanpur',
            'शामली' => 'Shamli', 'श्रावस्ती' => 'Shravasti', 'सिद्धार्थनगर' => 'Siddharthnagar', 'सीतापुर' => 'Sitapur', 'सोनभद्र' => 'Sonbhadra',
            'सुल्तानपुर' => 'Sultanpur', 'उन्नाव' => 'Unnao', 'वाराणसी' => 'Varanasi',
        ];
        $popular = ['Lucknow', 'Gorakhpur', 'Varanasi', 'Prayagraj', 'Kanpur Nagar', 'Agra', 'Meerut', 'Ghaziabad', 'Gautam Buddh Nagar', 'Maharajganj', 'Ayodhya', 'Bareilly'];
        $i = 0;
        foreach ($districts as $hi => $en) {
            $slug = $slugify($en);
            $db->insert('locations', [
                'parent_id' => $up, 'type' => 'district', 'name' => $hi, 'name_en' => $en, 'slug' => $slug, 'path' => 'uttar-pradesh/' . $slug,
                'is_popular' => in_array($en, $popular, true) ? 1 : 0, 'sort_order' => ++$i, 'meta_title' => $hi . ' समाचार',
            ]);
        }
    }

    /** मुख्य मेनू और फ़ुटर में श्रेणियाँ (सिर्फ़ तब, जब इनमें अभी श्रेणी का कोई लिंक न हो) */
    private function seedMenus(Database $db): void
    {
        $cats = $db->all('SELECT id, name FROM {p}categories WHERE parent_id IS NULL AND show_in_menu = 1 ORDER BY sort_order LIMIT 10');
        foreach (['main' => 10, 'footer_3' => 6, 'mobile' => 8] as $loc => $limit) {
            $menuId = (int) $db->value('SELECT id FROM {p}menus WHERE location = ?', [$loc]);
            if (!$menuId || (int) $db->value("SELECT COUNT(*) FROM {p}menu_items WHERE menu_id = ? AND type = 'category'", [$menuId]) > 0) {
                continue;
            }
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            foreach (array_slice($cats, 0, $limit) as $c) {
                $db->insert('menu_items', ['menu_id' => $menuId, 'title' => $c['name'], 'type' => 'category', 'reference_id' => $c['id'], 'sort_order' => ++$order]);
            }
        }
        // कैश में पुराना मेनू न रहे
        foreach (glob(BASE_PATH . '/storage/cache/menus/*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }

    /** मीडिया के शुरुआती फ़ोल्डर */
    private function seedSettings(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}media_folders') === 0) {
            $db->insert('media_folders', ['name' => 'ख़बरों की तस्वीरें']);
            $db->insert('media_folders', ['name' => 'श्रेणी और टॉपिक']);
        }
    }
};
