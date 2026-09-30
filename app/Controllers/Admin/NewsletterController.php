<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterList;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTemplate;
use App\Services\AuditService;
use App\Services\HtmlSanitizer;
use App\Services\NewsletterService;

/** न्यूज़लेटर: कैंपेन, सब्सक्राइबर, सूचियाँ, टेम्पलेट */
final class NewsletterController extends Controller
{
    // ---------- कैंपेन ----------
    public function index(Request $request): Response
    {
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value('SELECT COUNT(*) FROM {p}newsletter_campaigns');
        $items = db()->all('SELECT * FROM {p}newsletter_campaigns ORDER BY id DESC LIMIT 30 OFFSET ' . Paginator::offset($page, 30));
        $stats = db()->first("SELECT SUM(status = 'subscribed') s, SUM(status = 'pending') p, SUM(status = 'unsubscribed') u, SUM(status = 'subscribed' AND confirmed_at >= NOW() - INTERVAL 30 DAY) n FROM {p}newsletter_subscribers");
        return $this->view('admin/newsletter/index', ['items' => new Paginator($items, $total, 30, $page), 'stats' => array_map('intval', $stats ?? []),
            'queued' => (int) db()->value("SELECT COUNT(*) FROM {p}newsletter_sends WHERE status = 'queued'")]);
    }

