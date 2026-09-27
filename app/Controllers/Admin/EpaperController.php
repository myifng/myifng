<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\EpaperHotspot;
use App\Models\EpaperIssue;
use App\Models\EpaperPage;
use App\Services\AuditService;
use App\Services\EmbedService;
use App\Services\EpaperService;

/** ई-पेपर: अंक, पेज (PDF → इमेज ब्राउज़र में, या सीधे इमेज), क्रम, हॉटस्पॉट */
final class EpaperController extends Controller
{
    public function index(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        if ($ed = $request->int('edition')) {
            $where[] = 'i.edition_id = ?';
            $params[] = $ed;
        }
        $month = preg_match('/^\d{4}-\d{2}$/', $request->str('month')) ? $request->str('month') : '';
        if ($month) {
            $where[] = "DATE_FORMAT(i.issue_date, '%Y-%m') = ?";
            $params[] = $month;
        }
        $status = $request->str('status');
        if ($status === 'draft') {
            $where[] = "i.status = 'draft'";
        } elseif ($status === 'published') {
            $where[] = "i.status = 'published' AND i.publish_at <= NOW()";
        } elseif ($status === 'scheduled') {
            $where[] = "i.status = 'published' AND i.publish_at > NOW()";
        } else {
            $status = '';
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}epaper_issues i WHERE $w", $params);
        $items = db()->all("SELECT i.*, e.name AS edition, e.slug AS edition_slug FROM {p}epaper_issues i JOIN {p}epaper_editions e ON e.id = i.edition_id
                            WHERE $w ORDER BY i.issue_date DESC, e.sort_order LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $today = db()->all("SELECT e.id, e.name, i.id AS issue_id, i.status, i.publish_at, i.page_count FROM {p}epaper_editions e
                            LEFT JOIN {p}epaper_issues i ON i.edition_id = e.id AND i.issue_date = CURDATE() WHERE e.status = 'active' ORDER BY e.is_default DESC, e.sort_order");
        return $this->view('admin/epaper/index', ['items' => new Paginator($items, $total, 30, $page), 'editions' => $this->editionOptions(),
            'edition' => $ed, 'month' => $month, 'status' => $status, 'today' => $today]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/epaper/create', ['editions' => $this->editionOptions(), 'edition' => $request->int('edition'), 'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->str('date')) ? $request->str('date') : date('Y-m-d')]);
    }

