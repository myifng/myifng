<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Models\Reader;
use App\Services\AuditService;
use App\Services\ReaderService;

/** पाठक खाते: सूची, प्रोफ़ाइल, ब्लॉक/भरोसेमंद, हटाना, CSV */
final class ReaderController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $status = $request->str('status');
        if (isset(Reader::STATUSES[$status])) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(r.name LIKE ? OR r.email LIKE ? OR r.mobile LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}readers r WHERE $w", $params);
        $items = db()->all("SELECT r.*, l.name city, (SELECT COUNT(*) FROM {p}reader_follows f WHERE f.reader_id = r.id) follows,
                (SELECT COUNT(*) FROM {p}reader_bookmarks b WHERE b.reader_id = r.id) saved, (SELECT COUNT(*) FROM {p}comments c WHERE c.reader_id = r.id) comments
            FROM {p}readers r LEFT JOIN {p}locations l ON l.id = r.location_id WHERE $w ORDER BY r.id DESC LIMIT 50 OFFSET " . Paginator::offset($page, 50), $params);
        $stats = db()->first("SELECT COUNT(*) t, SUM(status = 'active') a, SUM(created_at >= NOW() - INTERVAL 30 DAY) n, SUM(last_login_at >= NOW() - INTERVAL 7 DAY) w FROM {p}readers");
        return $this->view('admin/readers/index', ['items' => new Paginator($items, $total, 50, $page), 'status' => $status, 'q' => $q, 'stats' => array_map('intval', $stats ?? [])]);
    }

    public function show(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $f = ReaderService::follows($id);
        $names = [];
        foreach ($f as $type => $ids) {
            foreach ($ids as $tid) {
                if ($t = ReaderService::target($type, $tid)) {
                    $names[] = ReaderService::FOLLOW_TYPES[$type] . ': ' . $t[0];
                }
            }
        }
        $comments = db()->all('SELECT c.id, c.body, c.status, c.created_at, n.title FROM {p}comments c JOIN {p}news n ON n.id = c.news_id WHERE c.reader_id = ? ORDER BY c.id DESC LIMIT 20', [$id]);
        return $this->view('admin/readers/show', ['r' => $r, 'follows' => $names, 'comments' => $comments,
            'saved' => (int) db()->value('SELECT COUNT(*) FROM {p}reader_bookmarks WHERE reader_id = ?', [$id]),
            'city' => $r['location_id'] ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$r['location_id']]) : null,
            'sub' => db()->value('SELECT status FROM {p}newsletter_subscribers WHERE email = ?', [$r['email']])]);
    }

    /** status: active / blocked; trusted: 0/1; verify: pending → active */
    public function update(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $data = [];
        $action = $request->str('action');
        if ($action === 'block') {
            $data = ['status' => 'blocked'];
        } elseif ($action === 'unblock' || $action === 'verify') {
            $data = ['status' => 'active', 'email_verified_at' => $r['email_verified_at'] ?: date('Y-m-d H:i:s')];
        } elseif ($action === 'trust') {
            $data = ['trusted' => $r['trusted'] ? 0 : 1];
        }
        if (!$data) {
            return $this->back();
        }
        Reader::update($id, $data);
        AuditService::log('update', 'readers', $id, 'पाठक: ' . $action . ' (' . $r['email'] . ')', $r, $data);
        return $this->back()->with('success', ['block' => 'पाठक ब्लॉक (अब लॉगिन नहीं कर सकेगा)।', 'unblock' => 'पाठक फिर चालू।', 'verify' => 'खाता सत्यापित/चालू किया।',
            'trust' => $r['trusted'] ? 'भरोसेमंद हटाया।' : 'भरोसेमंद: इसकी टिप्पणियाँ सीधे छपेंगी (मॉडरेशन "भरोसेमंद" मोड में)।'][$action]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $r = $this->find($id);
        ReaderService::erase($id);
        AuditService::log('delete', 'readers', $id, 'पाठक खाता हटाया: ' . $r['email'], ['email' => $r['email'], 'name' => $r['name']]);
        return $this->toRoute('admin.readers.index')->with('success', 'पाठक खाता हटा दिया गया।');
    }

    public function export(Request $request): Response
    {
        $rows = db()->all('SELECT r.id, r.name, r.email, r.mobile, l.name city, r.status, r.created_at, r.last_login_at FROM {p}readers r LEFT JOIN {p}locations l ON l.id = r.location_id ORDER BY r.id');
        AuditService::log('export', 'readers', null, 'पाठक CSV (' . count($rows) . ')');
        return Response::csv('readers-' . date('Y-m-d') . '.csv', ['ID', 'नाम', 'ईमेल', 'मोबाइल', 'शहर', 'स्थिति', 'जुड़े', 'आख़िरी लॉगिन'], $rows);
    }

    private function find(int $id): array
    {
        return Reader::find($id) ?? throw new HttpException(404);
    }
}
