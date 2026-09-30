<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Location;
use App\Models\News;
use App\Repositories\NewsRepository;
use App\Services\AuditService;
use App\Services\HtmlSanitizer;
use App\Services\MediaService;
use App\Services\NewsService;
use App\Services\NewsWorkflow;
use App\Validators\NewsValidator;

/** ख़बरें: सूची, लिखना/बदलना, प्रीव्यू, कॉपी, ट्रैश, बल्क, एक्सपोर्ट। स्थिति के काम NewsWorkflowController में। */
final class NewsController extends Controller
{
    private const CONTENT_MAX = 500_000;

    /** ID से ख़बर + पहुँच जाँच (दूसरे रिपोर्टर की ख़बर पर 404, ताकि पता भी न चले) */
    public static function findVisible(int $id, bool $withTrashed = false): array
    {
        $n = News::find($id, $withTrashed);
        if (!$n || !NewsService::canView($n)) {
            throw new HttpException(404);
        }
        return $n;
    }

    private function filters(Request $request): array
    {
        return [
            'status' => $request->str('status'), 'q' => mb_substr($request->str('q'), 0, 100), 'category' => $request->int('category'),
            'reporter' => $request->int('reporter'), 'location' => $request->int('location'), 'flag' => $request->str('flag'),
            'from' => $request->str('from'), 'to' => $request->str('to'), 'mine' => $request->bool('mine'),
        ];
    }

    public function index(Request $request): Response
    {
        $repo = new NewsRepository();
        $f = $this->filters($request);
        return $this->view('admin/news/index', [
            'news' => $repo->paginate($f, max(1, $request->int('page', 1))), 'filters' => $f, 'counts' => $repo->counts($f['mine']),
            'categories' => Category::options(), 'reporters' => NewsService::seesAll() ? NewsService::reporters() : [],
            'filterLocation' => $f['location'] ? Location::find($f['location']) : null,
        ]);
    }

    public function create(Request $request): Response
    {
        $assignment = null;
        if ($aid = $request->int('assignment')) {
            $assignment = db()->first('SELECT * FROM {p}assignments WHERE id = ?', [$aid]);
            if (!$assignment || ((int) $assignment['reporter_id'] !== (int) auth()->id() && !can('assignments.manage'))) {
                throw new HttpException(404);
            }
        }
        return $this->form(null, ['assignment' => $assignment]);
    }

    private function form(?array $news, array $extra = []): Response
    {
        $rel = $news ? NewsService::relations((int) $news['id']) : ['topics' => [], 'tags' => [], 'related' => [], 'gallery' => []];
        $loc = null;
        $locId = $news['location_id'] ?? ($extra['assignment']['location_id'] ?? null);
        if ($locId) {
            $loc = Location::find((int) $locId);
            $loc['chain'] = implode(' › ', array_column([...Location::ancestors($loc), $loc], 'name'));
        }
        return $this->view('admin/news/form', $extra + [
            'news' => $news, 'rel' => $rel, 'location' => $loc, 'assignment' => $extra['assignment'] ?? null,
            'categories' => Category::options(), 'topics' => db()->all("SELECT id, name, type FROM {p}topics WHERE status = 'active' ORDER BY is_featured DESC, name"),
            'reporters' => can('news.approve') ? NewsService::reporters() : [],
            'actions' => $news ? NewsWorkflow::available($news) : (can('news.publish') ? ['publish' => 1, 'schedule' => 1, 'submit' => 1] : ['submit' => 1]),
            'remarks' => $news ? $this->visibleRemarks((int) $news['id']) : [],
        ]);
    }

    /** रिपोर्टर को आंतरिक (note) टिप्पणियाँ नहीं दिखतीं */
    public static function visibleRemarks(int $id): array
    {
        $internal = can('news.approve') ? '' : " AND r.type <> 'note'";
        return db()->all("SELECT r.*, u.name AS user FROM {p}news_remarks r LEFT JOIN {p}users u ON u.id = r.user_id WHERE r.news_id = ?$internal ORDER BY r.id DESC LIMIT 100", [$id]);
    }