    public function store(Request $request): Response
    {
        $v = $this->validate($request, [
            'edition_id' => 'required|integer|exists:epaper_editions,id', 'issue_date' => 'required|date', 'title' => 'nullable|max:190', 'access' => 'required|in:free,premium',
        ], ['edition_id' => 'संस्करण', 'issue_date' => 'तारीख़', 'title' => 'शीर्षक', 'access' => 'पहुँच']);
        $date = date('Y-m-d', strtotime((string) $v['issue_date']));
        if ($id = (int) db()->value('SELECT id FROM {p}epaper_issues WHERE edition_id = ? AND issue_date = ?', [$v['edition_id'], $date])) {
            return $this->toRoute('admin.epaper.edit', ['id' => $id])->with('info', 'इस संस्करण का इस तारीख़ का अंक पहले से है; वही खुला है।');
        }
        $id = EpaperIssue::create(['edition_id' => (int) $v['edition_id'], 'issue_date' => $date, 'title' => $v['title'] ? strip_tags((string) $v['title']) : null,
            'dir' => EpaperService::newDir($date), 'access' => $v['access'], 'status' => 'draft', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
        AuditService::log('create', 'epaper', $id, 'ई-पेपर अंक: ' . hindi_date($date));
        return $this->toRoute('admin.epaper.edit', ['id' => $id])->with('success', 'अंक बन गया। अब PDF या पेज की इमेज अपलोड करें।');
    }

    public function edit(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        $edition = db()->first('SELECT * FROM {p}epaper_editions WHERE id = ?', [$issue['edition_id']]);
        $pages = db()->all('SELECT p.*, (SELECT COUNT(*) FROM {p}epaper_hotspots h WHERE h.page_id = p.id) AS hotspots FROM {p}epaper_pages p WHERE p.issue_id = ? ORDER BY p.page_no', [$id]);
        return $this->view('admin/epaper/edit', ['issue' => $issue, 'edition' => $edition, 'pages' => $pages, 'editions' => $this->editionOptions(),
            'maxPdf' => EpaperService::maxPdfMb(), 'serverMb' => EpaperService::serverLimitMb()]);
    }

    /** शीर्षक, तारीख़, पहुँच, स्थिति, प्रकाशन का समय */
    public function update(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        $v = $this->validate($request, [
            'edition_id' => 'required|integer|exists:epaper_editions,id', 'issue_date' => 'required|date', 'title' => 'nullable|max:190', 'access' => 'required|in:free,premium',
            'status' => 'required|in:draft,published', 'publish_at' => 'nullable|date',
        ], ['edition_id' => 'संस्करण', 'issue_date' => 'तारीख़', 'title' => 'शीर्षक', 'access' => 'पहुँच', 'status' => 'स्थिति', 'publish_at' => 'प्रकाशन का समय']);
        $date = date('Y-m-d', strtotime((string) $v['issue_date']));
        if ((int) db()->value('SELECT id FROM {p}epaper_issues WHERE edition_id = ? AND issue_date = ? AND id <> ?', [$v['edition_id'], $date, $id])) {
            throw new ValidationException(['issue_date' => 'इस संस्करण का इस तारीख़ का अंक पहले से है।'], $request->post());
        }
        $data = ['edition_id' => (int) $v['edition_id'], 'issue_date' => $date, 'title' => $v['title'] ? strip_tags((string) $v['title']) : null, 'access' => $v['access'], 'updated_by' => auth()->id()];
        if (can('epaper.publish')) {
            if ($v['status'] === 'published' && !(int) $issue['page_count']) {
                throw new ValidationException(['status' => 'प्रकाशित करने से पहले पेज अपलोड करें।'], $request->post());
            }
            $data['status'] = $v['status'];
            $data['publish_at'] = $v['publish_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['publish_at'])) : ($v['status'] === 'published' ? ($issue['publish_at'] ?? now()) : null);
        }
        EpaperIssue::update($id, $data);
        EpaperService::changed();
        AuditService::log('update', 'epaper', $id, 'ई-पेपर अंक बदला: ' . hindi_date($date), $issue, $data);
        $state = ($data['status'] ?? $issue['status']) === 'published' ? (strtotime((string) ($data['publish_at'] ?? $issue['publish_at'])) > time() ? 'शेड्यूल: ' . hindi_date($data['publish_at'] ?? $issue['publish_at'], true) : 'वेबसाइट पर प्रकाशित') : 'ड्राफ़्ट';
        return $this->toRoute('admin.epaper.edit', ['id' => $id])->with('success', 'सेव हो गया · ' . $state);
    }

    public function destroy(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        EpaperIssue::delete($id);
        EpaperService::purge($issue);
        EpaperService::changed();
        AuditService::log('delete', 'epaper', $id, 'ई-पेपर अंक हटाया: ' . hindi_date($issue['issue_date']), $issue);
        return $this->toRoute('admin.epaper.index')->with('success', 'अंक और उसकी सारी फ़ाइलें हट गईं।');
    }

    // ---------- पेज (AJAX) ----------

    public function uploadPage(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        $count = (int) db()->value('SELECT COUNT(*) FROM {p}epaper_pages WHERE issue_id = ?', [$id]);
        if ($count >= EpaperService::MAX_PAGES) {
            return $this->json(['ok' => false, 'message' => 'एक अंक में ज़्यादा से ज़्यादा ' . EpaperService::MAX_PAGES . ' पेज।'], 422);
        }
        $r = EpaperService::storePage($issue, $request->file('file') ?? []);
        if (!$r['ok']) {
            return $this->json(['ok' => false, 'message' => $r['error']], 422);
        }
        $label = trim(strip_tags($request->str('label')));
        $pid = EpaperPage::create(['issue_id' => $id, 'page_no' => $count + 1, 'image' => $r['image'], 'thumb' => $r['thumb'], 'width' => $r['width'], 'height' => $r['height'],
            'label' => $label !== '' ? mb_substr($label, 0, 100) : null, 'created_at' => now()]);
        EpaperService::renumber($id);
        EpaperService::changed();
        $p = EpaperPage::find($pid);
        return $this->json(['ok' => true, 'page' => $this->pageJson($p + ['hotspots' => 0], $issue)]);
    }

    public function uploadPdf(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        $r = EpaperService::storePdf($issue, $request->file('file') ?? []);
        if (!$r['ok']) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'message' => $r['error']], 422) : $this->back()->with('danger', $r['error']);
        }
        EpaperIssue::update($id, ['pdf' => $r['path'], 'pdf_size' => $r['size']]);
        AuditService::log('pdf', 'epaper', $id, 'PDF अपलोड (' . round($r['size'] / 1048576, 1) . ' MB)');
        return $request->wantsJson() ? $this->json(['ok' => true, 'message' => 'PDF सेव हो गई।']) : $this->back()->with('success', 'PDF सेव हो गई।');
    }

