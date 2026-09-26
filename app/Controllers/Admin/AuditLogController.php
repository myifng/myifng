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
}
