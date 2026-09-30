<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Models\Comment;
use App\Models\CommentBlock;
use App\Services\AuditService;
use App\Services\CommentService;

/** टिप्पणी मॉडरेशन: स्थिति के टैब, बल्क काम, जवाब, ब्लॉक सूची */
final class CommentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->str('status', 'pending');
        $where = ['1=1'];
        $params = [];
        if (isset(Comment::STATUSES[$status])) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if ($nid = $request->int('news')) {
            $where[] = 'c.news_id = ?';
            $params[] = $nid;
        }
        if (($q = $request->str('q')) !== '') {
            $where[] = '(c.body LIKE ? OR c.name LIKE ? OR c.email LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}comments c WHERE $w", $params);
        $items = db()->all("SELECT c.*, n.title news_title, n.slug news_slug, p.name parent_name, u.name staff
            FROM {p}comments c JOIN {p}news n ON n.id = c.news_id LEFT JOIN {p}comments p ON p.id = c.parent_id LEFT JOIN {p}users u ON u.id = c.user_id
            WHERE $w ORDER BY c.created_at DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $tally = array_column(db()->all('SELECT status, COUNT(*) c FROM {p}comments GROUP BY status'), 'c', 'status');
        return $this->view('admin/comments/index', ['items' => new Paginator($items, $total, 30, $page), 'status' => $status, 'q' => $q, 'tally' => $tally,
            'newsTitle' => $nid ? db()->value('SELECT title FROM {p}news WHERE id = ?', [$nid]) : null]);
    }

    /** एक या कई: approve / spam / reject / pending / delete */
    public function action(Request $request): Response
    {
        $action = $request->str('action');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        if (!$ids || !in_array($action, ['approved', 'spam', 'rejected', 'pending', 'delete'], true)) {
            return $this->back()->with('warning', 'कोई टिप्पणी या काम नहीं चुना गया।');
        }
        if ($action === 'delete' && !can('comments.delete')) {
            throw new HttpException(403);
        }
        $in = Database::in($ids);
        if ($action === 'delete') {
            $n = db()->query("DELETE FROM {p}comments WHERE id IN ($in)", $ids)->rowCount();
        } else {
            $newlyApproved = $action === 'approved' ? array_column(db()->all("SELECT id FROM {p}comments WHERE status <> 'approved' AND id IN ($in)", $ids), 'id') : [];
            $n = db()->query("UPDATE {p}comments SET status = ?, approved_by = IF(? = 'approved', ?, approved_by), updated_at = NOW() WHERE id IN ($in)", [$action, $action, auth()->id(), ...$ids])->rowCount();
            foreach ($newlyApproved as $cid) {
                CommentService::approved(Comment::find((int) $cid));
            }
        }
        AuditService::log($action === 'delete' ? 'delete' : 'moderate', 'comments', count($ids) === 1 ? $ids[0] : null, 'टिप्पणी: ' . $action . ' (' . $n . ')');
        $label = ['approved' => 'स्वीकृत', 'spam' => 'स्पैम', 'rejected' => 'अस्वीकृत', 'pending' => 'पेंडिंग', 'delete' => 'हटाई गईं'][$action];
        return $this->back()->with('success', "$n टिप्पणी: $label");
    }

    /** संपादक का जवाब (सीधे स्वीकृत) */
    public function reply(Request $request, int $id): Response
    {
        $c = Comment::find($id) ?? throw new HttpException(404);
        $v = $this->validate($request, ['body' => 'required|min:2|max:2000'], ['body' => 'जवाब']);
        $parent = $c['parent_id'] ? (int) $c['parent_id'] : $id;
        $rid = Comment::create(['news_id' => (int) $c['news_id'], 'parent_id' => $parent, 'user_id' => auth()->id(), 'name' => (string) auth()->user()['name'],
            'body' => trim(strip_tags((string) $v['body'])), 'status' => 'approved', 'approved_by' => auth()->id()]);
        if ($c['status'] !== 'approved') {
            Comment::update($id, ['status' => 'approved', 'approved_by' => auth()->id()]);
            CommentService::approved(Comment::find($id));
        }
        CommentService::approved(Comment::find($rid));
        AuditService::log('create', 'comments', $rid, 'टिप्पणी का जवाब: #' . $id);
        return $this->back()->with('success', 'जवाब प्रकाशित हो गया (मूल टिप्पणी भी स्वीकृत)।');
    }

    /** टिप्पणी से: ईमेल / IP / पाठक ब्लॉक (और उसकी बाकी टिप्पणियाँ स्पैम) */
    public function block(Request $request, int $id): Response
    {
        $c = Comment::find($id) ?? throw new HttpException(404);
        $type = $request->str('type');
        $value = match ($type) {
            'email' => (string) $c['email'],
            'ip' => (string) $c['ip_hash'],
            'reader' => (string) ($c['reader_id'] ?? ''),
            default => '',
        };
        if ($value === '') {
            return $this->back()->with('warning', 'इस टिप्पणी में यह जानकारी नहीं है।');
        }
        db()->query('INSERT IGNORE INTO {p}comment_blocks (type, value, note, created_by, created_at) VALUES (?, ?, ?, ?, NOW())', [$type, $value, 'टिप्पणी #' . $id . ' (' . $c['name'] . ')', auth()->id()]);
        $col = ['email' => 'email', 'ip' => 'ip_hash', 'reader' => 'reader_id'][$type];
        $n = db()->query("UPDATE {p}comments SET status = 'spam' WHERE $col = ? AND status IN ('pending','approved')", [$value])->rowCount();
        AuditService::log('block', 'comments', $id, 'टिप्पणी लेखक ब्लॉक (' . $type . '): ' . $c['name']);
        return $this->back()->with('success', CommentBlock::TYPES[$type] . ' ब्लॉक हुआ; ' . $n . ' टिप्पणियाँ स्पैम में।');
    }

    public function blocks(Request $request): Response
    {
        return $this->view('admin/comments/blocks', ['items' => db()->all('SELECT b.*, u.name creator FROM {p}comment_blocks b LEFT JOIN {p}users u ON u.id = b.created_by ORDER BY b.id DESC LIMIT 500')]);
    }

    public function addWord(Request $request): Response
    {
        $v = $this->validate($request, ['value' => 'required|min:2|max:100'], ['value' => 'शब्द']);
        db()->query("INSERT IGNORE INTO {p}comment_blocks (type, value, created_by, created_at) VALUES ('word', ?, ?, NOW())", [mb_strtolower(trim(strip_tags((string) $v['value']))), auth()->id()]);
        return $this->back()->with('success', 'शब्द जोड़ा गया।');
    }

    public function unblock(Request $request, int $id): Response
    {
        $b = CommentBlock::find($id) ?? throw new HttpException(404);
        db()->query('DELETE FROM {p}comment_blocks WHERE id = ?', [$id]);
        AuditService::log('unblock', 'comments', $id, 'ब्लॉक हटाया: ' . $b['type']);
        return $this->back()->with('success', 'ब्लॉक हटा दिया गया।');
    }
}