    public function store(Request $request): Response
    {
        [$data, $rel] = $this->payload($request, null);
        if ($aid = $request->int('assignment_id')) {
            $a = db()->first('SELECT * FROM {p}assignments WHERE id = ?', [$aid]);
            if ($a && ((int) $a['reporter_id'] === (int) auth()->id() || can('assignments.manage'))) {
                $data['assignment_id'] = $aid;
            }
        }
        $data['language_id'] = db()->value('SELECT id FROM {p}languages WHERE code = ?', [setting('language', 'hi')]) ?: null;
        $id = NewsService::save(null, $data, $rel, 'पहला ड्राफ़्ट');
        if (!empty($data['assignment_id'])) {
            db()->query("UPDATE {p}assignments SET news_id = COALESCE(news_id, ?), status = IF(status IN ('open','accepted'), 'in_progress', status), updated_at = NOW() WHERE id = ?", [$id, $data['assignment_id']]);
        }
        AuditService::log('create', 'news', $id, 'नई ख़बर (ड्राफ़्ट): ' . $data['title']);
        return $this->afterSave($request, $id, 'ख़बर ड्राफ़्ट के रूप में सेव हो गई।');
    }

    public function edit(Request $request, int $id): Response
    {
        $news = self::findVisible($id);
        if (!NewsService::canEdit($news)) {
            return $this->toRoute('admin.news.history', ['id' => $id])->with('info', 'इस स्थिति (' . NewsWorkflow::label($news['status']) . ') में आप यह ख़बर बदल नहीं सकते। यहाँ देख सकते हैं।');
        }
        return $this->form($news);
    }

    public function update(Request $request, int $id): Response
    {
        $news = self::findVisible($id);
        if (!NewsService::canEdit($news)) {
            throw new HttpException(403, 'इस स्थिति में आप यह ख़बर नहीं बदल सकते।');
        }
        // बीच में किसी और ने सेव किया? चुपचाप ओवरराइट नहीं
        if ($request->str('updated_at') !== '' && $request->str('updated_at') !== $news['updated_at']) {
            throw new ValidationException(['title' => 'आपके खोलने के बाद इस ख़बर को किसी और ने बदला है। आपके बदलाव सेव नहीं हुए; पेज दोबारा खोलकर (हिस्ट्री देखकर) फिर से करें।'], $request->post());
        }
        [$data, $rel] = $this->payload($request, $news);
        $reason = trim($request->str('change_reason'));
        if ($news['status'] === 'published' && mb_strlen($reason) < 5) {
            throw new ValidationException(['change_reason' => 'प्रकाशित ख़बर बदलने का कारण लिखें (कम से कम 5 अक्षर)।'], $request->post());
        }
        $correction = $news['status'] === 'published' && $request->bool('is_correction');
        NewsService::save($news, $data, $rel, $reason, $correction);
        AuditService::log($correction ? 'correct' : 'update', 'news', $id, ($correction ? 'सार्वजनिक सुधार: ' : 'ख़बर बदली: ') . $data['title'] . ($reason ? " ($reason)" : ''),
            array_intersect_key($news, $data), $data);
        return $this->afterSave($request, $id, 'बदलाव सेव हो गए।' . ($correction ? ' वेबसाइट पर सुधार सूचना दिखेगी।' : ''));
    }

    /** सेव के बाद "सेव करके भेजें/प्रकाशित करें" */
    private function afterSave(Request $request, int $id, string $msg): Response
    {
        $then = $request->str('then');
        if ($then !== '' && isset(NewsWorkflow::ACTIONS[$then]) && !NewsWorkflow::needsRemark($then)) {
            $err = NewsService::transition(News::find($id), $then, '', $request->str('scheduled_at') ?: null);
            if ($err) {
                return $this->toRoute('admin.news.edit', ['id' => $id])->with('warning', $msg . ' लेकिन: ' . $err);
            }
            $msg .= ' स्थिति: ' . NewsWorkflow::label(NewsWorkflow::target($then)) . '।';
            $n = News::find($id);
            return (NewsService::canEdit($n) ? $this->toRoute('admin.news.edit', ['id' => $id]) : $this->toRoute('admin.news.index'))->with('success', $msg);
        }
        return $this->toRoute('admin.news.edit', ['id' => $id])->with('success', $msg);
    }

