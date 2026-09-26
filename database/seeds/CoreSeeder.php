<?php
/**
 * ज़रूरी शुरुआती डेटा: भाषाएँ, रोल, अनुमतियाँ, डिफ़ॉल्ट सेटिंग।
 * इंस्टॉलर चलाता है। दोबारा चलाने पर मौजूद डेटा नहीं बिगड़ता।
 * $ctx: site_name, tagline, admin_email, timezone, language, primary_color, secondary_color, logo, site_url
 */
use App\Core\Database;
use App\Services\PermissionService;

return new class {
    public function run(Database $db, array $ctx): void
    {
        $base = dirname(__DIR__, 2);
        $registry = require $base . '/config/modules.php';
        $roles = require $base . '/config/roles.php';

        // भाषाएँ (बहुभाषी ढाँचा)
        foreach ([['hi', 'Hindi', 'हिंदी', 1, 1], ['en', 'English', 'English', 0, 2]] as [$code, $name, $native, $default, $order]) {
            if (!$db->value('SELECT id FROM {p}languages WHERE code = ?', [$code])) {
                $db->insert('languages', ['code' => $code, 'name' => $name, 'native_name' => $native, 'is_default' => $default, 'sort_order' => $order]);
            }
        }
        // नए इंस्टॉल में migration पहले चलती हैं, भाषाएँ बाद में बनती हैं: डिफ़ॉल्ट डेटा को भाषा से जोड़ें
        $lang = $db->value("SELECT id FROM {p}languages WHERE code = ?", [($ctx['language'] ?? 'hi') === 'en' ? 'en' : 'hi']);
        foreach (['pages', 'categories'] as $t) {
            if ($lang && $db->first('SHOW TABLES LIKE ' . $db->pdo()->quote($db->table($t)))) {
                $db->query("UPDATE {p}$t SET language_id = ? WHERE language_id IS NULL", [$lang]);
            }
        }

        // अनुमतियाँ रजिस्ट्री से
        PermissionService::sync($db, $registry['modules']);

        // system रोल और उनकी डिफ़ॉल्ट अनुमतियाँ
        foreach ($roles as $slug => $r) {
            $id = (int) $db->value('SELECT id FROM {p}roles WHERE slug = ?', [$slug]);
            if (!$id) {
                $id = $db->insert('roles', ['name' => $r['name'], 'slug' => $slug, 'description' => $r['description'], 'level' => $r['level'], 'is_system' => 1]);
                PermissionService::setRolePermissions($db, $id, PermissionService::resolve($db, $r['permissions']));
            }
        }

        // डिफ़ॉल्ट सेटिंग (white-label: हर ब्रांड जानकारी यहीं से)
        $defaults = [
            'general' => [
                'site_name' => $ctx['site_name'] ?? 'News Portal',
                'tagline' => $ctx['tagline'] ?? '',
                'site_description' => $ctx['tagline'] ?? '',
                'admin_email' => $ctx['admin_email'] ?? '',
                'contact_email' => $ctx['admin_email'] ?? '',
                'contact_phone' => '',
                'whatsapp' => '',
                'address' => '',
                'timezone' => $ctx['timezone'] ?? 'Asia/Kolkata',
                'language' => $ctx['language'] ?? 'hi',
                'date_format' => 'd M Y',
                'maintenance_mode' => '0',
                'maintenance_message' => '',
            ],
            'branding' => [
                'logo' => $ctx['logo'] ?? '',
                'logo_dark' => '',
                'logo_mobile' => '',
                'favicon' => $ctx['favicon'] ?? '',
                'primary_color' => $ctx['primary_color'] ?? '#d71920',
                'secondary_color' => $ctx['secondary_color'] ?? '#15161a',
                'font_heading' => 'Mukta',
                'font_body' => 'Noto Sans Devanagari',
            ],
            'security' => [
                'session_timeout' => '120',
                'login_max_attempts' => '5',
                'login_lockout_minutes' => '15',
            ],
            'mail' => [
                'mail_from_email' => $ctx['admin_email'] ?? '',
                'mail_from_name' => $ctx['site_name'] ?? '',
            ],
        ];
        foreach ($defaults as $group => $values) {
            foreach ($values as $name => $value) {
                $db->query('INSERT IGNORE INTO {p}settings (name, value, group_name) VALUES (?, ?, ?)', [$name, (string) $value, $group]);
            }
        }
    }
};
