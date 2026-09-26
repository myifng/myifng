<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Paginator;

/** असाइनमेंट: टैब (आज / आगे / देर / पूरे / सभी), रिपोर्टर को सिर्फ़ अपने */
final class AssignmentRepository
{
    public const TABS = ['today' => 'आज', 'upcoming' => 'आगे', 'overdue' => 'देर हो चुकी', 'completed' => 'पूरे', 'all' => 'सभी'];
    private const OPEN = "a.status IN ('open','accepted','in_progress','submitted')";

    private function tabWhere(string $tab): string
    {
        return match ($tab) {
            'today' => self::OPEN . ' AND (a.deadline IS NULL OR DATE(a.deadline) = CURDATE()) AND (a.deadline IS NULL OR a.deadline >= NOW())',
            'upcoming' => self::OPEN . ' AND DATE(a.deadline) > CURDATE()',
            'overdue' => self::OPEN . " AND a.deadline < NOW() AND a.status <> 'submitted'",
            'completed' => "a.status IN ('completed','cancelled')",
            default => '1=1',
        };
    }

    private function scope(bool $all, array $f): array
    {
        $where = [];
        $params = [];
        if (!$all) {
            $where[] = 'a.reporter_id = ?';
            $params[] = auth()->id();
        } elseif ((int) ($f['reporter'] ?? 0) > 0) {
            $where[] = 'a.reporter_id = ?';
            $params[] = (int) $f['reporter'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(a.title LIKE ? OR a.description LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        return [$where ? implode(' AND ', $where) : '1=1', $params];
    }

    public function paginate(string $tab, bool $all, array $f, int $page, int $perPage = 25): Paginator
    {
        [$s, $params] = $this->scope($all, $f);
        $w = $s . ' AND ' . $this->tabWhere($tab);
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}assignments a WHERE $w", $params);
        $order = $tab === 'completed' ? 'a.updated_at DESC' : "a.deadline IS NULL, a.deadline ASC, FIELD(a.priority, 'urgent','high','normal','low')";
        $items = db()->all(
            "SELECT a.*, u.name AS reporter, c.name AS category, l.name AS location, n.title AS news_title, n.status AS news_status
             FROM {p}assignments a LEFT JOIN {p}users u ON u.id = a.reporter_id LEFT JOIN {p}categories c ON c.id = a.category_id
             LEFT JOIN {p}locations l ON l.id = a.location_id LEFT JOIN {p}news n ON n.id = a.news_id
             WHERE $w ORDER BY $order LIMIT $perPage OFFSET " . Paginator::offset($page, $perPage),
            $params
        );
        return new Paginator($items, $total, $perPage, $page);
    }

    public function counts(bool $all, array $f): array
    {
        [$s, $params] = $this->scope($all, $f);
        $out = [];
        foreach (array_keys(self::TABS) as $t) {
            $out[$t] = (int) db()->value("SELECT COUNT(*) FROM {p}assignments a WHERE $s AND " . $this->tabWhere($t), $params);
        }
        return $out;
    }
}
