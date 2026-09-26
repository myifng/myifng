<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * अनुमतियों का प्रबंधन: मॉड्यूल रजिस्ट्री → permissions टेबल, रोल की अनुमतियाँ सेव करना।
 * इंस्टॉलर में भी चलता है, इसलिए सीधे Database लेता है।
 */
final class PermissionService
{
    public const ACTION_LABELS = [
        'view' => 'देखें', 'create' => 'बनाएँ', 'edit' => 'बदलें', 'delete' => 'हटाएँ',
        'approve' => 'मंज़ूर', 'publish' => 'प्रकाशित', 'export' => 'एक्सपोर्ट', 'manage' => 'प्रबंधन',
    ];

    /** रजिस्ट्री की हर अनुमति डेटाबेस में हो; लौटाता है नई जोड़ी गई संख्या */
    public static function sync(Database $db, array $modules): int
    {
        $existing = array_flip(array_column($db->all('SELECT name FROM {p}permissions'), 'name'));
        $added = 0;
        foreach ($modules as $key => $m) {
            foreach ($m['actions'] as $action) {
                $name = $key . '.' . $action;
                if (!isset($existing[$name])) {
                    $db->insert('permissions', ['module' => $key, 'action' => $action, 'name' => $name]);
                    $added++;
                }
            }
            $exists = $db->value('SELECT COUNT(*) FROM {p}modules WHERE module_key = ?', [$key]);
            if (!$exists) {
                $db->insert('modules', ['module_key' => $key, 'name' => $m['label'], 'enabled' => 1]);
            }
        }
        return $added;
    }

    /** पैटर्न ('*', 'news.*', 'news.view') को अनुमति IDs में बदलें */
    public static function resolve(Database $db, array $patterns): array
    {
        $all = $db->all('SELECT id, name, module FROM {p}permissions');
        $ids = [];
        foreach ($all as $p) {
            foreach ($patterns as $pat) {
                if ($pat === '*' || $pat === $p['name'] || ($pat === $p['module'] . '.*')) {
                    $ids[] = (int) $p['id'];
                    break;
                }
            }
        }
        return $ids;
    }

    /** रोल की अनुमतियाँ पूरी तरह बदलें */
    public static function setRolePermissions(Database $db, int $roleId, array $permissionIds): void
    {
        $db->delete('role_permissions', 'role_id = ?', [$roleId]);
        foreach (array_unique(array_map('intval', $permissionIds)) as $pid) {
            $db->insert('role_permissions', ['role_id' => $roleId, 'permission_id' => $pid]);
        }
    }

    /** मॉड्यूल-वार मैट्रिक्स: [module => ['label'=>..,'phase'=>..,'actions'=>[action=>permission_id]]] */
    public static function matrix(Database $db, array $registry): array
    {
        $ids = [];
        foreach ($db->all('SELECT id, name FROM {p}permissions') as $p) {
            $ids[$p['name']] = (int) $p['id'];
        }
        $out = [];
        foreach ($registry['modules'] as $key => $m) {
            $row = ['label' => $m['label'], 'icon' => $m['icon'], 'phase' => $m['phase'], 'group' => $m['group'], 'actions' => []];
            foreach ($m['actions'] as $a) {
                if (isset($ids["$key.$a"])) {
                    $row['actions'][$a] = $ids["$key.$a"];
                }
            }
            $out[$key] = $row;
        }
        return $out;
    }

    public static function rolePermissionIds(Database $db, int $roleId): array
    {
        return array_map('intval', array_column($db->all('SELECT permission_id FROM {p}role_permissions WHERE role_id = ?', [$roleId]), 'permission_id'));
    }
}
