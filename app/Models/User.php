<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'mobile', 'password', 'role_id', 'avatar', 'bio', 'status', 'language', 'created_by', 'last_login_at', 'last_login_ip', 'deleted_at'];
    protected static bool $timestamps = true;
    protected static bool $softDeletes = true;

    public const STATUSES = ['active' => 'चालू', 'inactive' => 'बंद', 'suspended' => 'निलंबित'];

    public static function findWithRole(int $id): ?array
    {
        return static::db()->first(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.level AS role_level FROM {p}users u
             JOIN {p}roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return static::firstWhere('email', strtolower(trim($email)));
    }

    /** चालू Super Admin कितने हैं (आख़िरी को हटाने/बंद करने से रोकने के लिए) */
    public static function activeSuperAdmins(): int
    {
        return (int) static::db()->value(
            "SELECT COUNT(*) FROM {p}users u JOIN {p}roles r ON r.id = u.role_id WHERE r.slug = 'super-admin' AND u.status = 'active' AND u.deleted_at IS NULL"
        );
    }

    /** soft delete + ईमेल मुक्त करें, ताकि वही ईमेल नए खाते में इस्तेमाल हो सके */
    public static function softDelete(int $id): void
    {
        static::db()->query(
            "UPDATE {p}users SET deleted_at = NOW(), status = 'inactive', email = CONCAT('deleted-', id, '-', email) WHERE id = ?",
            [$id]
        );
    }
}
