<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;
use App\Services\NewsService;
use App\Services\NewsWorkflow;

/** ख़बरों की सूची: स्थिति टैब, फ़िल्टर, खोज; हमेशा यूज़र की पहुँच के दायरे में */
final class NewsRepository
{
    public function paginate(array $f, int $page, int $perPage = 25): Paginator
    {
        [$w, $params] = $this->where($f);
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}news n WHERE $w", $params);
        $items = db()->all(
            "SELECT n.id, n.title, n.slug, n.status, n.featured_image, n.published_at, n.scheduled_at, n.updated_at, n.created_at, n.deleted_at, n.views, n.word_count,
                    n.is_breaking, n.is_featured, n.is_exclusive, n.is_live, n.reporter_id, n.created_by, n.category_id,
                    c.name AS category, l.name AS location, r.name AS reporter
             FROM {p}news n
             LEFT JOIN {p}categories c ON c.id = n.category_id
             LEFT JOIN {p}locations l ON l.id = n.location_id
             LEFT JOIN {p}users r ON r.id = n.reporter_id
             WHERE $w ORDER BY " . ($f['status'] === 'scheduled' ? 'n.scheduled_at ASC' : 'n.updated_at DESC') . ", n.id DESC
             LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($items, $total, $perPage, $page);
    }

    /** CSV के लिए (सीमा के साथ) */
    public function export(array $f, int $limit = 5000): array
    {
        [$w, $params] = $this->where($f);
        return db()->all(
            "SELECT n.id, n.title, n.status, c.name AS category, l.name AS location, r.name AS reporter, n.published_at, n.views, n.word_count, n.created_at
             FROM {p}news n LEFT JOIN {p}categories c ON c.id = n.category_id LEFT JOIN {p}locations l ON l.id = n.location_id LEFT JOIN {p}users r ON r.id = n.reporter_id
             WHERE $w ORDER BY n.id DESC LIMIT $limit",
            $params
        );
    }

    private function where(array $f): array
    {
        [$scope, $params] = NewsService::scope('n');
        $where = [$scope];
        if (($f['status'] ?? '') === 'trash') {
            $where[] = 'n.deleted_at IS NOT NULL';
        } else {
            $where[] = 'n.deleted_at IS NULL';
            if (isset(NewsWorkflow::STATUSES[$f['status'] ?? ''])) {
                $where[] = 'n.status = ?';
                $params[] = $f['status'];
            } elseif (($f['status'] ?? '') === 'desk') {
                $where[] = "n.status IN ('submitted','review','fact_check')";
            }
        }
        if (!empty($f['mine'])) {
            $where[] = '(n.reporter_id = ? OR n.created_by = ?)';
            array_push($params, auth()->id(), auth()->id());
        }
        if ((int) ($f['category'] ?? 0) > 0) {
            $where[] = '(n.category_id = ? OR n.category_id IN (SELECT id FROM {p}categories WHERE parent_id = ?))';
            array_push($params, (int) $f['category'], (int) $f['category']);
        }
        if ((int) ($f['reporter'] ?? 0) > 0) {
            $where[] = 'n.reporter_id = ?';
            $params[] = (int) $f['reporter'];
        }
        if ((int) ($f['location'] ?? 0) > 0) {
            // यह लोकेशन या इसके नीचे की कोई भी (path से)
            $path = db()->value('SELECT path FROM {p}locations WHERE id = ?', [(int) $f['location']]);
            if ($path) {
                $where[] = 'n.location_id IN (SELECT id FROM {p}locations WHERE path = ? OR path LIKE ?)';
                array_push($params, $path, addcslashes($path, '%_\\') . '/%');
            } else {
                $where[] = 'n.location_id = ?';
                $params[] = (int) $f['location'];
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['from'] ?? ''))) {
            $where[] = 'n.created_at >= ?';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['to'] ?? ''))) {
            $where[] = 'n.created_at <= ?';
            $params[] = $f['to'] . ' 23:59:59';
        }
        if (isset(\App\Models\News::FLAGS[$f['flag'] ?? ''])) {
            $where[] = 'n.`' . $f['flag'] . '` = 1';
        }
        if (($f['q'] ?? '') !== '') {
            if (ctype_digit($f['q'])) {
                $where[] = 'n.id = ?';
                $params[] = (int) $f['q'];
            } else {
                $like = '%' . addcslashes($f['q'], '%_\\') . '%';
                $where[] = '(n.title LIKE ? OR n.slug LIKE ? OR n.summary LIKE ?)';
                array_push($params, $like, $like, $like);
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /** हर टैब की गिनती (यूज़र के दायरे में) */
    public function counts(bool $mine = false): array
    {
        [$scope, $params] = NewsService::scope('n');
        if ($mine) {
            $scope .= ' AND (n.reporter_id = ? OR n.created_by = ?)';
            array_push($params, auth()->id(), auth()->id());
        }
        $out = ['' => 0, 'desk' => 0, 'trash' => 0] + array_fill_keys(array_keys(NewsWorkflow::STATUSES), 0);
        foreach (db()->all("SELECT n.status, COUNT(*) c FROM {p}news n WHERE $scope AND n.deleted_at IS NULL GROUP BY n.status", $params) as $r) {
            $out[$r['status']] = (int) $r['c'];
            $out[''] += (int) $r['c'];
        }
        $out['desk'] = $out['submitted'] + $out['review'] + $out['fact_check'];
        $out['trash'] = (int) db()->value("SELECT COUNT(*) FROM {p}news n WHERE $scope AND n.deleted_at IS NOT NULL", $params);
        return $out;
    }
}
