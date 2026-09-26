<?php
declare(strict_types=1);

namespace App\Core;

/**
 * अनुमति जाँच (RBAC)। अनुमति = मॉड्यूल.action, जैसे 'news.publish'
 * रोल की अनुमतियाँ + यूज़र-स्तर पर दी/छीनी गई अनुमतियाँ। Super Admin सब कुछ कर सकता है।
 */
final class Gate
{
    public const SUPER_ADMIN = 'super-admin';
    private ?array $perms = null;

    public function __construct(private Database $db, private Auth $auth)
    {
    }

    public function isSuperAdmin(?array $user = null): bool
    {
        $user ??= $this->auth->user();
        return ($user['role_slug'] ?? '') === self::SUPER_ADMIN;
    }

    public function can(string $permission): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        return isset($this->permissions()[$permission]);
    }

    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) {
                return true;
            }
        }
        return false;
    }

    /** मौजूदा यूज़र की सभी अनुमतियाँ ['news.view' => true, ...] */
    public function permissions(): array
    {
        if ($this->perms !== null) {
            return $this->perms;
        }
        $user = $this->auth->user();
        if (!$user) {
            return $this->perms = [];
        }
        $list = array_column($this->db->all(
            'SELECT p.name FROM {p}role_permissions rp JOIN {p}permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [(int) $user['role_id']]
        ), 'name');
        $perms = array_fill_keys($list, true);
        foreach ($this->db->all('SELECT p.name, up.allow FROM {p}user_permissions up JOIN {p}permissions p ON p.id = up.permission_id WHERE up.user_id = ?', [(int) $user['id']]) as $o) {
            if ((int) $o['allow'] === 1) {
                $perms[$o['name']] = true;
            } else {
                unset($perms[$o['name']]);
            }
        }
        return $this->perms = $perms;
    }

    /**
     * क्या मौजूदा यूज़र इस स्तर (level) वाले रोल/यूज़र को संभाल सकता है?
     * नियम: सिर्फ़ अपने से नीचे के स्तर वाले; Super Admin सब।
     */
    public function outranks(int $level): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return false;
        }
        if ($this->isSuperAdmin($user)) {
            return true;
        }
        $mine = (int) $this->db->value('SELECT level FROM {p}roles WHERE id = ?', [(int) $user['role_id']]);
        return $level < $mine;
    }

    public function reset(): void
    {
        $this->perms = null;
    }
}
