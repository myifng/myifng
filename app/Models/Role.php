<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Role extends Model
{
    protected static string $table = 'roles';
    protected static array $fillable = ['name', 'slug', 'description', 'level', 'is_system'];
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    public static function findBySlug(string $slug): ?array
    {
        return static::firstWhere('slug', $slug);
    }

    public static function userCount(int $roleId): int
    {
        return (int) static::db()->value('SELECT COUNT(*) FROM {p}users WHERE role_id = ? AND deleted_at IS NULL', [$roleId]);
    }

    /** रोल जिन्हें मौजूदा यूज़र दूसरों को दे सकता है (Super Admin सिर्फ़ Super Admin दे सकता है) */
    public static function assignable(): array
    {
        $roles = static::where([], 'level DESC, name ASC');
        return is_super_admin() ? $roles : array_values(array_filter($roles, static fn($r) => $r['slug'] !== \App\Core\Gate::SUPER_ADMIN));
    }
}