    public function deletePdf(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        if ($issue['pdf']) {
            @unlink(EpaperService::root() . $issue['pdf']);
            EpaperIssue::update($id, ['pdf' => null, 'pdf_size' => null]);
        }
        return $this->back()->with('success', 'PDF हटा दी गई। पेज बने रहेंगे।');
    }

    /** क्रम: ids[] नए क्रम में */
    public function order(Request $request, int $id): Response
    {
        $this->find($id);
        $ids = array_values(array_filter(array_map('intval', (array) ($request->post()['ids'] ?? []))));
        $own = array_map('intval', array_column(db()->all('SELECT id FROM {p}epaper_pages WHERE issue_id = ?', [$id]), 'id'));
        if (!$ids || array_diff($ids, $own) || count($ids) !== count($own)) {
            return $this->json(['ok' => false, 'message' => 'पेज की सूची मेल नहीं खाती; पेज दोबारा खोलें।'], 422);
        }
        db()->transaction(static function () use ($ids) {
            foreach ($ids as $i => $pid) {
                db()->query('UPDATE {p}epaper_pages SET page_no = ? WHERE id = ?', [$i + 1, $pid]);
            }
        });
        EpaperService::renumber($id);
        EpaperService::changed();
        return $this->json(['ok' => true, 'message' => 'क्रम सेव हो गया।']);
    }

    public function updatePage(Request $request, int $id, int $pid): Response
    {
        $this->find($id);
        $page = $this->findPage($id, $pid);
        $label = trim(strip_tags($request->str('label')));
        EpaperPage::update($pid, ['label' => $label !== '' ? mb_substr($label, 0, 100) : null]);
        EpaperService::changed();
        return $request->wantsJson() ? $this->json(['ok' => true, 'message' => 'पेज ' . $page['page_no'] . ' का नाम सेव हुआ।']) : $this->back();
    }

    public function deletePage(Request $request, int $id, int $pid): Response
    {
        $issue = $this->find($id);
        $page = $this->findPage($id, $pid);
        EpaperService::deletePage($page);
        EpaperService::renumber($id);
        $issue = $this->find($id);
        if ($issue['status'] === 'published' && !(int) $issue['page_count']) {
            EpaperIssue::update($id, ['status' => 'draft']); // बिना पेज प्रकाशित नहीं रह सकता
        }
        EpaperService::changed();
        AuditService::log('page_delete', 'epaper', $id, 'पेज ' . $page['page_no'] . ' हटाया');
        return $request->wantsJson() ? $this->json(['ok' => true, 'message' => 'पेज हट गया।', 'count' => (int) $issue['page_count']]) : $this->back()->with('success', 'पेज हट गया।');
    }

    /** सारे पेज हटाएँ (PDF से दोबारा बनाने के लिए) */
    public function clearPages(Request $request, int $id): Response
    {
        $issue = $this->find($id);
        foreach (EpaperService::pages($id) as $p) {
            EpaperService::deletePage($p);
        }
        EpaperService::renumber($id);
        EpaperIssue::update($id, ['status' => 'draft']);
        EpaperService::changed();
        AuditService::log('pages_clear', 'epaper', $id, 'सारे पेज हटाए (' . hindi_date($issue['issue_date']) . ')');
        return $this->back()->with('success', 'सारे पेज हट गए; अंक ड्राफ़्ट में है।');
    }

    // ---------- हॉटस्पॉट ----------

