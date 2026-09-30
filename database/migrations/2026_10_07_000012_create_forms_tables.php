<?php
/**
 * Phase 12: फ़ॉर्म बिल्डर, जमा फ़ॉर्म (संपर्क, न्यूज़ टिप, शिकायत, नौकरी आवेदन), नोट्स, नौकरियाँ
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $o = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS `{p}forms` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'custom',
                `description` TEXT NULL,
                `success_message` VARCHAR(500) NULL,
                `submit_label` VARCHAR(60) NULL,
                `notify_emails` VARCHAR(500) NULL,
                `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
                `is_system` TINYINT(1) NOT NULL DEFAULT 0,
                `settings` TEXT NULL,
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_form_slug` (`slug`),
                KEY `idx_form_type` (`type`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}form_fields` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` INT UNSIGNED NOT NULL,
                `field_key` VARCHAR(60) NOT NULL,
                `label` VARCHAR(190) NOT NULL,
                `type` VARCHAR(20) NOT NULL DEFAULT 'text',
                `options` TEXT NULL,
                `required` TINYINT(1) NOT NULL DEFAULT 0,
                `help` VARCHAR(300) NULL,
                `placeholder` VARCHAR(190) NULL,
                `width` TINYINT UNSIGNED NOT NULL DEFAULT 12,
                `accept` VARCHAR(60) NULL,
                `max_mb` TINYINT UNSIGNED NULL,
                `sort_order` SMALLINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ff_key` (`form_id`, `field_key`),
                KEY `idx_ff_sort` (`form_id`, `sort_order`),
                CONSTRAINT `fk_{p}ff_form` FOREIGN KEY (`form_id`) REFERENCES `{p}forms` (`id`) ON DELETE CASCADE
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}jobs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(190) NOT NULL,
                `slug` VARCHAR(190) NOT NULL,
                `department` VARCHAR(120) NULL,
                `location` VARCHAR(120) NULL,
                `job_type` ENUM('full_time','part_time','internship','freelance','contract') NOT NULL DEFAULT 'full_time',
                `description` MEDIUMTEXT NULL,
                `requirements` TEXT NULL,
                `salary` VARCHAR(120) NULL,
                `vacancies` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `deadline` DATE NULL,
                `status` ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
                `created_by` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_job_slug` (`slug`),
                KEY `idx_job_status` (`status`, `deadline`)
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}form_submissions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `form_id` INT UNSIGNED NOT NULL,
                `ref_no` VARCHAR(30) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'new',
                `data` MEDIUMTEXT NOT NULL,
                `files` TEXT NULL,
                `name` VARCHAR(150) NULL,
                `email` VARCHAR(190) NULL,
                `mobile` VARCHAR(20) NULL,
                `job_id` INT UNSIGNED NULL,
                `assigned_to` INT UNSIGNED NULL,
                `response` TEXT NULL,
                `resolution` VARCHAR(500) NULL,
                `converted` VARCHAR(40) NULL,
                `ip_hash` CHAR(64) NULL,
                `user_agent` VARCHAR(255) NULL,
                `page_url` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_fs_ref` (`ref_no`),
                KEY `idx_fs_form` (`form_id`, `status`, `created_at`),
                KEY `idx_fs_job` (`job_id`),
                CONSTRAINT `fk_{p}fs_form` FOREIGN KEY (`form_id`) REFERENCES `{p}forms` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}fs_job` FOREIGN KEY (`job_id`) REFERENCES `{p}jobs` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_{p}fs_user` FOREIGN KEY (`assigned_to`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $o",
            "CREATE TABLE IF NOT EXISTS `{p}form_notes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `submission_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL,
                `type` ENUM('note','reply','status','system') NOT NULL DEFAULT 'note',
                `body` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_fn_sub` (`submission_id`, `created_at`),
                CONSTRAINT `fk_{p}fn_sub` FOREIGN KEY (`submission_id`) REFERENCES `{p}form_submissions` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_{p}fn_user` FOREIGN KEY (`user_id`) REFERENCES `{p}users` (`id`) ON DELETE SET NULL
            ) $o",
        ] as $sql) {
            $db->query($sql);
        }

        $consent = ['consent', 'मैं सहमति देता/देती हूँ कि यह जानकारी सही है और इसे ऊपर बताए काम के लिए इस्तेमाल किया जा सकता है।', 'consent', 1];
        // [key, लेबल, प्रकार, ज़रूरी, विकल्प, मदद, चौड़ाई, accept, MB]
        $forms = [
            ['contact', 'संपर्क करें', 'contact', 'कोई सवाल, सुझाव या जानकारी? हमें लिखें, हम जल्द जवाब देंगे।', 'धन्यवाद! आपका संदेश मिल गया। हम जल्द जवाब देंगे।', 'संदेश भेजें', [
                ['name', 'आपका नाम', 'text', 1, null, null, 6], ['email', 'ईमेल', 'email', 1, null, null, 6], ['mobile', 'मोबाइल', 'mobile', 0, null, null, 6],
                ['subject', 'विषय', 'select', 1, "सामान्य जानकारी\nख़बर से जुड़ा सुझाव\nसुधार का अनुरोध\nतकनीकी समस्या\nअन्य", null, 6],
                ['message', 'संदेश', 'textarea', 1], $consent]],
            ['news-tip', 'हमें ख़बर भेजें', 'news_tip', 'आपके आसपास कोई ख़बर है? फ़ोटो, वीडियो या दस्तावेज़ के साथ भेजें। हमारी टीम जाँचकर प्रकाशित कर सकती है। आपकी पहचान गोपनीय रखी जा सकती है।', 'धन्यवाद! आपकी ख़बर मिल गई। हमारी टीम इसे जाँचेगी।', 'ख़बर भेजें', [
                ['name', 'आपका नाम', 'text', 1, null, null, 6], ['mobile', 'मोबाइल', 'mobile', 1, null, 'ज़रूरत पड़ने पर संपर्क के लिए; सार्वजनिक नहीं होगा', 6],
                ['email', 'ईमेल (वैकल्पिक)', 'email', 0, null, null, 6], ['location', 'जगह (शहर/गाँव/इलाक़ा)', 'text', 1, null, null, 6],
                ['description', 'ख़बर क्या है? (कब, कहाँ, क्या हुआ)', 'textarea', 1],
                ['photo', 'फ़ोटो', 'file', 0, null, 'JPG/PNG, 5 MB तक', 4, 'image', 5], ['video', 'वीडियो', 'file', 0, null, 'MP4, 50 MB तक', 4, 'video', 50],
                ['document', 'दस्तावेज़', 'file', 0, null, 'PDF, 10 MB तक', 4, 'document', 10],
                ['anonymous', 'मेरा नाम प्रकाशित न करें', 'checkbox', 0, 'हाँ, मेरी पहचान गोपनीय रखें'],
                ['consent', 'मैं पुष्टि करता/करती हूँ कि यह सामग्री मेरी है या मुझे इसे भेजने का अधिकार है, और वेबसाइट इसे प्रकाशित/संपादित कर सकती है।', 'consent', 1]]],
            ['complaint', 'शिकायत / ग्रीवेंस दर्ज करें', 'complaint', 'किसी ख़बर, रिपोर्टर या सामग्री से जुड़ी शिकायत दर्ज करें। शिकायत नंबर से आप स्थिति देख सकेंगे।', 'आपकी शिकायत दर्ज हो गई। शिकायत नंबर संभालकर रखें; इससे स्थिति देख सकते हैं।', 'शिकायत दर्ज करें', [
                ['name', 'आवेदक का नाम', 'text', 1, null, null, 6], ['email', 'ईमेल', 'email', 1, null, null, 6], ['mobile', 'मोबाइल', 'mobile', 1, null, null, 6],
                ['complaint_type', 'शिकायत का प्रकार', 'select', 1, "ग़लत या भ्रामक जानकारी\nमानहानि\nनिजता का उल्लंघन\nकॉपीराइट\nरिपोर्टर का व्यवहार\nविज्ञापन\nअन्य", null, 6],
                ['article_url', 'संबंधित ख़बर का लिंक (हो तो)', 'url', 0, null, null, 12],
                ['description', 'शिकायत का विवरण', 'textarea', 1], ['evidence', 'सबूत (वैकल्पिक)', 'file', 0, null, 'JPG/PNG/PDF, 10 MB तक', 12, 'image,document', 10], $consent]],
            ['job-application', 'नौकरी / इंटर्नशिप आवेदन', 'career', null, 'आपका आवेदन मिल गया। चुने जाने पर हम आपसे संपर्क करेंगे।', 'आवेदन भेजें', [
                ['name', 'पूरा नाम', 'text', 1, null, null, 6], ['email', 'ईमेल', 'email', 1, null, null, 6], ['mobile', 'मोबाइल', 'mobile', 1, null, null, 6],
                ['city', 'वर्तमान शहर', 'text', 1, null, null, 6], ['experience', 'अनुभव', 'select', 1, "फ़्रेशर\n1 साल से कम\n1-3 साल\n3-5 साल\n5 साल से ज़्यादा", null, 6],
                ['portfolio', 'पोर्टफ़ोलियो / प्रकाशित काम का लिंक', 'url', 0, null, null, 6], ['cover', 'आप क्यों जुड़ना चाहते हैं?', 'textarea', 0],
                ['cv', 'CV / बायोडाटा', 'file', 1, null, 'PDF/DOC/DOCX, 5 MB तक', 12, 'cv', 5], $consent]],
            ['advertise', 'विज्ञापन के लिए संपर्क', 'contact', 'वेबसाइट, ई-पेपर या सोशल मीडिया पर विज्ञापन के लिए जानकारी भेजें।', 'धन्यवाद! हमारी विज्ञापन टीम जल्द संपर्क करेगी।', 'भेजें', [
                ['name', 'नाम', 'text', 1, null, null, 6], ['company', 'कंपनी / संस्था', 'text', 0, null, null, 6], ['email', 'ईमेल', 'email', 1, null, null, 6], ['mobile', 'मोबाइल', 'mobile', 1, null, null, 6],
                ['budget', 'अनुमानित बजट', 'select', 0, "₹10,000 से कम\n₹10,000 - ₹50,000\n₹50,000 - ₹2 लाख\n₹2 लाख से ज़्यादा", null, 6],
                ['interest', 'किसमें रुचि', 'checkbox', 0, "वेबसाइट बैनर\nई-पेपर\nवीडियो\nप्रायोजित ख़बर\nन्यूज़लेटर", null, 6], ['message', 'संदेश', 'textarea', 0]]],
            ['feedback', 'फ़ीडबैक', 'contact', 'हमारी वेबसाइट और ख़बरों के बारे में अपनी राय दें।', 'आपकी राय के लिए धन्यवाद!', 'भेजें', [
                ['name', 'नाम', 'text', 0, null, null, 6], ['email', 'ईमेल', 'email', 0, null, null, 6],
                ['rating', 'कुल मिलाकर अनुभव', 'radio', 1, "बहुत अच्छा\nअच्छा\nठीक-ठाक\nख़राब"], ['message', 'सुझाव', 'textarea', 0]]],
        ];
        foreach ($forms as [$slug, $title, $type, $desc, $ok, $btn, $fields]) {
            if ($db->value('SELECT id FROM {p}forms WHERE slug = ?', [$slug])) {
                continue;
            }
            $fid = $db->insert('forms', ['title' => $title, 'slug' => $slug, 'type' => $type, 'description' => $desc, 'success_message' => $ok, 'submit_label' => $btn, 'is_system' => 1, 'status' => 'active']);
            foreach ($fields as $i => $f) {
                $db->insert('form_fields', ['form_id' => $fid, 'field_key' => $f[0], 'label' => $f[1], 'type' => $f[2], 'required' => $f[3], 'options' => $f[4] ?? null, 'help' => $f[5] ?? null,
                    'width' => $f[6] ?? 12, 'accept' => $f[7] ?? null, 'max_mb' => $f[8] ?? null, 'sort_order' => $i]);
            }
        }

        // "संपर्क करें" पेज में फ़ॉर्म
        $page = $db->first("SELECT id, content FROM {p}pages WHERE slug = 'contact-us'");
        if ($page && !str_contains((string) $page['content'], '[form:')) {
            $db->update('pages', ['content' => $page['content'] . '<p>[form:contact]</p>'], 'id = ?', [$page['id']]);
        }
        // फ़ुटर "उपयोगी लिंक"
        $menuId = (int) $db->value("SELECT id FROM {p}menus WHERE location = 'footer_4'");
        if ($menuId) {
            $order = (int) $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}menu_items WHERE menu_id = ?', [$menuId]);
            foreach (['हमें ख़बर भेजें' => 'send-news', 'शिकायत दर्ज करें' => 'complaint', 'करियर' => 'careers'] as $title => $u) {
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
