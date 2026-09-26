<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;

/** मीडिया सूची: प्रकार, फ़ोल्डर, महीना, खोज, ट्रैश */
final class MediaRepository
{
    public function paginate(array $f, int $page, int $perPage = 40): Paginator
    {
        [$w, $params] = $this->where($f);
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}media m WHERE $w", $params);
        $items = db()->all(
            "SELECT m.*, u.name AS uploader FROM {p}media m LEFT JOIN {p}users u ON u.id = m.uploaded_by
             WHERE $w ORDER BY m.created_at DESC, m.id DESC LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($items, $total, $perPage, $page);
    }

    private function where(array $f): array
    {
        $where = [($f['trash'] ?? false) ? 'm.deleted_at IS NOT NULL' : 'm.deleted_at IS NULL'];
        $params = [];
        if (in_array($f['kind'] ?? '', ['image', 'video', 'audio', 'document'], true)) {
            $where[] = 'm.kind = ?';
            $params[] = $f['kind'];
        }
        if (($f['folder'] ?? '') === 'none') {
            $where[] = 'm.folder_id IS NULL';
        } elseif ((int) ($f['folder'] ?? 0) > 0) {
            $where[] = 'm.folder_id = ?';
            $params[] = (int) $f['folder'];
        }
        if (preg_match('/^\d{4}-\d{2}$/', (string) ($f['month'] ?? ''))) {
            $where[] = "DATE_FORMAT(m.created_at, '%Y-%m') = ?";
            $params[] = $f['month'];
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $where[] = '(m.title LIKE ? OR m.alt LIKE ? OR m.caption LIKE ? OR m.keywords LIKE ? OR m.original_name LIKE ? OR m.credit LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    public function counts(): array
    {
        $r = db()->first("SELECT SUM(deleted_at IS NULL) alls, SUM(deleted_at IS NULL AND kind = 'image') image, SUM(deleted_at IS NULL AND kind = 'video') video,
                          SUM(deleted_at IS NULL AND kind = 'audio') audio, SUM(deleted_at IS NULL AND kind = 'document') document, SUM(deleted_at IS NOT NULL) trash,
                          COALESCE(SUM(size), 0) bytes FROM {p}media");
        return array_map('intval', $r);
    }

    public function folders(): array
    {
        return db()->all('SELECT f.id, f.name, (SELECT COUNT(*) FROM {p}media m WHERE m.folder_id = f.id AND m.deleted_at IS NULL) AS files FROM {p}media_folders f ORDER BY f.name');
    }

    public function months(): array
    {
        return array_column(db()->all("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') ym FROM {p}media WHERE deleted_at IS NULL ORDER BY ym DESC LIMIT 36"), 'ym');
    }
}
