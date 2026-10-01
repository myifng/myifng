<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Models\Assignment;
use App\Services\AuditService;
use App\Services\FormService;
use App\Services\NewsService;
use App\Services\NotificationService;
use App\Services\PrivateFileService;

/** जमा फ़ॉर्म का इनबॉक्स: संपर्क, न्यूज़ टिप, शिकायत, नौकरी आवेदन, कस्टम */
final class SubmissionController extends Controller
{
    private function guard(string $type, string $action = 'view'): void
    {
        if (!isset(FormService::TYPES[$type]) || !FormService::can($type, $action)) {
            throw new HttpException(isset(FormService::TYPES[$type]) ? 403 : 404);
        }
    }

    public function inbox(Request $request, string $type): Response
    {
        $this->guard($type);
        $t = FormService::TYPES[$type];
        $where = ['f.type = ?'];
        $params = [$type];
        $status = $request->str('status');
        if (isset($t[3][$status])) {
            $where[] = 's.status = ?';
            $params[] = $status;
        } elseif ($status === '') {
            $where[] = "s.status NOT IN ('spam','closed','rejected')"; // डिफ़ॉल्ट: काम वाले
        }
        foreach (['form' => 's.form_id', 'job' => 's.job_id', 'assigned' => 's.assigned_to'] as $k => $col) {
            if ($v = $request->int($k)) {
                $where[] = "$col = ?";
                $params[] = $v;
            }
        }
        if ($request->str('assigned') === 'me') {
            $where[] = 's.assigned_to = ?';
            $params[] = auth()->id();
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(s.ref_no LIKE ? OR s.name LIKE ? OR s.email LIKE ? OR s.mobile LIKE ? OR s.data LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id WHERE $w", $params);
        $items = db()->all("SELECT s.*, f.title form_title, u.name assignee, j.title job_title FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id
            LEFT JOIN {p}users u ON u.id = s.assigned_to LEFT JOIN {p}jobs j ON j.id = s.job_id WHERE $w ORDER BY s.status = 'new' DESC, s.created_at DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $tally = array_column(db()->all('SELECT s.status, COUNT(*) c FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id WHERE f.type = ? GROUP BY s.status', [$type]), 'c', 'status');
        return $this->view('admin/submissions/index', ['type' => $type, 't' => $t, 'items' => new Paginator($items, $total, 30, $page), 'status' => $status, 'q' => $q, 'tally' => $tally,
            'forms' => array_column(db()->all('SELECT id, title FROM {p}forms WHERE type = ? ORDER BY title', [$type]), 'title', 'id'), 'formId' => $request->int('form'),
            'jobs' => $type === 'career' ? array_column(db()->all('SELECT id, title FROM {p}jobs ORDER BY id DESC'), 'title', 'id') : [], 'jobId' => $request->int('job')]);
    }

    /** साइडबार के लिए अलग नाम वाले रूट */
    public function tips(Request $request): Response
    {
        return $this->inbox($request, 'news_tip');
    }

    public function complaints(Request $request): Response
    {
        return $this->inbox($request, 'complaint');
    }

    public function contacts(Request $request): Response
    {
        return $this->inbox($request, 'contact');
    }

    private function find(int $id): array
    {
        $s = db()->first('SELECT s.*, f.title form_title, f.type, f.slug form_slug, j.title job_title, j.slug job_slug, u.name assignee FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id
            LEFT JOIN {p}jobs j ON j.id = s.job_id LEFT JOIN {p}users u ON u.id = s.assigned_to WHERE s.id = ?', [$id]) ?? throw new HttpException(404);
        $this->guard($s['type']);
        $s['data'] = json_decode((string) $s['data'], true) ?: [];
        $s['files'] = json_decode((string) $s['files'], true) ?: [];
        return $s;
    }

    public function show(Request $request, int $id): Response
    {
        $s = $this->find($id);
        $notes = db()->all('SELECT n.*, u.name FROM {p}form_notes n LEFT JOIN {p}users u ON u.id = n.user_id WHERE n.submission_id = ? ORDER BY n.id DESC', [$id]);
        $staff = array_column(db()->all("SELECT id, name FROM {p}users WHERE status = 'active' AND deleted_at IS NULL ORDER BY name"), 'name', 'id');
        $others = $s['email'] || $s['mobile'] ? (int) db()->value('SELECT COUNT(*) FROM {p}form_submissions WHERE id <> ? AND ((email <> \'\' AND email = ?) OR (mobile <> \'\' AND mobile = ?))', [$id, (string) $s['email'], (string) $s['mobile']]) : 0;
        $reporters = $s['type'] === 'news_tip' ? array_column(NewsService::reporters(), 'name', 'id') : [];
        return $this->view('admin/submissions/show', ['s' => $s, 't' => FormService::TYPES[$s['type']], 'notes' => $notes, 'staff' => $staff, 'others' => $others, 'reporters' => $reporters,
            'categories' => $s['type'] === 'news_tip' ? array_column(db()->all("SELECT id, name FROM {p}categories WHERE status = 'active' ORDER BY name"), 'name', 'id') : []]);
    }

    /** स्थिति / सौंपना / सार्वजनिक जवाब / निपटारा */
    public function update(Request $request, int $id): Response
    {
        $s = $this->find($id);
        $this->guard($s['type'], $s['type'] === 'career' ? 'edit' : ($s['type'] === 'complaint' ? 'edit' : 'edit'));
        $t = FormService::TYPES[$s['type']];
        $data = [];
        $log = [];
        $status = $request->str('status');
        if ($status !== '' && isset($t[3][$status]) && $status !== $s['status']) {
            $data['status'] = $status;
            $log[] = 'स्थिति: ' . ($t[3][$s['status']][0] ?? $s['status']) . ' → ' . $t[3][$status][0];
        }
        if ($request->input('assigned_to') !== null) {
            $to = $request->int('assigned_to') ?: null;
            if ($to && !db()->value("SELECT id FROM {p}users WHERE id = ? AND status = 'active'", [$to])) {
                $to = null;
            }
            if ($to !== ($s['assigned_to'] ? (int) $s['assigned_to'] : null)) {
                $data['assigned_to'] = $to;
                $log[] = $to ? 'सौंपा: ' . db()->value('SELECT name FROM {p}users WHERE id = ?', [$to]) : 'सौंपना हटाया';
                if ($to && $to !== (int) auth()->id()) {
                    NotificationService::notify('form', ['users' => [$to]], ['title' => 'आपको सौंपा गया: ' . $s['ref_no'], 'body' => $s['form_title'], 'url' => route('admin.submissions.show', ['id' => $id])]);
                }
            }
        }
        foreach (['response' => 'जवाब', 'resolution' => 'निपटारा'] as $col => $label) {
            if ($request->input($col) !== null) {
                $val = trim(strip_tags($request->str($col)));
                $val = $val !== '' ? mb_substr($val, 0, $col === 'resolution' ? 500 : 5000) : null;
                if ($val !== $s[$col]) {
                    $data[$col] = $val;
                    $log[] = $label . ' अपडेट';
                }
            }
        }
        if (!$data) {
            return $this->back()->with('info', 'कोई बदलाव नहीं।');
        }
        db()->update('form_submissions', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        FormService::note($id, 'status', implode(' · ', $log));
        // शिकायत: स्थिति/जवाब बदलने पर शिकायतकर्ता को ईमेल
        if ($s['type'] === 'complaint' && $s['email'] && (isset($data['status']) || isset($data['response']) || isset($data['resolution'])) && $request->bool('notify_applicant')) {
            $st = $t[3][$data['status'] ?? $s['status']][0];
            $sent = NotificationService::mail((string) $s['email'], 'शिकायत ' . $s['ref_no'] . ': ' . $st, \App\Services\MailTemplate::title('शिकायत की स्थिति: ' . $st, '📋')
                . \App\Services\MailTemplate::info([['संदर्भ नंबर', (string) $s['ref_no']], ['स्थिति', (string) $st]])
                . (($r = $data['response'] ?? $s['response']) ? \App\Services\MailTemplate::note('<b>जवाब:</b><br>' . nl2br(e((string) $r))) : '')
                . (($r = $data['resolution'] ?? $s['resolution']) ? \App\Services\MailTemplate::note('<b>निपटारा:</b><br>' . nl2br(e((string) $r)), 'ok') : '')
                . (setting('complaint_tracking', '1') === '1' ? \App\Services\MailTemplate::button(route('complaint.track') . '?ref=' . rawurlencode((string) $s['ref_no']), 'शिकायत की स्थिति देखें') : ''));
            FormService::note($id, 'system', $sent ? 'शिकायतकर्ता को ईमेल भेजा' : 'शिकायतकर्ता को ईमेल नहीं जा सका (mail())');
        }
        AuditService::log('update', FormService::TYPES[$s['type']][1], $id, $s['ref_no'] . ': ' . implode(' · ', $log));
        return $this->back()->with('success', 'सेव हो गया।');
    }

    public function note(Request $request, int $id): Response
    {
        $s = $this->find($id);
        $v = $this->validate($request, ['body' => 'required|min:2|max:5000'], ['body' => 'नोट']);
        FormService::note($id, 'note', trim(strip_tags((string) $v['body'])));
        db()->query('UPDATE {p}form_submissions SET updated_at = NOW() WHERE id = ?', [$id]);
        return $this->back()->with('success', 'नोट जुड़ गया।');
    }

    /** भेजने वाले को ईमेल से जवाब (और स्थिति "जवाब दिया") */
    public function reply(Request $request, int $id): Response
    {
        $s = $this->find($id);
        $this->guard($s['type'], 'edit');
        if (!$s['email']) {
            return $this->back()->with('danger', 'इस फ़ॉर्म में ईमेल नहीं है।');
        }
        $v = $this->validate($request, ['subject' => 'required|max:190', 'body' => 'required|min:2|max:10000'], ['subject' => 'विषय', 'body' => 'जवाब']);
        $body = trim(strip_tags((string) $v['body']));
        $ok = NotificationService::mail((string) $s['email'], strip_tags((string) $v['subject']), \App\Services\MailTemplate::p(nl2br(e($body))) . \App\Services\MailTemplate::p('संदर्भ: <b>' . e((string) $s['ref_no']) . '</b>', 'font-size:13px;color:#6b6770'));
        FormService::note($id, 'reply', "विषय: {$v['subject']}\n\n" . $body . ($ok ? '' : "\n\n(ईमेल नहीं जा सका)"));
        $next = isset(FormService::TYPES[$s['type']][3]['replied']) ? 'replied' : null;
        if ($ok && $next && in_array($s['status'], ['new', 'in_progress'], true)) {
            db()->query('UPDATE {p}form_submissions SET status = ?, updated_at = NOW() WHERE id = ?', [$next, $id]);
        }
        return $this->back()->with($ok ? 'success' : 'warning', $ok ? 'जवाब ईमेल से भेज दिया।' : 'जवाब नोट में सेव हुआ, पर ईमेल नहीं जा सका (सर्वर का mail() देखें)।');
    }

    /** फ़ाइल: अनुमति वाले ही देख सकते हैं */
    public function file(Request $request, int $id, string $key): Response
    {
        $s = $this->find($id);
        $f = $s['files'][$key] ?? throw new HttpException(404);
        AuditService::log('view', FormService::TYPES[$s['type']][1], $id, $s['ref_no'] . ': फ़ाइल देखी (' . $key . ')');
        $inline = !$request->bool('download') && preg_match('~^(image/|application/pdf|video/)~', (string) $f['mime']);
        return PrivateFileService::send((string) $f['path'], $s['ref_no'] . '-' . $key, (bool) $inline);
    }

    /** न्यूज़ टिप → असाइनमेंट या ड्राफ़्ट ख़बर */
    public function convert(Request $request, int $id): Response
    {
        $s = $this->find($id);
        if ($s['type'] !== 'news_tip') {
            throw new HttpException(404);
        }
        $this->guard('news_tip', 'approve');
        $desc = (string) ($s['data']['description'][1] ?? '');
        $place = (string) ($s['data']['location'][1] ?? '');
        $anon = !empty($s['data']['anonymous']);
        $title = trim($request->str('title')) ?: \App\Helpers\Str::limit($desc, 90);
        $source = 'पाठक द्वारा भेजी गई (' . $s['ref_no'] . ')' . ($anon ? '' : ': ' . $s['name']);
        if ($request->str('to') === 'assignment') {
            if (!can('assignments.create')) {
                throw new HttpException(403);
            }
            $rid = $request->int('reporter_id');
            if (!in_array($rid, array_map('intval', array_column(NewsService::reporters(), 'id')), true)) {
                return $this->back()->with('danger', 'रिपोर्टर चुनें।');
            }
            $aid = Assignment::create(['title' => mb_substr($title, 0, 190), 'description' => $desc . ($place ? "\n\nजगह: $place" : '') . "\n\nस्रोत: $source" . ($s['mobile'] && !$anon ? "\nसंपर्क: " . $s['mobile'] : ''),
                'reporter_id' => $rid, 'category_id' => $request->int('category_id') ?: null, 'priority' => 'normal', 'status' => 'open', 'created_by' => auth()->id()]);
            \App\Services\NotifyEvents::assignment(['title' => $title, 'reporter_id' => $rid]);
            $conv = 'assignment:' . $aid;
            $msg = 'असाइनमेंट बना और रिपोर्टर को सौंपा।';
            $url = route('admin.assignments.edit', ['id' => $aid]);
        } else {
            if (!can('news.create')) {
                throw new HttpException(403);
            }
            $content = '<p>' . nl2br(e($desc)) . '</p>' . ($place ? '<p>जगह: ' . e($place) . '</p>' : '');
            $nid = NewsService::save(null, ['title' => mb_substr($title, 0, 255), 'slug' => NewsService::uniqueSlug($title), 'content' => $content, 'source' => mb_substr($source, 0, 150),
                'category_id' => $request->int('category_id') ?: null, 'reporter_id' => auth()->id(), 'robots' => 'index,follow'], ['topics' => [], 'tags' => [], 'related' => [], 'gallery' => []], 'न्यूज़ टिप ' . $s['ref_no'] . ' से');
            $conv = 'news:' . $nid;
            $msg = 'ड्राफ़्ट ख़बर बनी। फ़ोटो/वीडियो इस टिप से देखकर मीडिया लाइब्रेरी में डालें।';
            $url = route('admin.news.edit', ['id' => $nid]);
        }
        db()->query("UPDATE {p}form_submissions SET status = 'converted', converted = ?, updated_at = NOW() WHERE id = ?", [$conv, $id]);
        FormService::note($id, 'system', $msg);
        AuditService::log('convert', 'news_tips', $id, $s['ref_no'] . ' → ' . $conv);
        return $this->redirect($url)->with('success', $msg);
    }

    public function bulk(Request $request, string $type): Response
    {
        $this->guard($type, 'edit');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        $action = $request->str('action');
        $t = FormService::TYPES[$type];
        if (!$ids || !($action === 'delete' || isset($t[3][$action]))) {
            return $this->back()->with('warning', 'कुछ नहीं चुना गया।');
        }
        $in = Database::in($ids);
        $scope = "id IN ($in) AND form_id IN (SELECT id FROM {p}forms WHERE type = ?)";
        if ($action === 'delete') {
            $this->guard($type, 'delete');
            foreach (db()->all("SELECT files FROM {p}form_submissions WHERE $scope", [...$ids, $type]) as $r) {
                foreach ((array) json_decode((string) $r['files'], true) as $f) {
                    if ($abs = PrivateFileService::absolute($f['path'] ?? null)) {
                        @unlink($abs);
                    }
                }
            }
            $n = db()->query("DELETE FROM {p}form_submissions WHERE $scope", [...$ids, $type])->rowCount();
        } else {
            $n = db()->query("UPDATE {p}form_submissions SET status = ?, updated_at = NOW() WHERE $scope", [$action, ...$ids, $type])->rowCount();
        }
        AuditService::log($action === 'delete' ? 'delete' : 'update', $t[1], null, "जमा फ़ॉर्म: $action ($n)");
        return $this->back()->with('success', "$n पर काम हुआ।");
    }

    public function export(Request $request, string $type): Response
    {
        $this->guard($type, $type === 'news_tip' ? 'view' : 'export');
        $rows = db()->all('SELECT s.*, f.title form_title, j.title job_title FROM {p}form_submissions s JOIN {p}forms f ON f.id = s.form_id LEFT JOIN {p}jobs j ON j.id = s.job_id WHERE f.type = ? ORDER BY s.id', [$type]);
        $keys = [];
        foreach ($rows as $r) {
            foreach ((array) json_decode((string) $r['data'], true) as $k => [$label]) {
                $keys[$k] ??= $label;
            }
        }
        AuditService::log('export', FormService::TYPES[$type][1], null, 'CSV (' . count($rows) . ')');
        $st = FormService::TYPES[$type][3];
        return Response::csv($type . '-' . date('Y-m-d') . '.csv', array_merge(['नंबर', 'फ़ॉर्म', 'स्थिति', 'तारीख़'], $type === 'career' ? ['पद'] : [], array_values($keys)),
            (static function () use ($rows, $keys, $st, $type) {
                foreach ($rows as $r) {
                    $d = (array) json_decode((string) $r['data'], true);
                    yield array_merge([$r['ref_no'], $r['form_title'], $st[$r['status']][0] ?? $r['status'], $r['created_at']], $type === 'career' ? [$r['job_title']] : [],
                        array_map(static fn($k) => isset($d[$k]) ? (is_array($d[$k][1]) ? implode(', ', $d[$k][1]) : $d[$k][1]) : '', array_keys($keys)));
                }
            })());
    }
}