    public function hotspots(Request $request, int $id, int $pid): Response
    {
        $issue = $this->find($id);
        $page = $this->findPage($id, $pid);
        $spots = db()->all('SELECT h.*, n.title AS news_title FROM {p}epaper_hotspots h LEFT JOIN {p}news n ON n.id = h.news_id WHERE h.page_id = ? ORDER BY h.y, h.x', [$pid]);
        $nav = db()->first('SELECT (SELECT id FROM {p}epaper_pages WHERE issue_id = ? AND page_no = ?) AS prev, (SELECT id FROM {p}epaper_pages WHERE issue_id = ? AND page_no = ?) AS next',
            [$id, $page['page_no'] - 1, $id, $page['page_no'] + 1]);
        return $this->view('admin/epaper/hotspots', ['issue' => $issue, 'page' => $page, 'spots' => $spots, 'nav' => $nav]);
    }

    /** पूरे पेज के हॉटस्पॉट एक साथ (JSON: [{x,y,w,h,news_id,url,label}]) */
    public function saveHotspots(Request $request, int $id, int $pid): Response
    {
        $this->find($id);
        $this->findPage($id, $pid);
        $raw = json_decode($request->str('spots', '[]'), true);
        if (!is_array($raw) || count($raw) > 100) {
            return $this->json(['ok' => false, 'message' => 'हॉटस्पॉट का डेटा सही नहीं है।'], 422);
        }
        $rows = [];
        foreach ($raw as $i => $s) {
            if (!is_array($s)) {
                continue;
            }
            $c = static fn($k) => round(max(0, min(100, (float) ($s[$k] ?? 0))), 3);
            $x = $c('x');
            $y = $c('y');
            $w = min($c('w'), 100 - $x);
            $h = min($c('h'), 100 - $y);
            if ($w < 1 || $h < 1) {
                continue; // बहुत छोटा
            }
            $news = (int) ($s['news_id'] ?? 0);
            $news = $news && db()->value('SELECT id FROM {p}news WHERE id = ?', [$news]) ? $news : null;
            $url = trim((string) ($s['url'] ?? ''));
            if ($url !== '' && !EmbedService::safeLink($url)) {
                return $this->json(['ok' => false, 'message' => 'हॉटस्पॉट ' . ($i + 1) . ': लिंक https:// या / से शुरू हो।'], 422);
            }
            if (!$news && $url === '') {
                return $this->json(['ok' => false, 'message' => 'हॉटस्पॉट ' . ($i + 1) . ': ख़बर चुनें या लिंक डालें।'], 422);
            }
            $rows[] = ['page_id' => $pid, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'news_id' => $news, 'url' => $news ? null : mb_substr($url, 0, 500),
                'label' => ($l = trim(strip_tags((string) ($s['label'] ?? '')))) !== '' ? mb_substr($l, 0, 190) : null];
        }
        db()->transaction(static function () use ($pid, $rows) {
            db()->query('DELETE FROM {p}epaper_hotspots WHERE page_id = ?', [$pid]);
            foreach ($rows as $r) {
                EpaperHotspot::create($r);
            }
        });
        EpaperService::changed();
        AuditService::log('hotspots', 'epaper', $id, 'पेज #' . $pid . ': ' . count($rows) . ' हॉटस्पॉट');
        return $this->json(['ok' => true, 'message' => count($rows) . ' हॉटस्पॉट सेव हुए।', 'count' => count($rows)]);
    }

    private function pageJson(array $p, array $issue): array
    {
        return ['id' => (int) $p['id'], 'page_no' => (int) $p['page_no'], 'thumb' => upload_url($p['thumb'] ?: $p['image']), 'label' => (string) $p['label'],
            'width' => (int) $p['width'], 'height' => (int) $p['height'], 'hotspots' => (int) ($p['hotspots'] ?? 0),
            'hotspots_url' => route('admin.epaper.hotspots', ['id' => $issue['id'], 'pid' => $p['id']]),
            'update_url' => route('admin.epaper.pages.update', ['id' => $issue['id'], 'pid' => $p['id']]),
            'delete_url' => route('admin.epaper.pages.destroy', ['id' => $issue['id'], 'pid' => $p['id']])];
    }

    private function editionOptions(): array
    {
        return array_column(db()->all('SELECT id, name FROM {p}epaper_editions ORDER BY is_default DESC, sort_order, name'), 'name', 'id');
    }

    private function find(int $id): array
    {
        return EpaperIssue::find($id) ?? throw new HttpException(404);
    }

    private function findPage(int $issueId, int $pid): array
    {
        return db()->first('SELECT * FROM {p}epaper_pages WHERE id = ? AND issue_id = ?', [$pid, $issueId]) ?? throw new HttpException(404);
    }
}
