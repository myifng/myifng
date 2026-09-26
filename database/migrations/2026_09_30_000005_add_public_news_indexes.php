<?php
/**
 * Phase 5: वेबसाइट की क्वेरी के लिए इंडेक्स (सबसे ज़्यादा पढ़ी, ब्रेकिंग टिकर)
 * + होमपेज पर "होम पर दिखाएँ" वाली श्रेणियों के सेक्शन (सिर्फ़ तब, जब कोई श्रेणी सेक्शन पहले से न हो)
 */
use App\Core\Database;

return new class {
    public function up(Database $db): void
    {
        $indexes = [
            'idx_news_views' => '(`status`, `deleted_at`, `views`)',
            'idx_news_breaking' => '(`is_breaking`, `status`, `published_at`)',
        ];
        foreach ($indexes as $name => $cols) {
            $exists = $db->first('SHOW INDEX FROM `' . $db->table('news') . '` WHERE Key_name = ?', [$name]);
            if (!$exists) {
                $db->pdo()->exec('ALTER TABLE `' . $db->table('news') . "` ADD INDEX `$name` $cols");
            }
        }
        $this->seedCategorySections($db);
    }

    /** पहली श्रेणी बड़ी (1 बड़ी + सूची), बाकी दो-दो आधी चौड़ाई में; "मेरा शहर" सेक्शन के ठीक बाद */
    private function seedCategorySections(Database $db): void
    {
        if ((int) $db->value("SELECT COUNT(*) FROM {p}home_sections WHERE block_type = 'category'") > 0) {
            return;
        }
        $cats = $db->all("SELECT id, name FROM {p}categories WHERE parent_id IS NULL AND show_on_home = 1 AND status = 'active' ORDER BY sort_order LIMIT 9");
        if (!$cats) {
            return;
        }
        $after = (int) ($db->value("SELECT sort_order FROM {p}home_sections WHERE block_type = 'location' ORDER BY sort_order LIMIT 1")
            ?? $db->value('SELECT COALESCE(MAX(sort_order), 0) FROM {p}home_sections'));
        $db->query('UPDATE {p}home_sections SET sort_order = sort_order + ? WHERE sort_order > ?', [count($cats), $after]);
        foreach ($cats as $i => $c) {
            $settings = ['category' => (int) $c['id'], 'layout' => $i === 0 ? 'lead-list' : 'half', 'count' => 5, 'more' => 1];
            $db->insert('home_sections', ['block_type' => 'category', 'title' => $c['name'], 'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'sort_order' => $after + $i + 1]);
        }
        foreach (glob(BASE_PATH . '/storage/cache/home/*.cache') ?: [] as $f) {
            @unlink($f);
        }
    }
};
