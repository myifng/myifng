<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Redirect;
use App\Services\AuditService;
use App\Services\RedirectService;

/** 301/302/307/410 रीडायरेक्ट: सूची, बनाना/बदलना, जाँच, CSV */
final class RedirectController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $type = $request->str('type');
        if ($type === 'auto' || $type === 'manual') {
            $where[] = 'r.is_auto = ' . ($type === 'auto' ? 1 : 0);
        }
        $code = $request->int('code');
        if (isset(Redirect::CODES[$code])) {
            $where[] = 'r.code = ' . $code;
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(r.source LIKE ? OR r.target LIKE ? OR r.note LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}redirects r WHERE $w", $params);
        $items = db()->all("SELECT r.*, u.name creator FROM {p}redirects r LEFT JOIN {p}users u ON u.id = r.created_by WHERE $w ORDER BY r.id DESC LIMIT 50 OFFSET " . Paginator::offset($page, 50), $params);
        $test = null;
        if (($t = $request->str('test')) !== '') {
            $path = RedirectService::normalize($t);
            $test = ['input' => $t, 'path' => $path, 'manual' => RedirectService::match($path, false), 'any' => RedirectService::match($path, true)];
        }
        return $this->view('admin/redirects/index', ['items' => new Paginator($items, $total, 50, $page), 'type' => $type, 'code' => $code, 'q' => $q, 'test' => $test,
            'stats' => db()->first('SELECT COUNT(*) c, COALESCE(SUM(hits), 0) h, COALESCE(SUM(is_auto), 0) a, SUM(status = \'inactive\') off FROM {p}redirects') ?? []]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/redirects/form', ['r' => null, 'prefill' => ['source' => $request->str('source'), 'nf' => $request->int('nf'), 'return' => $request->str('return')]]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = Redirect::create($data + ['created_by' => auth()->id(), 'is_auto' => 0]);
        if ($nf = $request->int('nf')) {
            db()->query("UPDATE {p}not_found_log SET status = 'fixed' WHERE id = ?", [$nf]);
        }
        db()->query("UPDATE {p}not_found_log SET status = 'fixed' WHERE path_hash = ?", [$data['source_hash']]);
        RedirectService::changed();
        AuditService::log('create', 'redirects', $id, 'रीडायरेक्ट: ' . $data['source'] . ' → ' . ($data['target'] ?? '410'), null, $data);
        $to = $request->str('return') === '404' ? route('admin.seo.404') : route('admin.redirects.index');
        return $this->redirect($to)->with('success', 'रीडायरेक्ट बन गया: ' . $data['source'] . ' → ' . ($data['target'] ?? '410 (हटाया गया)'));
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->view('admin/redirects/form', ['r' => $this->find($id), 'prefill' => []]);
    }

    public function update(Request $request, int $id): Response
    {
        $r = $this->find($id);
        $data = $this->payload($request, $r);
        Redirect::update($id, $data + ['is_auto' => 0]); // हाथ से बदला = अब हाथ वाला नियम
        RedirectService::changed();
        AuditService::log('update', 'redirects', $id, 'रीडायरेक्ट बदला: ' . $data['source'], $r, $data);
        return $this->toRoute('admin.redirects.index')->with('success', 'रीडायरेक्ट सेव हो गया।');
    }

    public function toggle(Request $request, int $id): Response
    {
        $r = $this->find($id);
        Redirect::update($id, ['status' => $r['status'] === 'active' ? 'inactive' : 'active']);
        RedirectService::changed();
        AuditService::log('update', 'redirects', $id, 'रीडायरेक्ट ' . ($r['status'] === 'active' ? 'बंद' : 'चालू') . ': ' . $r['source']);
        return $this->back()->with('success', $r['status'] === 'active' ? 'रीडायरेक्ट बंद किया।' : 'रीडायरेक्ट चालू किया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $r = $this->find($id);
        Redirect::delete($id);
        RedirectService::changed();
        AuditService::log('delete', 'redirects', $id, 'रीडायरेक्ट हटाया: ' . $r['source'], $r);
        return $this->back()->with('success', 'रीडायरेक्ट हटा दिया गया।');
    }

    public function export(Request $request): Response
    {
        $rows = db()->all('SELECT * FROM {p}redirects ORDER BY id');
        AuditService::log('export', 'redirects', null, 'रीडायरेक्ट CSV (' . count($rows) . ')');
        return Response::csv('redirects-' . date('Y-m-d') . '.csv', ['source', 'target', 'code', 'hits', 'last_hit_at', 'type', 'status', 'note'],
            (static function () use ($rows) {
                foreach ($rows as $r) {
                    yield [$r['source'] . ($r['match_type'] === 'prefix' ? '/*' : ''), $r['target'], $r['code'], $r['hits'], $r['last_hit_at'], $r['is_auto'] ? 'auto' : 'manual', $r['status'], $r['note']];
                }
            })());
    }

    /** CSV: source,target,code (हेडर वैकल्पिक); गलत पंक्तियाँ छोड़कर बताई जाती हैं */
    public function import(Request $request): Response
    {
        $file = $request->file('csv');
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024 || !preg_match('/\.(csv|txt)$/i', (string) $file['name'])) {
            return $this->back()->with('danger', 'CSV फ़ाइल चुनें (2 MB तक)।');
        }
        $fh = fopen($file['tmp_name'], 'r');
        $ok = 0;
        $bad = [];
        $line = 0;
        while (($row = fgetcsv($fh, 4000, ',', '"', '\\')) !== false && $line < 5000) {
            $line++;
            $row = array_map(static fn($c) => trim((string) $c, " \t\n\r\0\x0B\xEF\xBB\xBF"), $row);
            if ($line === 1 && strtolower($row[0] ?? '') === 'source') {
                continue;
            }
            if (count(array_filter($row)) === 0) {
                continue;
            }
            [$data, $err] = RedirectService::clean($row[0] ?? '', $row[1] ?? '', (int) (($row[2] ?? '') ?: 301));
            if (!$data) {
                $bad[] = "पंक्ति $line: $err";
                continue;
            }
            Redirect::create($data + ['created_by' => auth()->id(), 'is_auto' => 0, 'note' => 'CSV इंपोर्ट']);
            $ok++;
        }
        fclose($fh);
        RedirectService::changed();
        AuditService::log('import', 'redirects', null, "रीडायरेक्ट CSV इंपोर्ट: $ok जुड़े, " . count($bad) . ' छोड़े');
        $resp = $this->back()->with($ok ? 'success' : 'warning', "$ok रीडायरेक्ट जुड़े।" . ($bad ? ' ' . count($bad) . ' पंक्तियाँ छोड़ी गईं।' : ''));
        return $bad ? $resp->with('warning', implode(' · ', array_slice($bad, 0, 8)) . (count($bad) > 8 ? ' …' : '')) : $resp;
    }

    private function payload(Request $request, ?array $r): array
    {
        $v = $this->validate($request, ['source' => 'required|max:500', 'target' => 'nullable|max:1000', 'code' => 'required|in:301,302,307,410', 'note' => 'nullable|max:255',
            'status' => 'required|in:active,inactive'], ['source' => 'पुराना पता', 'target' => 'नया पता', 'code' => 'प्रकार', 'note' => 'नोट', 'status' => 'स्थिति']);
        [$data, $err] = RedirectService::clean((string) $v['source'], (string) ($v['target'] ?? ''), (int) $v['code'], $r ? (int) $r['id'] : null);
        if (!$data) {
            $field = str_contains($err, 'नया') || str_contains($err, 'लूप') ? 'target' : 'source';
            throw new ValidationException([$field => $err], $request->post());
        }
        return $data + ['note' => $v['note'] ? strip_tags((string) $v['note']) : null, 'status' => $v['status']];
    }

    private function find(int $id): array
    {
        return Redirect::find($id) ?? throw new HttpException(404);
    }
}
