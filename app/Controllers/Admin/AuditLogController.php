<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;
use App\Services\AuditService;

/** ऑडिट लॉग: कौन, क्या, कब, कहाँ से */
final class AuditLogController extends Controller
{
    public function __construct(private AuditLogRepository $repo = new AuditLogRepository())
    {
    }

    private function filters(Request $r): array
    {
        return ['q' => $r->str('q'), 'user' => $r->int('user'), 'module' => $r->str('module'), 'action' => $r->str('action'), 'from' => $r->str('from'), 'to' => $r->str('to')];
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        return $this->view('admin/audit/index', [
            'logs' => $this->repo->paginate($filters, max(1, $request->int('page', 1))),
            'filters' => $filters,
            'modules' => $this->repo->modules(),
            'actions' => $this->repo->actions(),
            'users' => db()->all('SELECT id, name FROM {p}users ORDER BY name'),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $log = db()->first('SELECT * FROM {p}audit_logs WHERE id = ?', [$id]) ?? throw new HttpException(404);
        return $this->view('admin/audit/show', [
            'log' => $log,
            'old' => json_decode((string) $log['old_values'], true) ?: [],
            'new' => json_decode((string) $log['new_values'], true) ?: [],
        ]);
    }

    public function export(Request $request): Response
    {
        $rows = $this->repo->export($this->filters($request));
        AuditService::log('export', 'audit', null, count($rows) . ' ऑडिट प्रविष्टियाँ एक्सपोर्ट कीं');
        return Response::csv('audit-log-' . date('Y-m-d') . '.csv', ['ID', 'समय', 'यूज़र', 'रोल', 'Action', 'मॉड्यूल', 'रिकॉर्ड', 'विवरण', 'IP', 'डिवाइस'],
            array_map(fn($l) => [$l['id'], $l['created_at'], $l['user_name'], $l['role'], $l['action'], $l['module'], $l['record_id'], $l['description'], $l['ip'], device_name($l['user_agent'])], $rows));
    }

    /** लॉगिन इतिहास: सफल/असफल/ब्लॉक/लॉगआउट */
    public function logins(Request $request): Response
    {
        $f = ['user' => $request->int('user'), 'status' => $request->str('status'), 'ip' => trim($request->str('ip')), 'from' => $request->str('from'), 'to' => $request->str('to')];
        $w = ['1=1'];
        $p = [];
        if ($f['user']) {
            $w[] = 'h.user_id = ?';
            $p[] = $f['user'];
        }
        if (in_array($f['status'], ['success', 'failed', 'blocked', 'logout', 'otp_sent', 'otp_failed'], true)) {
            $w[] = 'h.status = ?';
            $p[] = $f['status'];
        }
        if ($f['ip'] !== '') {
            $w[] = 'h.ip LIKE ?';
            $p[] = $f['ip'] . '%';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $w[] = 'h.created_at >= ?';
            $p[] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $w[] = 'h.created_at <= ?';
            $p[] = $f['to'] . ' 23:59:59';
        }
        $where = implode(' AND ', $w);
        if ($request->str('export') === 'csv' && can('audit.export')) {
            $rows = db()->all("SELECT h.*, u.name, u.email, r.name role FROM {p}login_history h LEFT JOIN {p}users u ON u.id = h.user_id LEFT JOIN {p}roles r ON r.id = u.role_id WHERE $where ORDER BY h.id DESC LIMIT 20000", $p);
            AuditService::log('export', 'audit', null, count($rows) . ' लॉगिन इतिहास प्रविष्टियाँ एक्सपोर्ट कीं');
            return Response::csv('login-history-' . date('Y-m-d') . '.csv', ['समय', 'यूज़र', 'ईमेल', 'रोल', 'स्थिति', 'IP', 'डिवाइस'],
                array_map(static fn($h) => [$h['created_at'], $h['name'], $h['email'], $h['role'], $h['status'], $h['ip'], device_name($h['user_agent'])], $rows));
        }
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}login_history h WHERE $where", $p);
        $rows = db()->all("SELECT h.*, u.name, r.name role FROM {p}login_history h LEFT JOIN {p}users u ON u.id = h.user_id LEFT JOIN {p}roles r ON r.id = u.role_id WHERE $where ORDER BY h.id DESC LIMIT 50 OFFSET " . \App\Core\Paginator::offset($page, 50), $p);
        $sum = [];
        foreach (db()->all("SELECT status, COUNT(*) c FROM {p}login_history WHERE created_at >= NOW() - INTERVAL 24 HOUR GROUP BY status") as $r) {
            $sum[$r['status']] = (int) $r['c'];
        }
        return $this->view('admin/audit/logins', ['items' => new \App\Core\Paginator($rows, $total, 50, $page), 'f' => $f, 'sum' => $sum, 'users' => db()->all('SELECT id, name FROM {p}users ORDER BY name')]);
    }
}
