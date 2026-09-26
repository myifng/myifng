<?php
/**
 * Phase 2: पेज CMS, मेनू बिल्डर, होमपेज बिल्डर
 * टेबल: pages, menus, menu_items, home_sections + डिफ़ॉल्ट डेटा (दोबारा चलने पर दोहराव नहीं)
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}pages` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(190) NOT NULL,
                `excerpt` VARCHAR(500) NULL,
                `content` MEDIUMTEXT NULL,
                `featured_image` VARCHAR(255) NULL,
                `template` VARCHAR(40) NOT NULL DEFAULT 'default',
                `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
                `show_header` TINYINT(1) NOT NULL DEFAULT 1,
                `show_footer` TINYINT(1) NOT NULL DEFAULT 1,
                `meta_title` VARCHAR(190) NULL,
                `meta_description` VARCHAR(320) NULL,
                `meta_keywords` VARCHAR(255) NULL,
                `og_image` VARCHAR(255) NULL,
                `robots` VARCHAR(40) NOT NULL DEFAULT 'index,follow',
                `language_id` INT UNSIGNED NULL,
                `published_at` DATETIME NULL,
                `created_by` INT UNSIGNED NULL,
                `updated_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `deleted_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pages_slug` (`slug`),
                KEY `idx_pages_status` (`status`, `deleted_at`),
                CONSTRAINT `fk_{p}pages_lang` FOREIGN KEY (`language_id`) REFERENCES `{p}languages` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}pages_creator` FOREIGN KEY (`created_by`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}menus` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `location` VARCHAR(40) NOT NULL,
                `description` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_menus_location` (`location`)
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}menu_items` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `menu_id` INT UNSIGNED NOT NULL,
                `parent_id` INT UNSIGNED NULL,
                `title` VARCHAR(120) NOT NULL,
                `type` VARCHAR(30) NOT NULL DEFAULT 'custom',
                `reference_id` INT UNSIGNED NULL,
                `url` VARCHAR(500) NULL,
                `target_blank` TINYINT(1) NOT NULL DEFAULT 0,
                `icon` VARCHAR(60) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_mi_menu` (`menu_id`, `parent_id`, `sort_order`),
                CONSTRAINT `fk_{p}mi_menu` FOREIGN KEY (`menu_id`) REFERENCES `{p}menus` (`id`) ON DELETE CASCADE
            ) $opts",

            "CREATE TABLE IF NOT EXISTS `{p}home_sections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `block_type` VARCHAR(40) NOT NULL,
                `title` VARCHAR(150) NULL,
                `settings` TEXT NULL,
                `show_desktop` TINYINT(1) NOT NULL DEFAULT 1,
                `show_mobile` TINYINT(1) NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hs_order` (`is_active`, `sort_order`)
            ) $opts",
        ] as $sql) {
            $db->pdo()->exec($db->sql($sql));
        }

        $this->seedPages($db);
        $this->seedMenus($db);
        $this->seedHomepage($db);
    }

    /** 12 डिफ़ॉल्ट पेज; [site_name] आदि shortcode दिखाते समय सेटिंग से बदलते हैं */
    private function seedPages(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}pages') > 0) {
            return;
        }
        $lang = $db->value("SELECT id FROM {p}languages WHERE code = 'hi'");
        $p = fn(string ...$lines) => '<p>' . implode('</p><p>', $lines) . '</p>';
        $pages = [
            ['about-us', 'हमारे बारे में', 'default', $p('[site_name] एक डिजिटल न्यूज़ प्लेटफ़ॉर्म है, जो देश, प्रदेश और ज़िलों की ख़बरें तेज़, सटीक और निष्पक्ष रूप से पाठकों तक पहुँचाता है।', 'हमारी टीम में अनुभवी संपादक और ज़मीनी संवाददाता हैं। हमारा लक्ष्य है कि हर ख़बर तथ्यों की जाँच के बाद ही प्रकाशित हो।') . '<h2>हमारा उद्देश्य</h2>' . $p('जनहित की ख़बरें, स्थानीय मुद्दे और विश्वसनीय जानकारी हर पाठक तक पहुँचाना।')],
            ['contact-us', 'संपर्क करें', 'contact', $p('किसी ख़बर, सुझाव, सुधार या विज्ञापन के लिए हमसे संपर्क करें।', 'ईमेल: [contact_email]', 'फ़ोन: [contact_phone]', 'पता: [address]')],
            ['disclaimer', 'अस्वीकरण (Disclaimer)', 'default', $p('[site_name] पर प्रकाशित जानकारी सामान्य जानकारी के उद्देश्य से है। हम सटीकता का पूरा प्रयास करते हैं, फिर भी किसी त्रुटि के लिए पाठक आधिकारिक स्रोत से पुष्टि करें।', 'बाहरी वेबसाइटों के लिंक की सामग्री के लिए [site_name] ज़िम्मेदार नहीं है।')],
            ['terms-and-conditions', 'नियम और शर्तें', 'default', $p('इस वेबसाइट का उपयोग करके आप इन नियमों को स्वीकार करते हैं।', 'वेबसाइट की सामग्री (लेख, तस्वीरें, वीडियो) [site_name] की संपत्ति है। बिना लिखित अनुमति के इसका व्यावसायिक उपयोग वर्जित है।')],
            ['privacy-policy', 'गोपनीयता नीति', 'default', $p('हम आपकी निजी जानकारी की सुरक्षा को गंभीरता से लेते हैं।', 'न्यूज़लेटर, टिप्पणी या फ़ॉर्म में दी गई जानकारी सिर्फ़ उसी उद्देश्य के लिए इस्तेमाल होती है और किसी तीसरे पक्ष को बेची नहीं जाती।', 'वेबसाइट बेहतर बनाने के लिए कुकीज़ और एनालिटिक्स का उपयोग हो सकता है।')],
            ['editorial-policy', 'संपादकीय नीति', 'default', $p('[site_name] निष्पक्ष, तथ्य-आधारित और ज़िम्मेदार पत्रकारिता के लिए प्रतिबद्ध है।', 'हर ख़बर कम से कम एक संपादक की समीक्षा के बाद प्रकाशित होती है। विज्ञापन और संपादकीय सामग्री को स्पष्ट रूप से अलग रखा जाता है।')],
            ['correction-policy', 'सुधार नीति', 'default', $p('ग़लती होने पर हम उसे जल्द से जल्द सुधारते हैं और ज़रूरत होने पर ख़बर के नीचे सुधार की सूचना देते हैं।', 'किसी त्रुटि की जानकारी [contact_email] पर भेजें।')],
            ['code-of-ethics', 'आचार संहिता', 'default', $p('सच्चाई, निष्पक्षता, स्वतंत्रता और जवाबदेही हमारी पत्रकारिता के मूल सिद्धांत हैं।', 'हम पीड़ितों और नाबालिगों की पहचान की रक्षा करते हैं और किसी भी दबाव में ख़बर नहीं बदलते।')],
            ['grievance-policy', 'शिकायत निवारण नीति', 'default', $p('किसी सामग्री से जुड़ी शिकायत [contact_email] पर भेजें। शिकायत मिलने के 15 दिनों के भीतर हम जवाब देने का प्रयास करते हैं।', 'शिकायत अधिकारी का विवरण इसी पेज पर अपडेट किया जाएगा।')],
            ['reporter-policy', 'रिपोर्टर नीति', 'default', $p('[site_name] से जुड़े सभी संवाददाता संपादकीय नीति और आचार संहिता का पालन करते हैं।', 'रिपोर्टर का पहचान पत्र वेबसाइट पर सत्यापित किया जा सकता है। किसी भी दुरुपयोग की सूचना तुरंत [contact_email] पर दें।')],
            ['advertise-with-us', 'विज्ञापन दें', 'contact', $p('[site_name] पर अपने व्यवसाय का विज्ञापन देकर लाखों पाठकों तक पहुँचें।', 'बैनर, प्रायोजित सामग्री और वीडियो विज्ञापन के लिए संपर्क करें: [contact_email], [contact_phone]')],
            ['careers', 'करियर', 'default', $p('[site_name] में पत्रकारिता, वीडियो, डिज़ाइन और तकनीक से जुड़े अवसरों के लिए अपना बायोडाटा [contact_email] पर भेजें।')],
        ];
        foreach ($pages as $i => [$slug, $title, $tpl, $html]) {
            $db->insert('pages', [
                'title' => $title, 'slug' => $slug, 'content' => $html, 'template' => $tpl, 'status' => 'published',
                'language_id' => $lang, 'published_at' => date('Y-m-d H:i:s'), 'meta_title' => $title,
            ]);
        }
    }

    private function seedMenus(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}menus') > 0) {
            return;
        }
        $page = fn(string $slug) => (int) $db->value('SELECT id FROM {p}pages WHERE slug = ?', [$slug]);
        $menus = [
            'top' => ['टॉप बार मेनू', [['ई-पेपर', 'epaper'], ['विज्ञापन दें', 'page', 'advertise-with-us'], ['संपर्क', 'page', 'contact-us']]],
            'main' => ['मुख्य नेविगेशन', [['होम', 'home']]],
            'mobile' => ['मोबाइल मेनू', [['होम', 'home'], ['ई-पेपर', 'epaper'], ['लाइव टीवी', 'live_tv'], ['हमारे बारे में', 'page', 'about-us'], ['संपर्क', 'page', 'contact-us']]],
            'footer_1' => ['हमारे बारे में', [['हमारे बारे में', 'page', 'about-us'], ['संपर्क करें', 'page', 'contact-us'], ['विज्ञापन दें', 'page', 'advertise-with-us'], ['करियर', 'page', 'careers']]],
            'footer_2' => ['नीतियाँ', [['संपादकीय नीति', 'page', 'editorial-policy'], ['सुधार नीति', 'page', 'correction-policy'], ['आचार संहिता', 'page', 'code-of-ethics'], ['शिकायत निवारण', 'page', 'grievance-policy'], ['रिपोर्टर नीति', 'page', 'reporter-policy']]],
            'footer_3' => ['प्रमुख श्रेणियाँ', []],
            'footer_4' => ['उपयोगी लिंक', [['ई-पेपर', 'epaper'], ['लाइव टीवी', 'live_tv']]],
            'legal' => ['लीगल मेनू', [['गोपनीयता नीति', 'page', 'privacy-policy'], ['नियम और शर्तें', 'page', 'terms-and-conditions'], ['अस्वीकरण', 'page', 'disclaimer']]],
            'quick' => ['क्विक लिंक्स', [['ई-पेपर', 'epaper'], ['लाइव टीवी', 'live_tv'], ['संपर्क', 'page', 'contact-us']]],
        ];
        foreach ($menus as $loc => [$name, $items]) {
            $mid = $db->insert('menus', ['name' => $name, 'location' => $loc]);
            foreach ($items as $i => $it) {
                $db->insert('menu_items', [
                    'menu_id' => $mid, 'title' => $it[0], 'type' => $it[1],
                    'reference_id' => $it[1] === 'page' ? ($page($it[2]) ?: null) : null, 'sort_order' => $i + 1,
                ]);
            }
        }
    }

    private function seedHomepage(Database $db): void
    {
        if ((int) $db->value('SELECT COUNT(*) FROM {p}home_sections') > 0) {
            return;
        }
        $sections = [
            ['breaking', 'ब्रेकिंग न्यूज़', ['style' => 'ticker']],
            ['hero', 'मुख्य ख़बरें', ['layout' => 'hero-3col', 'count' => 5, 'source' => 'auto', 'filter' => 'featured']],
            ['latest', 'ताज़ा ख़बरें', ['layout' => 'list', 'count' => 10]],
            ['ads', 'विज्ञापन', ['slot' => 'home_top']],
            ['location', 'मेरा शहर / राज्य', ['layout' => 'tabs', 'count' => 6]],
            ['videos', 'वीडियो', ['layout' => 'dark-strip', 'count' => 5]],
            ['most_read', 'सबसे ज़्यादा पढ़ी गईं', ['count' => 5, 'period' => 7]],
            ['gallery', 'फ़ोटो गैलरी', ['count' => 4]],
            ['web_stories', 'वेब स्टोरी', ['count' => 8]],
            ['epaper', 'आज का ई-पेपर', []],
            ['newsletter', 'न्यूज़लेटर', []],
        ];
        foreach ($sections as $i => [$type, $title, $settings]) {
            $db->insert('home_sections', ['block_type' => $type, 'title' => $title, 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'sort_order' => $i + 1]);
        }
    }
};
