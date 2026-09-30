<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\Notify\WebPush;

/** स्टाफ़ की अपनी सूचनाएँ (घंटी) + नोटिफ़िकेशन सेंटर (भेजना, कतार, लॉग) */
final class NotificationController extends Controller
{
    /** घंटी: JSON (ड्रॉपडाउन) */
    public function bell(Request $request): Response
    {
        $uid = (int) auth()->id();
        $rows = array_map(static fn($n) => ['title' => $n['title'], 'body' => $n['body'], 'url' => $n['url'], 'read' => (bool) $n['read_at'], 'time' => hindi_date($n['created_at'], true)],
            NotificationService::latest('user', $uid, 8));
        return $this->json(['unread' => NotificationService::unread('user', $uid), 'items' => $rows]);
    }

    public function mine(Request $request): Response
    {
        $uid = (int) auth()->id();
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}notifications WHERE recipient_type = 'user' AND recipient_id = ?", [$uid]);
        $rows = db()->all("SELECT * FROM {p}notifications WHERE recipient_type = 'user' AND recipient_id = ? ORDER BY id DESC LIMIT 30 OFFSET " . Paginator::offset($page, 30), [$uid]);
        return $this->view('admin/notifications/mine', ['items' => new Paginator($rows, $total, 30, $page)]);
    }

    public function readAll(Request $request): Response
    {
        db()->query("UPDATE {p}notifications SET read_at = NOW() WHERE recipient_type = 'user' AND recipient_id = ? AND read_at IS NULL", [auth()->id()]);
        return $request->wantsJson() ? $this->json(['ok' => true]) : $this->back()->with('success', 'सभी सूचनाएँ पढ़ी गईं।');
    }

    /** एक सूचना खोलें: पढ़ी मानें और उसके पते पर जाएँ */
    public function open(Request $request, int $id): Response
    {
        $n = db()->first("SELECT * FROM {p}notifications WHERE id = ? AND recipient_type = 'user' AND recipient_id = ?", [$id, auth()->id()]);
        if (!$n) {
            return $this->toRoute('admin.notifications.mine');
        }
        db()->query('UPDATE {p}notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$id]);
        $url = (string) $n['url'];
        return $this->redirect($url !== '' && str_starts_with($url, rtrim((string) config('app.url'), '/')) ? $url : route('admin.notifications.mine'));
    }

    // ---------- सेंटर ----------
    public function center(Request $request): Response
    {
        $where = ['1=1'];
        $params = [];
        foreach (['channel', 'status', 'event'] as $f) {
            if (($v = $request->str($f)) !== '') {
                $where[] = "$f = ?";
                $params[] = $v;
            }
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}notification_outbox WHERE $w", $params);
        $rows = db()->all("SELECT * FROM {p}notification_outbox WHERE $w ORDER BY id DESC LIMIT 50 OFFSET " . Paginator::offset($page, 50), $params);
        $stats = db()->first("SELECT SUM(status = 'queued') q, SUM(status = 'sent' AND created_at >= NOW() - INTERVAL 1 DAY) s, SUM(status = 'failed' AND created_at >= NOW() - INTERVAL 1 DAY) f FROM {p}notification_outbox");
        return $this->view('admin/notifications/center', ['items' => new Paginator($rows, $total, 50, $page), 'f' => $request->only(['channel', 'status', 'event']),
            'stats' => array_map('intval', $stats ?? []), 'pushSubs' => (int) db()->value('SELECT COUNT(*) FROM {p}push_subscriptions'),
            'readers' => (int) db()->value("SELECT COUNT(*) FROM {p}readers WHERE status = 'active'"), 'pushReady' => WebPush::available(),
            'channels' => array_map(static fn($e) => NotificationService::channels($e), array_combine(array_keys(NotificationService::EVENTS), array_keys(NotificationService::EVENTS)))]);
    }

    /** हाथ से: पुश (सभी सब्सक्राइबर) / पाठकों के खाते / स्टाफ़ */
    public function send(Request $request): Response
    {
        $v = $this->validate($request, ['title' => 'required|min:3|max:120', 'body' => 'nullable|max:300', 'url' => 'nullable|url|max:500', 'audience' => 'required|in:push,readers,staff'],
            ['title' => 'शीर्षक', 'body' => 'संदेश', 'url' => 'लिंक', 'audience' => 'किसे']);
        $msg = ['title' => strip_tags((string) $v['title']), 'body' => strip_tags((string) ($v['body'] ?? '')), 'url' => (string) ($v['url'] ?: url())];
        $n = match ($v['audience']) {
            'push' => setting('push_enabled', '0') === '1' ? self::forced('push', ['push' => 'all'], $msg) : -1,
            'readers' => self::forced('inapp', ['readers' => array_map('intval', array_column(db()->all("SELECT id FROM {p}readers WHERE status = 'active'"), 'id'))], $msg),
            'staff' => self::forced('inapp', ['users' => array_map('intval', array_column(db()->all("SELECT id FROM {p}users WHERE status = 'active' AND deleted_at IS NULL"), 'id'))], $msg),
        };
        if ($n < 0) {
            return $this->back()->with('danger', 'वेब पुश बंद है (सेटिंग → नोटिफ़िकेशन)।');
        }
        AuditService::log('create', 'notifications', null, 'सूचना भेजी (' . $v['audience'] . ', ' . $n . '): ' . $msg['title']);
        return $this->back()->with('success', "$n को सूचना " . ($v['audience'] === 'push' ? 'कतार में डाली (हर मिनट भेजी जाती है)।' : 'भेज दी।'));
    }

    /** "manual" इवेंट, सिर्फ़ चुना चैनल */
    private static function forced(string $channel, array $to, array $msg): int
    {
        return NotificationService::notify('manual', $to, $msg, [$channel]);
    }

    public function process(Request $request): Response
    {
        $r = NotificationService::process(100, 25);
        return $this->back()->with('success', 'भेजे: ' . $r['sent'] . ' · विफल: ' . $r['failed']);
    }

    public function retry(Request $request): Response
    {
        $n = db()->query("UPDATE {p}notification_outbox SET status = 'queued', attempts = 0, send_after = NOW(), error = NULL WHERE status = 'failed' AND channel <> 'push' AND created_at >= NOW() - INTERVAL 3 DAY")->rowCount();
        return $this->back()->with('success', "$n विफल सूचनाएँ दोबारा कतार में।");
    }
}
