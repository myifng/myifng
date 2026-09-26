<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;

/** यूज़र सूची: खोज, रोल, स्थिति के फ़िल्टर और पेज */
final class UserRepository
{
    public function paginate(array $f, int $page, int $perPage = 20): Paginator
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['role'])) {
            $where[] = 'u.role_id = ?';
            $params[] = (int) $f['role'];
        }
        if (!empty($f['status'])) {
            $where[] = 'u.status = ?';
            $params[] = $f['status'];
        }
        $w = implode(' AND ', $where);
        $sort = match ($f['sort'] ?? '') {
            'name' => 'u.name ASC',
            'login' => 'u.last_login_at IS NULL, u.last_login_at DESC',
            default => 'u.id DESC',
        };
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}users u WHERE $w", $params);
        $items = db()->all(
            "SELECT u.id, u.name, u.email, u.mobile, u.status, u.avatar, u.last_login_at, u.created_at, r.name AS role_name, r.slug AS role_slug
             FROM {p}users u JOIN {p}roles r ON r.id = u.role_id WHERE $w ORDER BY $sort LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($items, $total, $perPage, $page);
    }

    public function statusCounts(): array
    {
        $out = ['all' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0];
        foreach (db()->all('SELECT status, COUNT(*) c FROM {p}users WHERE deleted_at IS NULL GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['c'];
            $out['all'] += (int) $r['c'];
        }
        return $out;
    }

    public function loginHistory(int $userId, int $limit = 15): array
    {
        return db()->all('SELECT * FROM {p}login_history WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit, [$userId]);
    }
}
