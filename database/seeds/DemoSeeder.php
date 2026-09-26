<?php
/**
 * वैकल्पिक डेमो डेटा। Phase 1 में: हर रोल का एक डेमो यूज़र (सभी का पासवर्ड $ctx['demo_password'])।
 * आगे के phase इसमें डेमो ख़बरें, श्रेणियाँ, रिपोर्टर आदि जोड़ेंगे।
 */
use App\Core\Database;

return new class {
    public function run(Database $db, array $ctx): void
    {
        $pass = password_hash((string) ($ctx['demo_password'] ?? 'Demo@12345'), PASSWORD_DEFAULT);
        $users = [
            ['admin', 'डेमो एडमिन', 'admin.demo@example.com', '9876500001'],
            ['editor', 'रवि शर्मा (संपादक)', 'editor.demo@example.com', '9876500002'],
            ['reporter', 'अमित वर्मा (रिपोर्टर)', 'reporter.demo@example.com', '9876500003'],
            ['reporter', 'नेहा अग्रवाल (रिपोर्टर)', 'reporter2.demo@example.com', '9876500004'],
            ['employee', 'सुनील कुमार (कर्मचारी)', 'employee.demo@example.com', '9876500005'],
        ];
        foreach ($users as [$role, $name, $email, $mobile]) {
            $roleId = (int) $db->value('SELECT id FROM {p}roles WHERE slug = ?', [$role]);
            if ($roleId && !$db->value('SELECT id FROM {p}users WHERE email = ?', [$email])) {
                $db->insert('users', ['name' => $name, 'email' => $email, 'mobile' => $mobile, 'password' => $pass, 'role_id' => $roleId, 'bio' => 'डेमो खाता', 'created_by' => $ctx['admin_id'] ?? null]);
            }
        }
    }
};
