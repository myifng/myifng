<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;

/** ऑडिट लॉग: यूज़र, मॉड्यूल, action और तारीख़ से फ़िल्टर */
final class AuditLogRepository
{
    public function paginate(array $f, int $page, int $perPage = 30): Paginator
    {
        [$w, $params] = $this->filters($f);
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}audit_logs a WHERE $w", $params);
        $items = db()->all("SELECT a.* FROM {p}audit_logs a WHERE $w ORDER BY a.id DESC LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage), $params);
        return new Paginator($items, $total, $perPage, $page);
    }

    /** CSV एक्सपोर्ट के लिए (अधिकतम 10,000 पंक्तियाँ) */
    public function export(array $f): array
    {
        [$w, $params] = $this->filters($f);
        return db()->all("SELECT a.* FROM {p}audit_logs a WHERE $w ORDER BY a.id DESC LIMIT 10000", $params);
    }

    private function filters(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($f['user'])) {
            $where[] = 'a.user_id = ?';
            $params[] = (int) $f['user'];
        }
        if (!empty($f['module'])) {
            $where[] = 'a.module = ?';
            $params[] = $f['module'];
        }
        if (!empty($f['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $f['action'];
        }
        if (!empty($f['from']) && strtotime($f['from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = date('Y-m-d 00:00:00', strtotime($f['from']));
        }
        if (!empty($f['to']) && strtotime($f['to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = date('Y-m-d 23:59:59', strtotime($f['to']));
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(a.description LIKE ? OR a.user_name LIKE ? OR a.ip LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    public function modules(): array
    {
        return array_column(db()->all('SELECT DISTINCT module FROM {p}audit_logs ORDER BY module'), 'module');
    }

    public function actions(): array
    {
        return array_column(db()->all('SELECT DISTINCT action FROM {p}audit_logs ORDER BY action'), 'action');
    }
}
