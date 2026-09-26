<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\LiveBlog;
use App\Models\LiveUpdate;
use App\Services\AuditService;
use App\Services\EmbedService;
use App\Services\LiveBlogService;
use App\Services\MediaService;

/** लाइव ब्लॉग: सूची, शुरू करना, कंट्रोल पेज, अपडेट (AJAX) */
final class LiveBlogController extends Controller
{
    public function index(Request $request): Response
    {
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value('SELECT COUNT(*) FROM {p}live_blogs');
        $items = db()->all('SELECT b.*, n.title, n.slug, n.status AS news_status, (SELECT COUNT(*) FROM {p}live_updates u WHERE u.live_blog_id = b.id) AS updates,
                (SELECT MAX(posted_at) FROM {p}live_updates u WHERE u.live_blog_id = b.id) AS last_update
                FROM {p}live_blogs b JOIN {p}news n ON n.id = b.news_id ORDER BY FIELD(b.status, "live", "paused", "ended"), b.updated_at DESC LIMIT 25 OFFSET ' . Paginator::offset($page, 25));
        return $this->view('admin/live-blogs/index', ['items' => new Paginator($items, $total, 25, $page)]);
    }

    /** किसी ख़बर को लाइव ब्लॉग बनाएँ */
    public function store(Request $request): Response
    {
        $newsId = $request->int('news_id');
        $news = $newsId ? db()->first('SELECT id, title, status FROM {p}news WHERE id = ? AND deleted_at IS NULL', [$newsId]) : null;
        if (!$news) {
            throw new ValidationException(['news_id' => 'ख़बर चुनें।'], $request->post());
        }
        if ($existing = LiveBlogService::forNews($newsId)) {
            return $this->toRoute('admin.live_blogs.show', ['id' => $existing['id']])->with('info', 'इस ख़बर का लाइव ब्लॉग पहले से है।');
        }
        $id = LiveBlogService::start($newsId);
        AuditService::log('create', 'live_blogs', $id, 'लाइव ब्लॉग शुरू: ' . $news['title']);
        return $this->toRoute('admin.live_blogs.show', ['id' => $id])->with('success', 'लाइव ब्लॉग शुरू हो गया।' . ($news['status'] !== 'published' ? ' ध्यान दें: ख़बर अभी प्रकाशित नहीं है, इसलिए वेबसाइट पर नहीं दिखेगा।' : ''));
    }

    public function show(Request $request, int $id): Response
    {
        $blog = $this->find($id);
        $news = db()->first('SELECT id, title, slug, status, published_at FROM {p}news WHERE id = ?', [$blog['news_id']]);
        return $this->view('admin/live-blogs/show', ['blog' => $blog, 'news' => $news, 'updates' => LiveBlogService::updates($id)]);
    }

    public function status(Request $request, int $id): Response
    {
        $blog = $this->find($id);
        $to = $request->str('status');
        if (!isset(LiveBlog::STATUSES[$to])) {
            throw new HttpException(422);
        }
        LiveBlogService::setStatus($blog, $to);
        AuditService::log('status', 'live_blogs', $id, 'लाइव ब्लॉग: ' . LiveBlog::STATUSES[$blog['status']] . ' → ' . LiveBlog::STATUSES[$to]);
        return $this->back()->with('success', ['live' => 'लाइव ब्लॉग फिर से लाइव है।', 'paused' => 'रोक दिया गया। पाठकों को “रुका हुआ” दिखेगा।', 'ended' => 'लाइव ब्लॉग ख़त्म। अपडेट पेज पर बने रहेंगे।'][$to]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $blog = $this->find($id);
        LiveBlog::delete($id);
        db()->query('UPDATE {p}news SET is_live = 0 WHERE id = ?', [$blog['news_id']]);
        LiveBlogService::changed();
        AuditService::log('delete', 'live_blogs', $id, 'लाइव ब्लॉग हटाया (ख़बर #' . $blog['news_id'] . ')');
        return $this->toRoute('admin.live_blogs.index')->with('success', 'लाइव ब्लॉग और उसके सारे अपडेट हट गए। ख़बर बनी रहेगी।');
    }

    // ---------- अपडेट (AJAX; बिना JS सामान्य फ़ॉर्म की तरह भी) ----------

    public function storeUpdate(Request $request, int $id): Response
    {
        $this->find($id);
        $data = $this->updatePayload($request, null);
        $uid = LiveUpdate::create($data + ['live_blog_id' => $id, 'author_id' => auth()->id()]);
        LiveBlogService::changed();
        AuditService::log('update_add', 'live_blogs', $id, 'अपडेट #' . $uid . ': ' . \App\Helpers\Str::limit((string) ($data['title'] ?: $data['body']), 80));
        return $this->reply($request, $id, $uid, 'अपडेट डल गया।');
    }

    public function updateUpdate(Request $request, int $id, int $uid): Response
    {
        $this->find($id);
        $u = $this->findUpdate($id, $uid);
        $data = $this->updatePayload($request, $u);
        LiveUpdate::update($uid, $data);
        LiveBlogService::changed();
        AuditService::log('update_edit', 'live_blogs', $id, 'अपडेट #' . $uid . ' बदला', $u, $data);
        return $this->reply($request, $id, $uid, 'अपडेट बदल गया।');
    }

    public function pin(Request $request, int $id, int $uid): Response
    {
        $this->find($id);
        $u = $this->findUpdate($id, $uid);
        LiveUpdate::update($uid, ['is_pinned' => $u['is_pinned'] ? 0 : 1]);
        LiveBlogService::changed();
        return $this->reply($request, $id, $uid, $u['is_pinned'] ? 'पिन हटाया।' : 'पिन किया।');
    }

    public function destroyUpdate(Request $request, int $id, int $uid): Response
    {
        $this->find($id);
        $u = $this->findUpdate($id, $uid);
        LiveUpdate::delete($uid);
        LiveBlogService::changed();
        AuditService::log('update_delete', 'live_blogs', $id, 'अपडेट #' . $uid . ' हटाया', $u);
        return $request->wantsJson() ? $this->json(['ok' => true, 'id' => $uid, 'message' => 'अपडेट हटा दिया।'])
            : $this->toRoute('admin.live_blogs.show', ['id' => $id])->with('success', 'अपडेट हटा दिया।');
    }

    private function reply(Request $request, int $id, int $uid, string $msg): Response
    {
        if (!$request->wantsJson()) {
            return $this->toRoute('admin.live_blogs.show', ['id' => $id])->with('success', $msg);
        }
        $u = db()->first('SELECT lu.*, u.name AS author FROM {p}live_updates lu LEFT JOIN {p}users u ON u.id = lu.author_id WHERE lu.id = ?', [$uid]);
        return $this->json(['ok' => true, 'id' => $uid, 'message' => $msg, 'html' => app('view')->render('admin/live-blogs/_update', ['u' => $u])]);
    }

    private function updatePayload(Request $request, ?array $u): array
    {
        $v = $this->validate($request, [
            'title' => 'nullable|max:255', 'body' => 'nullable|max:5000', 'embed_url' => 'nullable|max:500', 'posted_at' => 'nullable|date',
        ], ['title' => 'शीर्षक', 'body' => 'अपडेट', 'embed_url' => 'वीडियो/लिंक', 'posted_at' => 'समय']);
        $errors = [];
        $title = trim(strip_tags((string) $v['title']));
        $body = trim(str_replace("\r", '', (string) $v['body']));
        $image = $request->str('image');
        if ($image !== ($u['image'] ?? '') && !MediaService::validImagePath($image)) {
            $errors['image'] = 'इमेज मीडिया लाइब्रेरी से चुनें।';
        }
        $embed = trim((string) $v['embed_url']);
        if ($embed !== '' && !EmbedService::safeLink($embed)) {
            $errors['embed_url'] = 'पूरा https:// लिंक डालें (YouTube, X आदि)।';
        }
        if ($title === '' && $body === '' && $image === '' && $embed === '') {
            $errors['body'] = 'अपडेट में कुछ लिखें या इमेज/वीडियो जोड़ें।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $at = $v['posted_at'] ? date('Y-m-d H:i:s', strtotime((string) $v['posted_at'])) : ($u['posted_at'] ?? now());
        if (strtotime($at) > time() + 60) {
            $at = now(); // आगे का समय नहीं (टाइमलाइन उलझती है)
        }
        return ['title' => $title ?: null, 'body' => $body ?: null, 'image' => $image ?: null, 'embed_url' => $embed ?: null,
            'is_key' => $request->bool('is_key') ? 1 : 0, 'is_pinned' => $request->bool('is_pinned') ? 1 : 0, 'posted_at' => $at];
    }

    private function find(int $id): array
    {
        return LiveBlog::find($id) ?? throw new HttpException(404);
    }

    private function findUpdate(int $blogId, int $uid): array
    {
        return db()->first('SELECT * FROM {p}live_updates WHERE id = ? AND live_blog_id = ?', [$uid, $blogId]) ?? throw new HttpException(404);
    }
}