    /** फ़ॉर्म → सुरक्षित डेटा + रिश्ते */
    private function payload(Request $request, ?array $news): array
    {
        $v = $this->validate($request, NewsValidator::rules(), NewsValidator::LABELS);
        $errors = [];
        $id = (int) ($news['id'] ?? 0);
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($v['title'])));
        if ($title === '') {
            $errors['title'] = 'शीर्षक में सादा टेक्स्ट लिखें।';
        }
        $content = (string) $request->input('content', '');
        if (strlen($content) > self::CONTENT_MAX) {
            $errors['content'] = 'लेख बहुत लंबा है (500 KB से ज़्यादा)।';
        }
        if (!isset(News::ROBOTS[$v['robots']])) {
            $errors['robots'] = 'सर्च इंजन का चुना गया मान मान्य नहीं है।';
        }
        foreach (['featured_image' => 'image', 'og_image' => 'image', 'audio_file' => 'audio'] as $f => $kind) {
            $val = $request->str($f);
            if ($val !== ($news[$f] ?? '') && !MediaService::validPath($val, $kind)) {
                $errors[$f] = $kind === 'image' ? 'मुख्य इमेज मीडिया लाइब्रेरी से चुनें।' : 'ऑडियो मीडिया लाइब्रेरी से चुनें।';
            }
        }
        $video = (string) $v['video_url'];
        if ($video !== '' && !preg_match('~^https://(www\.|m\.)?(youtube\.com/(watch\?v=|shorts/|live/|embed/)|youtu\.be/)[A-Za-z0-9_-]{11}~', $video) && !MediaService::validPath($video, 'video')) {
            $errors['video_url'] = 'वीडियो YouTube का लिंक हो या मीडिया लाइब्रेरी का वीडियो।';
        }
        // रिपोर्टर: डेस्क बदल सकता है; बाकी के लिए ख़ुद (या मौजूदा)
        $reporter = $news['reporter_id'] ?? auth()->id();
        if (can('news.approve') && $v['reporter_id']) {
            if (!in_array((int) $v['reporter_id'], array_map('intval', array_column(NewsService::reporters(), 'id')), true)) {
                $errors['reporter_id'] = 'यह यूज़र रिपोर्टर नहीं चुना जा सकता।';
            }
            $reporter = (int) $v['reporter_id'];
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }

        $slug = $v['slug'] ?: ($news['slug'] ?? '');
        if ($slug === '' || ($v['slug'] && $v['slug'] !== ($news['slug'] ?? ''))) {
            $slug = NewsService::uniqueSlug($slug ?: $title, $id);
        }
        $data = [
            'title' => $title, 'subtitle' => $v['subtitle'] ? strip_tags($v['subtitle']) : null, 'slug' => $slug,
            'summary' => $v['summary'] ? strip_tags($v['summary']) : null,
            'content' => HtmlSanitizer::clean($content, (string) config('app.url')),
            'featured_image' => $request->str('featured_image') ?: null, 'image_caption' => $v['image_caption'], 'image_credit' => $v['image_credit'],
            'video_url' => $video ?: null, 'audio_file' => $request->str('audio_file') ?: null,
            'category_id' => $v['category_id'] ? (int) $v['category_id'] : null, 'location_id' => $v['location_id'] ? (int) $v['location_id'] : null,
            'reporter_id' => $reporter, 'source' => $v['source'], 'news_credit' => $v['news_credit'],
            'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'meta_keywords' => $v['meta_keywords'],
            'canonical_url' => $v['canonical_url'], 'robots' => $v['robots'],
            // Phase 10: फ़ोकस कीवर्ड, सोशल शेयर, FAQ
            'focus_keyword' => $v['focus_keyword'] ? trim(strip_tags((string) $v['focus_keyword'])) : null,
            'og_title' => $v['og_title'] ? trim(strip_tags((string) $v['og_title'])) : null,
            'og_description' => $v['og_description'] ? trim(strip_tags((string) $v['og_description'])) : null,
            'og_image' => $request->str('og_image') ?: null,
            'faq' => self::faq($request),
            'allow_comments' => $request->bool('allow_comments') ? 1 : 0,
        ];
        // फ़्लैग सिर्फ़ डेस्क
        if (can('news.approve')) {
            foreach (array_keys(News::FLAGS) as $flag) {
                $data[$flag] = $request->bool($flag) ? 1 : 0;
            }
        }

        $ids = static fn(string $k) => array_values(array_unique(array_filter(array_map('intval', (array) $request->input($k, [])))));
        $topics = $ids('topics');
        if ($topics) {
            $topics = array_map('intval', array_column(db()->all("SELECT id FROM {p}topics WHERE status = 'active' AND id IN (" . Database::in($topics) . ')', $topics), 'id'));
        }
        $related = array_slice($ids('related'), 0, NewsService::MAX_RELATED);
        if ($related) {
            [$scope, $sp] = NewsService::scope('n');
            $found = array_map('intval', array_column(db()->all("SELECT n.id FROM {p}news n WHERE $scope AND n.deleted_at IS NULL AND n.id IN (" . Database::in($related) . ')', [...$sp, ...$related]), 'id'));
            $related = array_values(array_intersect($related, $found));
        }
        $gallery = array_slice($ids('gallery'), 0, NewsService::MAX_GALLERY);
        if ($gallery) {
            $found = array_map('intval', array_column(db()->all("SELECT id FROM {p}media WHERE kind = 'image' AND deleted_at IS NULL AND id IN (" . Database::in($gallery) . ')', $gallery), 'id'));
            $gallery = array_values(array_intersect($gallery, $found));
        }
        $tags = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،\n]+/u', $request->str('tags')) ?: []))));
        if (count($tags) > NewsService::MAX_TAGS) {
            throw new ValidationException(['tags' => 'ज़्यादा से ज़्यादा ' . NewsService::MAX_TAGS . ' टैग।'], $request->post());
        }
        return [$data, ['topics' => $topics, 'tags' => $tags, 'related' => $related, 'gallery' => $gallery]];
    }

    /** FAQ पंक्तियाँ (सवाल-जवाब, 10 तक) → JSON या null */
    private static function faq(Request $request): ?string
    {
        $q = (array) $request->input('faq_q', []);
        $a = (array) $request->input('faq_a', []);
        $out = [];
        foreach ($q as $i => $question) {
            $question = trim(strip_tags((string) $question));
            $answer = trim(strip_tags((string) ($a[$i] ?? '')));
            if ($question === '' && $answer === '') {
                continue;
            }
            if ($question === '' || $answer === '') {
                throw new ValidationException(['faq' => 'FAQ की हर पंक्ति में सवाल और जवाब दोनों लिखें।'], $request->post());
            }
            $out[] = ['q' => mb_substr($question, 0, 300), 'a' => mb_substr($answer, 0, 2000)];
        }
        if (count($out) > 10) {
            throw new ValidationException(['faq' => 'ज़्यादा से ज़्यादा 10 सवाल।'], $request->post());
        }
        return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /** प्रीव्यू: वेबसाइट के लेआउट में, noindex */
    public function preview(Request $request, int $id): Response
    {
        $news = self::findVisible($id, true);
        return (new \App\Controllers\Front\NewsController())->render($news, true);
    }

    public function duplicate(Request $request, int $id): Response
    {
        $news = self::findVisible($id);
        $copy = array_intersect_key($news, array_flip(['subtitle', 'summary', 'content', 'featured_image', 'image_caption', 'image_credit', 'video_url', 'audio_file',
            'category_id', 'location_id', 'source', 'news_credit', 'meta_description', 'meta_keywords', 'robots', 'language_id', 'focus_keyword', 'og_title', 'og_description', 'og_image', 'faq', 'allow_comments']));
        $copy['title'] = $news['title'] . ' (कॉपी)';
        $copy['slug'] = NewsService::uniqueSlug($news['slug'] . '-copy');
        $copy['reporter_id'] = auth()->id();
        $rel = NewsService::relations($id);
        $new = NewsService::save(null, $copy, ['topics' => $rel['topics'], 'tags' => $rel['tags'], 'related' => [], 'gallery' => array_map('intval', array_column($rel['gallery'], 'id'))], 'ख़बर #' . $id . ' की कॉपी');
        AuditService::log('duplicate', 'news', $new, 'ख़बर की कॉपी: ' . $news['title']);
        return $this->toRoute('admin.news.edit', ['id' => $new])->with('success', 'कॉपी ड्राफ़्ट के रूप में बन गई।');
    }

    public function trash(Request $request, int $id): Response
    {
        $news = self::findVisible($id);
        if (!can('news.approve') && !(NewsService::isOwner($news) && in_array($news['status'], NewsWorkflow::OWNER_EDITABLE, true))) {
            throw new HttpException(403, 'भेजी गई या प्रकाशित ख़बर सिर्फ़ डेस्क ट्रैश कर सकता है।');
        }
        News::delete($id);
        NewsService::recountTags(array_map('intval', array_column(db()->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$id]), 'tag_id')));
        NewsService::changed();
        AuditService::log('trash', 'news', $id, 'ख़बर ट्रैश में: ' . $news['title']);
        return $this->toRoute('admin.news.index')->with('success', '“' . $news['title'] . '” ट्रैश में चली गई' . ($news['status'] === 'published' ? ' और वेबसाइट से हट गई' : '') . '।');
    }

    public function restore(Request $request, int $id): Response
    {
        $news = self::findVisible($id, true);
        News::restore($id);
        NewsService::recountTags(array_map('intval', array_column(db()->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$id]), 'tag_id')));
        NewsService::changed();
        AuditService::log('restore', 'news', $id, 'ख़बर वापस लाई: ' . $news['title']);
        return $this->back()->with('success', '“' . $news['title'] . '” वापस आ गई (स्थिति: ' . NewsWorkflow::label($news['status']) . ')।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $news = self::findVisible($id, true);
        if ($news['deleted_at'] === null) {
            return $this->back()->with('danger', 'स्थायी रूप से हटाने से पहले ख़बर को ट्रैश में डालें।');
        }
        if (!can('news.approve') && !NewsService::isOwner($news)) {
            throw new HttpException(403);
        }
        $tags = array_map('intval', array_column(db()->all('SELECT tag_id FROM {p}news_tags WHERE news_id = ?', [$id]), 'tag_id'));
        News::forceDelete($id);
        NewsService::recountTags($tags);
        NewsService::changed();
        AuditService::log('delete', 'news', $id, 'ख़बर स्थायी रूप से हटाई: ' . $news['title'], ['title' => $news['title'], 'slug' => $news['slug'], 'status' => $news['status']]);
        return $this->back()->with('success', 'ख़बर और उसकी पूरी हिस्ट्री स्थायी रूप से हटा दी गई।');
    }

    /** बल्क: वर्कफ़्लो के काम (हर ख़बर पर नियम अलग से जाँचे जाते हैं) या ट्रैश/वापस/हटाएँ */
    public function bulk(Request $request): Response
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', []))))), 0, 200);
        $action = $request->str('action');
        if (!$ids || !in_array($action, ['submit', 'approve', 'publish', 'archive', 'trash', 'restore', 'delete'], true)) {
            return $this->back()->with('warning', 'पहले ख़बरें और काम चुनें।');
        }
        $ok = 0;
        $fail = [];
        foreach ($ids as $id) {
            $n = News::find($id, true);
            if (!$n || !NewsService::canView($n)) {
                continue;
            }
            switch ($action) {
                case 'trash':
                    if ($n['deleted_at'] === null && can('news.delete') && (can('news.approve') || (NewsService::isOwner($n) && in_array($n['status'], NewsWorkflow::OWNER_EDITABLE, true)))) {
                        News::delete($id);
                        $ok++;
                    } else {
                        $fail[] = $id;
                    }
                    break;
                case 'restore':
                    if ($n['deleted_at'] !== null && can('news.delete')) {
                        News::restore($id);
                        $ok++;
                    }
                    break;
                case 'delete':
                    if ($n['deleted_at'] !== null && can('news.delete') && (can('news.approve') || NewsService::isOwner($n))) {
                        News::forceDelete($id);
                        $ok++;
                    } else {
                        $fail[] = $id;
                    }
                    break;
                default:
                    $err = NewsService::transition($n, $action);
                    $err === null ? $ok++ : $fail[] = $id;
            }
        }
        if (in_array($action, ['trash', 'restore', 'delete'], true)) {
            AuditService::log('bulk_' . $action, 'news', implode(',', $ids), "$ok ख़बरों पर बल्क काम: $action");
            db()->query("UPDATE {p}tags t SET usage_count = (SELECT COUNT(*) FROM {p}news_tags nt JOIN {p}news n ON n.id = nt.news_id WHERE nt.tag_id = t.id AND n.status = 'published' AND n.deleted_at IS NULL)");
        }
        NewsService::changed();
        $msg = "$ok ख़बरों पर काम हुआ।" . ($fail ? ' ' . count($fail) . ' पर नहीं हो सका (स्थिति या अनुमति): #' . implode(', #', array_slice($fail, 0, 10)) : '');
        return $this->back()->with($fail ? 'warning' : 'success', $msg);
    }

    public function export(Request $request): Response
    {
        $rows = (new NewsRepository())->export($this->filters($request));
        AuditService::log('export', 'news', null, count($rows) . ' ख़बरें CSV में');
        return Response::csv('news-' . date('Y-m-d') . '.csv', ['ID', 'शीर्षक', 'स्थिति', 'श्रेणी', 'लोकेशन', 'रिपोर्टर', 'प्रकाशित', 'व्यूज़', 'शब्द', 'बनी'],
            array_map(static fn($r) => [$r['id'], $r['title'], NewsWorkflow::label($r['status']), $r['category'], $r['location'], $r['reporter'], $r['published_at'], $r['views'], $r['word_count'], $r['created_at']], $rows));
    }

    /** JSON खोज: संबंधित ख़बरें, होमपेज बिल्डर */
    public function search(Request $request): Response
    {
        $q = mb_substr($request->str('q'), 0, 100);
        [$scope, $params] = NewsService::scope('n');
        $where = "$scope AND n.deleted_at IS NULL";
        if ($request->bool('published')) {
            $where .= " AND n.status = 'published'";
        }
        if ($ex = $request->int('exclude')) {
            $where .= ' AND n.id <> ?';
            $params[] = $ex;
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $where .= ' AND n.id = ?';
                $params[] = (int) $q;
            } else {
                $where .= ' AND n.title LIKE ?';
                $params[] = '%' . addcslashes($q, '%_\\') . '%';
            }
        }
        $rows = db()->all("SELECT n.id, n.title, n.status, n.published_at FROM {p}news n WHERE $where ORDER BY COALESCE(n.published_at, n.updated_at) DESC LIMIT 15", $params);
        return $this->json(['items' => array_map(static fn($r) => ['id' => (int) $r['id'], 'title' => $r['title'], 'status' => NewsWorkflow::label($r['status'])], $rows)]);
    }
}
