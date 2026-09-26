<?php
declare(strict_types=1);

namespace App\Repositories;

/** रोल सूची: यूज़र और अनुमति की गिनती के साथ */
final class RoleRepository
{
    public function allWithCounts(): array
    {
        return db()->all(
            'SELECT r.*,
                (SELECT COUNT(*) FROM {p}users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count,
                (SELECT COUNT(*) FROM {p}role_permissions rp WHERE rp.role_id = r.id) AS permission_count
             FROM {p}roles r ORDER BY r.level DESC, r.name ASC'
        );
    }
}
