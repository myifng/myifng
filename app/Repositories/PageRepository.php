<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;

/** पेज सूची: स्थिति/ट्रैश टैब, खोज, पेज */
final class PageRepository
{
    public function paginate(array $f, int $page, int $perPage = 20): Paginator
    {
        $where = [];
        $params = [];
        if (($f['status'] ?? '') === 'trash') {
            $where[] = 'p.deleted_at IS NOT NULL';
        } else {
            $where[] = 'p.deleted_at IS NULL';
            if (in_array($f['status'] ?? '', ['published', 'draft'], true)) {
                $where[] = 'p.status = ?';
                $params[] = $f['status'];
            }
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}pages p WHERE $w", $params);
        $items = db()->all(
            "SELECT p.id, p.title, p.slug, p.status, p.template, p.updated_at, p.published_at, p.deleted_at, u.name AS editor
             FROM {p}pages p LEFT JOIN {p}users u ON u.id = COALESCE(p.updated_by, p.created_by)
             WHERE $w ORDER BY p.updated_at DESC, p.id DESC LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($items, $total, $perPage, $page);
    }

    public function counts(): array
    {
        $r = db()->first("SELECT SUM(deleted_at IS NULL) alls, SUM(deleted_at IS NULL AND status = 'published') published,
                          SUM(deleted_at IS NULL AND status = 'draft') draft, SUM(deleted_at IS NOT NULL) trash FROM {p}pages");
        return ['' => (int) $r['alls'], 'published' => (int) $r['published'], 'draft' => (int) $r['draft'], 'trash' => (int) $r['trash']];
    }
}