    private function formData(): array
    {
        return ['lists' => db()->all('SELECT l.*, (SELECT COUNT(*) FROM {p}newsletter_list_subscribers ls JOIN {p}newsletter_subscribers s ON s.id = ls.subscriber_id AND s.status = \'subscribed\' WHERE ls.list_id = l.id) subs FROM {p}newsletter_lists l ORDER BY l.is_default DESC, l.name'),
            'templates' => array_column(db()->all('SELECT id, name FROM {p}newsletter_templates ORDER BY is_default DESC, name'), 'name', 'id')];
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/newsletter/campaign', ['c' => null] + $this->formData());
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request);
        $id = NewsletterCampaign::create($data + ['created_by' => auth()->id(), 'status' => 'draft']);
        AuditService::log('create', 'newsletter', $id, 'न्यूज़लेटर कैंपेन: ' . $data['subject']);
        return $this->afterSave($request, $id, 'कैंपेन ड्राफ़्ट सेव हो गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        if (!in_array($c['status'], ['draft', 'scheduled'], true)) {
            return $this->toRoute('admin.newsletter.show', ['id' => $id]);
        }
        return $this->view('admin/newsletter/campaign', ['c' => $c] + $this->formData());
    }

    public function update(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        if (!in_array($c['status'], ['draft', 'scheduled'], true)) {
            throw new HttpException(403, 'भेजा जा चुका कैंपेन नहीं बदल सकते।');
        }
        NewsletterCampaign::update($id, $this->payload($request) + ['status' => 'draft', 'scheduled_at' => null]);
        AuditService::log('update', 'newsletter', $id, 'कैंपेन बदला: ' . $c['subject']);
        return $this->afterSave($request, $id, 'कैंपेन सेव हो गया।');
    }

    /** सेव के बाद: टेस्ट / अभी भेजें / तय समय */
    private function afterSave(Request $request, int $id, string $msg): Response
    {
        $then = $request->str('then');
        if ($then === 'test') {
            $to = mb_strtolower(trim($request->str('test_email'))) ?: (string) auth()->user()['email'];
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return $this->toRoute('admin.newsletter.edit', ['id' => $id])->with('danger', $msg . ' टेस्ट ईमेल का पता सही नहीं।');
            }
            $c = NewsletterCampaign::find($id);
            $ok = app('mailer')->send($to, '[टेस्ट] ' . $c['subject'], NewsletterService::render($c, ['token' => str_repeat('0', 40), 'name' => auth()->user()['name']]));
            return $this->toRoute('admin.newsletter.edit', ['id' => $id])->with($ok ? 'success' : 'warning', $msg . ($ok ? " टेस्ट ईमेल $to पर भेजा।" : ' टेस्ट ईमेल नहीं जा सका (सर्वर का mail() देखें; लॉग में दर्ज)।'));
        }
        if (in_array($then, ['send', 'schedule'], true)) {
            if (!can('newsletter.manage')) {
                throw new HttpException(403);
            }
            if ($then === 'schedule') {
                $ts = strtotime($request->str('scheduled_at'));
                if (!$ts || $ts < time() + 60) {
                    return $this->toRoute('admin.newsletter.edit', ['id' => $id])->with('danger', $msg . ' भेजने का समय कम से कम 1 मिनट आगे का चुनें।');
                }
                NewsletterCampaign::update($id, ['status' => 'scheduled', 'scheduled_at' => date('Y-m-d H:i:s', $ts)]);
                AuditService::log('schedule', 'newsletter', $id, 'कैंपेन शेड्यूल: ' . date('Y-m-d H:i', $ts));
                return $this->toRoute('admin.newsletter.show', ['id' => $id])->with('success', 'कैंपेन ' . hindi_date(date('Y-m-d H:i:s', $ts), true) . ' पर भेजा जाएगा।');
            }
            $n = NewsletterService::start($id);
            AuditService::log('send', 'newsletter', $id, "कैंपेन भेजना शुरू ($n)");
            return $this->toRoute('admin.newsletter.show', ['id' => $id])->with($n ? 'success' : 'warning', $n ? "$n सब्सक्राइबर के लिए कतार बनी। हर मिनट " . setting('newsletter_batch', '30') . ' ईमेल जाएँगे।' : 'चुनी सूचियों में कोई सब्सक्राइब्ड पता नहीं।');
        }
        return $this->toRoute('admin.newsletter.edit', ['id' => $id])->with('success', $msg);
    }

    public function show(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        $failed = db()->all("SELECT s.email, d.error FROM {p}newsletter_sends d JOIN {p}newsletter_subscribers s ON s.id = d.subscriber_id WHERE d.campaign_id = ? AND d.status = 'failed' LIMIT 50", [$id]);
        return $this->view('admin/newsletter/show', ['c' => $c, 'failed' => $failed, 'queued' => (int) db()->value("SELECT COUNT(*) FROM {p}newsletter_sends WHERE campaign_id = ? AND status = 'queued'", [$id])]);
    }

    /** iframe में प्रीव्यू (srcdoc नहीं: अलग पेज, कोई स्क्रिप्ट नहीं) */
    public function preview(Request $request, int $id): Response
    {
        $html = NewsletterService::render($this->campaign($id), null);
        return (new Response($html, 200))->header('Content-Security-Policy', "default-src 'none'; img-src * data:; style-src 'unsafe-inline'")->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /** अभी कुछ ईमेल भेजें (cron न चलता हो तो) / रद्द / कॉपी / हटाएँ */
    public function process(Request $request): Response
    {
        $n = NewsletterService::process(null, 25);
        return $this->back()->with('success', "$n ईमेल भेजे/प्रोसेस हुए।");
    }

    public function cancel(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        if (!in_array($c['status'], ['scheduled', 'sending'], true)) {
            return $this->back();
        }
        db()->query("DELETE FROM {p}newsletter_sends WHERE campaign_id = ? AND status = 'queued'", [$id]);
        NewsletterService::recount($id);
        NewsletterCampaign::update($id, ['status' => $c['status'] === 'scheduled' ? 'draft' : 'cancelled', 'scheduled_at' => null]);
        AuditService::log('cancel', 'newsletter', $id, 'कैंपेन रोका: ' . $c['subject']);
        return $this->back()->with('success', $c['status'] === 'scheduled' ? 'शेड्यूल हटाया; कैंपेन ड्राफ़्ट में।' : 'भेजना रोक दिया; बाकी ईमेल नहीं जाएँगे।');
    }

    public function duplicate(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        $new = NewsletterCampaign::create(array_intersect_key($c, array_flip(['preheader', 'content', 'include_top', 'template_id', 'list_ids'])) + ['subject' => $c['subject'] . ' (कॉपी)', 'status' => 'draft', 'created_by' => auth()->id()]);
        return $this->toRoute('admin.newsletter.edit', ['id' => $new])->with('success', 'कॉपी बनी।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $c = $this->campaign($id);
        if ($c['status'] === 'sending') {
            return $this->back()->with('danger', 'भेजे जा रहे कैंपेन को पहले रोकें।');
        }
        NewsletterCampaign::delete($id);
        AuditService::log('delete', 'newsletter', $id, 'कैंपेन हटाया: ' . $c['subject']);
        return $this->toRoute('admin.newsletter.index')->with('success', 'कैंपेन हटा दिया गया।');
    }

    private function payload(Request $request): array
    {
        $v = $this->validate($request, ['subject' => 'required|min:3|max:200', 'preheader' => 'nullable|max:200', 'template_id' => 'nullable|integer', 'include_top' => 'required|integer|min:0|max:20'],
            ['subject' => 'विषय', 'preheader' => 'प्रीहेडर', 'template_id' => 'टेम्पलेट', 'include_top' => 'टॉप ख़बरें']);
        $content = HtmlSanitizer::clean((string) $request->input('content', ''), (string) config('app.url'));
        if (trim(strip_tags($content)) === '' && !(int) $v['include_top']) {
            throw new ValidationException(['content' => 'कुछ सामग्री लिखें या "आज की टॉप ख़बरें" जोड़ें।'], $request->post());
        }
        $lists = array_map('intval', (array) $request->input('lists', []));
        $lists = $lists ? array_map('intval', array_column(db()->all('SELECT id FROM {p}newsletter_lists WHERE id IN (' . Database::in($lists) . ')', $lists), 'id')) : [];
        $tpl = $v['template_id'] && NewsletterTemplate::find((int) $v['template_id']) ? (int) $v['template_id'] : null;
        return ['subject' => trim(strip_tags((string) $v['subject'])), 'preheader' => $v['preheader'] ? strip_tags((string) $v['preheader']) : null, 'content' => $content,
            'include_top' => (int) $v['include_top'], 'template_id' => $tpl, 'list_ids' => $lists ? implode(',', $lists) : null];
    }

    private function campaign(int $id): array
    {
        return NewsletterCampaign::find($id) ?? throw new HttpException(404);
    }

    // ---------- सब्सक्राइबर ----------
    public function subscribers(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        $status = $request->str('status');
        if (isset(NewsletterSubscriber::STATUSES[$status])) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ($list = $request->int('list')) {
            $where[] = 's.id IN (SELECT subscriber_id FROM {p}newsletter_list_subscribers WHERE list_id = ?)';
            $params[] = $list;
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(s.email LIKE ? OR s.name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}newsletter_subscribers s WHERE $w", $params);
        $items = db()->all("SELECT s.*, (SELECT GROUP_CONCAT(l.name SEPARATOR ', ') FROM {p}newsletter_list_subscribers ls JOIN {p}newsletter_lists l ON l.id = ls.list_id WHERE ls.subscriber_id = s.id) lists
            FROM {p}newsletter_subscribers s WHERE $w ORDER BY s.id DESC LIMIT 50 OFFSET " . Paginator::offset($page, 50), $params);
        return $this->view('admin/newsletter/subscribers', ['items' => new Paginator($items, $total, 50, $page), 'status' => $status, 'q' => $q, 'list' => $list,
            'lists' => array_column(db()->all('SELECT id, name FROM {p}newsletter_lists ORDER BY name'), 'name', 'id')]);
    }

    /** हाथ से जोड़ें (एक या कई ईमेल; सहमति ली हुई मानी जाती है) */
    public function addSubscribers(Request $request): Response
    {
        $emails = array_unique(array_filter(array_map(static fn($e) => mb_strtolower(trim($e)), preg_split('/[\s,;]+/', $request->str('emails')) ?: [])));
        $lists = array_map('intval', (array) $request->input('lists', []));
        $ok = 0;
        $bad = 0;
        foreach (array_slice($emails, 0, 1000) as $e) {
            if (!filter_var($e, FILTER_VALIDATE_EMAIL) || mb_strlen($e) > 190) {
                $bad++;
                continue;
            }
            $s = db()->first('SELECT status FROM {p}newsletter_subscribers WHERE email = ?', [$e]);
            if ($s && $s['status'] === 'unsubscribed') {
                $bad++; // जिसने ख़ुद अनसब्सक्राइब किया, उसे दोबारा नहीं जोड़ते
                continue;
            }
            NewsletterService::subscribe($e, null, 'admin', null, !$request->bool('confirm'), $lists);
            $ok++;
        }
        AuditService::log('create', 'newsletter', null, "सब्सक्राइबर जोड़े: $ok");
        return $this->back()->with($ok ? 'success' : 'warning', "$ok पते जोड़े।" . ($bad ? " $bad छोड़े (ग़लत या पहले अनसब्सक्राइब)।" : ''));
    }

    public function subscriberAction(Request $request): Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        $action = $request->str('action');
        if (!$ids || !in_array($action, ['unsubscribe', 'delete', 'list'], true)) {
            return $this->back()->with('warning', 'कुछ नहीं चुना गया।');
        }
        if ($action === 'delete' && !can('newsletter.delete')) {
            throw new HttpException(403);
        }
        $in = Database::in($ids);
        if ($action === 'delete') {
            $n = db()->query("DELETE FROM {p}newsletter_subscribers WHERE id IN ($in)", $ids)->rowCount();
        } elseif ($action === 'list' && ($lid = $request->int('list_id')) && NewsletterList::find($lid)) {
            foreach ($ids as $sid) {
                db()->query('INSERT IGNORE INTO {p}newsletter_list_subscribers (list_id, subscriber_id) VALUES (?, ?)', [$lid, $sid]);
            }
            $n = count($ids);
        } else {
            foreach ($ids as $sid) {
                NewsletterService::unsubscribe($sid);
            }
            $n = count($ids);
        }
        AuditService::log('update', 'newsletter', null, "सब्सक्राइबर: $action ($n)");
        return $this->back()->with('success', "$n पतों पर काम हुआ।");
    }

    public function export(Request $request): Response
    {
        $rows = db()->all('SELECT email, name, status, source, confirmed_at, created_at FROM {p}newsletter_subscribers ORDER BY id');
        AuditService::log('export', 'newsletter', null, 'सब्सक्राइबर CSV (' . count($rows) . ')');
        return Response::csv('subscribers-' . date('Y-m-d') . '.csv', ['email', 'name', 'status', 'source', 'confirmed_at', 'created_at'], $rows);
    }

    // ---------- सूचियाँ ----------
    public function lists(Request $request): Response
    {
        $edit = $request->int('edit') ? NewsletterList::find($request->int('edit')) : null;
        return $this->view('admin/newsletter/lists', ['items' => $this->formData()['lists'], 'edit' => $edit,
            'categories' => array_column(db()->all("SELECT id, name FROM {p}categories WHERE status = 'active' ORDER BY name"), 'name', 'id')]);
    }

    public function saveList(Request $request): Response
    {
        $v = $this->validate($request, ['name' => 'required|min:2|max:120', 'description' => 'nullable|max:300', 'category_id' => 'nullable|integer'], ['name' => 'नाम', 'description' => 'विवरण', 'category_id' => 'श्रेणी']);
        $data = ['name' => strip_tags((string) $v['name']), 'description' => $v['description'] ? strip_tags((string) $v['description']) : null,
            'category_id' => $v['category_id'] && db()->value('SELECT id FROM {p}categories WHERE id = ?', [(int) $v['category_id']]) ? (int) $v['category_id'] : null,
            'is_public' => $request->bool('is_public') ? 1 : 0, 'is_default' => $request->bool('is_default') ? 1 : 0];
        if ($id = $request->int('id')) {
            NewsletterList::find($id) ?? throw new HttpException(404);
            NewsletterList::update($id, $data);
        } else {
            $id = NewsletterList::create($data);
        }
        AuditService::log('update', 'newsletter', $id, 'न्यूज़लेटर सूची: ' . $data['name']);
        return $this->toRoute('admin.newsletter.lists')->with('success', 'सूची सेव हो गई।');
    }

    public function deleteList(Request $request, int $id): Response
    {
        $l = NewsletterList::find($id) ?? throw new HttpException(404);
        if ((int) db()->value('SELECT COUNT(*) FROM {p}newsletter_lists') <= 1) {
            return $this->back()->with('danger', 'कम से कम एक सूची रहनी चाहिए।');
        }
        NewsletterList::delete($id);
        AuditService::log('delete', 'newsletter', $id, 'सूची हटाई: ' . $l['name']);
        return $this->back()->with('success', 'सूची हटा दी गई (सब्सक्राइबर बने रहेंगे)।');
    }

    // ---------- टेम्पलेट (HTML: सिर्फ़ newsletter.manage) ----------
    public function templates(Request $request): Response
    {
        $edit = $request->int('edit') ? NewsletterTemplate::find($request->int('edit')) : null;
        return $this->view('admin/newsletter/templates', ['items' => db()->all('SELECT id, name, is_default, updated_at FROM {p}newsletter_templates ORDER BY is_default DESC, name'), 'edit' => $edit]);
    }

    public function saveTemplate(Request $request): Response
    {
        $v = $this->validate($request, ['name' => 'required|min:2|max:120', 'html' => 'required|max:200000'], ['name' => 'नाम', 'html' => 'HTML']);
        $html = (string) $request->input('html', '');
        if (!str_contains($html, '{{content}}') || !str_contains($html, '{{unsubscribe_url}}')) {
            throw new ValidationException(['html' => 'टेम्पलेट में {{content}} और {{unsubscribe_url}} दोनों ज़रूरी हैं।'], $request->post());
        }
        $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html) ?? $html; // ईमेल में स्क्रिप्ट चलती नहीं; रखें भी नहीं
        $data = ['name' => strip_tags((string) $v['name']), 'html' => $html, 'is_default' => $request->bool('is_default') ? 1 : 0];
        if ($data['is_default']) {
            db()->query('UPDATE {p}newsletter_templates SET is_default = 0');
        }
        if ($id = $request->int('id')) {
            NewsletterTemplate::find($id) ?? throw new HttpException(404);
            NewsletterTemplate::update($id, $data);
        } else {
            $id = NewsletterTemplate::create($data);
        }
        AuditService::log('update', 'newsletter', $id, 'न्यूज़लेटर टेम्पलेट: ' . $data['name']);
        return $this->toRoute('admin.newsletter.templates', [])->with('success', 'टेम्पलेट सेव हो गया।');
    }

    public function deleteTemplate(Request $request, int $id): Response
    {
        $t = NewsletterTemplate::find($id) ?? throw new HttpException(404);
        if ($t['is_default'] || (int) db()->value('SELECT COUNT(*) FROM {p}newsletter_templates') <= 1) {
            return $this->back()->with('danger', 'डिफ़ॉल्ट टेम्पलेट नहीं हट सकता।');
        }
        NewsletterTemplate::delete($id);
        return $this->back()->with('success', 'टेम्पलेट हटा दिया गया।');
    }
}
