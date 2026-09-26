<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Location;
use App\Models\NewsRevision;
use App\Services\AuditService;
use App\Services\NewsService;
use App\Services\NewsWorkflow;
use App\Services\TextDiff;

/** वर्कफ़्लो: स्थिति बदलना, टिप्पणियाँ, हिस्ट्री, वर्ज़न की तुलना और वापस लाना */
final class NewsWorkflowController extends Controller
{
    public function transition(Request $request, int $id): Response
    {
        $news = NewsController::findVisible($id);
        $action = $request->str('action');
        $err = NewsService::transition($news, $action, mb_substr($request->str('remark'), 0, 3000), $request->str('scheduled_at') ?: null);
        if ($err) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'message' => $err], 422) : $this->back()->with('danger', $err);
        }
        $to = NewsWorkflow::label(NewsWorkflow::target($action));
        $msg = '“' . $news['title'] . '” अब: ' . $to . '।';
        return $request->wantsJson() ? $this->json(['ok' => true, 'message' => $msg]) : $this->back()->with('success', $msg);
    }

    /** टिप्पणी: डेस्क आंतरिक (note) या रिपोर्टर को दिखने वाली (feedback); रिपोर्टर की हमेशा feedback */
    public function remark(Request $request, int $id): Response
    {
        $news = NewsController::findVisible($id);
        $msg = trim(mb_substr($request->str('message'), 0, 3000));
        if ($msg === '') {
            return $this->back()->with('warning', 'टिप्पणी ख़ाली है।');
        }
        $type = can('news.approve') && $request->str('type') === 'note' ? 'note' : 'feedback';
        NewsService::remark($id, $type, $msg);
        AuditService::log('remark', 'news', $id, ($type === 'note' ? 'आंतरिक टिप्पणी: ' : 'टिप्पणी: ') . $news['title']);
        return $this->redirect(back_url() . '#remarks')->with('success', $type === 'note' ? 'आंतरिक टिप्पणी जुड़ गई (रिपोर्टर को नहीं दिखेगी)।' : 'टिप्पणी जुड़ गई।');
    }

    public function history(Request $request, int $id): Response
    {
        $news = NewsController::findVisible($id, true);
        $revisions = db()->all('SELECT r.id, r.version, r.title, r.status, r.reason, r.is_correction, r.created_at, u.name AS user
            FROM {p}news_revisions r LEFT JOIN {p}users u ON u.id = r.changed_by WHERE r.news_id = ? ORDER BY r.version DESC', [$id]);
        return $this->view('admin/news/history', [
            'news' => $news, 'revisions' => $revisions, 'remarks' => NewsController::visibleRemarks($id),
            'actions' => NewsWorkflow::available($news), 'canEdit' => NewsService::canEdit($news),
            'category' => $news['category_id'] ? Category::find((int) $news['category_id']) : null,
            'location' => $news['location_id'] ? Location::find((int) $news['location_id']) : null,
            'reporter' => $news['reporter_id'] ? db()->value('SELECT name FROM {p}users WHERE id = ?', [$news['reporter_id']]) : null,
        ]);
    }

    /** वर्ज़न की तुलना (डिफ़ॉल्ट: उससे पहले वाले से; ?with=ID किसी और से) */
    public function revision(Request $request, int $id, int $rid): Response
    {
        $news = NewsController::findVisible($id, true);
        $rev = NewsRevision::find($rid);
        if (!$rev || (int) $rev['news_id'] !== $id) {
            throw new HttpException(404);
        }
        $with = $request->int('with');
        $base = $with
            ? db()->first('SELECT * FROM {p}news_revisions WHERE id = ? AND news_id = ?', [$with, $id])
            : db()->first('SELECT * FROM {p}news_revisions WHERE news_id = ? AND version < ? ORDER BY version DESC LIMIT 1', [$id, $rev['version']]);
        $labels = ['subtitle' => 'उप-शीर्षक', 'category_id' => 'श्रेणी (ID)', 'location_id' => 'लोकेशन (ID)', 'featured_image' => 'मुख्य इमेज', 'video_url' => 'वीडियो',
            'topics' => 'टॉपिक (ID)', 'tags' => 'टैग', 'related' => 'संबंधित (ID)', 'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'status' => 'स्थिति'];
        $d1 = $base ? (array) json_decode((string) $base['data'], true) + ['status' => NewsWorkflow::label($base['status'])] : [];
        $d2 = (array) json_decode((string) $rev['data'], true) + ['status' => NewsWorkflow::label($rev['status'])];
        return $this->view('admin/news/revision', [
            'news' => $news, 'rev' => $rev, 'base' => $base,
            'versions' => db()->all('SELECT id, version, created_at FROM {p}news_revisions WHERE news_id = ? ORDER BY version DESC', [$id]),
            'titleDiff' => TextDiff::words((string) ($base['title'] ?? ''), (string) $rev['title']),
            'summaryDiff' => TextDiff::words((string) ($base['summary'] ?? ''), (string) $rev['summary']),
            'contentDiff' => TextDiff::html($base['content'] ?? '', $rev['content']),
            'fieldDiff' => TextDiff::fields($d1, $d2, $labels),
            'user' => $rev['changed_by'] ? db()->value('SELECT name FROM {p}users WHERE id = ?', [$rev['changed_by']]) : null,
            'canRestore' => NewsService::canEdit($news) && (can('news.manage') || can('news.approve')),
        ]);
    }

    public function restore(Request $request, int $id, int $rid): Response
    {
        $news = NewsController::findVisible($id);
        $rev = NewsRevision::find($rid);
        if (!$rev || (int) $rev['news_id'] !== $id) {
            throw new HttpException(404);
        }
        if (!NewsService::canEdit($news) || !(can('news.manage') || can('news.approve'))) {
            throw new HttpException(403);
        }
        NewsService::restoreRevision($news, $rev);
        AuditService::log('restore_version', 'news', $id, 'वर्ज़न ' . $rev['version'] . ' वापस लाया: ' . $news['title']);
        return $this->toRoute('admin.news.history', ['id' => $id])->with('success', 'वर्ज़न ' . $rev['version'] . ' की सामग्री वापस आ गई (नया वर्ज़न बना; पुराना कुछ नहीं मिटा)।');
    }
}
