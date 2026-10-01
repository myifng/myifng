<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CommentService;
use App\Services\NewsletterService;
use App\Services\NewsQuery;
use App\Services\Notify\WebPush;
use App\Services\PollService;
use App\Services\ReaderAuth;

/** पाठक की भागीदारी: टिप्पणी, पोल, न्यूज़लेटर, वेब पुश */
final class EngageController extends FrontController
{
    // ---------- टिप्पणी ----------
    public function comment(Request $request, string $slug): Response
    {
        $news = db()->first('SELECT n.* FROM {p}news n WHERE n.slug = ? AND ' . NewsQuery::PUBLISHED, [$slug]);
        if (!$news) {
            throw new HttpException(404);
        }
        $back = url('news/' . $news['slug']) . '#comments';
        [$open, $why] = CommentService::open($news);
        if (!$open) {
            return $this->redirect($back)->with('danger', $why ?? 'इस ख़बर पर टिप्पणियाँ बंद हैं।');
        }
        $r = CommentService::submit($news, $request, ReaderAuth::user());
        if (isset($r['error'])) {
            return $this->redirect($back)->withErrors([$r['field'] => $r['error']])->withInput(array_intersect_key($request->post(), array_flip(['name', 'email', 'body', 'parent_id'])));
        }
        $msg = $r['status'] === 'approved' ? 'आपकी टिप्पणी प्रकाशित हो गई।' : 'धन्यवाद! आपकी टिप्पणी जाँच के बाद दिखेगी।';
        return $this->redirect($r['status'] === 'approved' ? url('news/' . $news['slug']) . '#comment-' . $r['id'] : $back)->with('success', $msg);
    }

    // ---------- पोल ----------
    public function poll(Request $request, int $id): Response
    {
        $p = PollService::find($id);
        if (!$p) {
            throw new HttpException(404);
        }
        return $this->view('front/poll', ['p' => $p, 'widget' => PollService::widget($id, true), 'side' => $this->sidebar(),
            'seo' => ['title' => $p['question'], 'description' => $p['description'] ?: 'पोल: ' . $p['question'], 'canonical' => route('poll', ['id' => $id])]]);
    }

    public function vote(Request $request, int $id): Response
    {
        $p = PollService::find($id);
        if (!$p) {
            throw new HttpException(404);
        }
        $err = PollService::vote($p, (array) $request->input('options', []), $request);
        if ($request->wantsJson()) {
            return $err ? $this->json(['ok' => false, 'message' => $err], 422) : $this->json(['ok' => true, 'html' => PollService::widget($id, $request->bool('full'))]);
        }
        return $this->back()->with($err ? 'danger' : 'success', $err ?? 'आपका वोट दर्ज हो गया। धन्यवाद!');
    }

    // ---------- न्यूज़लेटर ----------
    public function subscribe(Request $request): Response
    {
        if (!NewsletterService::enabled()) {
            throw new HttpException(404);
        }
        if ($request->str('website') !== '') {
            return $this->back()->with('success', 'धन्यवाद!');
        }
        $email = mb_strtolower(trim($request->str('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $m = 'सही ईमेल पता लिखें।';
            return $request->wantsJson() ? $this->json(['ok' => false, 'message' => $m], 422) : $this->back()->withErrors(['nl_email' => $m])->with('danger', $m);
        }
        $reader = ReaderAuth::user();
        $lists = array_map('intval', (array) $request->input('lists', []));
        $lists = $lists ? array_map('intval', array_column(db()->all('SELECT id FROM {p}newsletter_lists WHERE is_public = 1 AND id IN (' . implode(',', $lists) . ')'), 'id')) : [];
        $status = NewsletterService::subscribe($email, $request->str('name') ?: ($reader['name'] ?? null), 'website', $reader && $reader['email'] === $email ? (int) $reader['id'] : null,
            $reader && $reader['email'] === $email, $lists);
        $m = $status === 'subscribed' ? 'आप न्यूज़लेटर से जुड़ गए। धन्यवाद!' : 'लगभग हो गया! अपने ईमेल में आया पुष्टि लिंक खोलें।';
        return $request->wantsJson() ? $this->json(['ok' => true, 'message' => $m]) : $this->back()->with('success', $m);
    }

    public function confirm(Request $request, string $token): Response
    {
        $s = db()->first('SELECT * FROM {p}newsletter_subscribers WHERE token = ?', [$token]);
        if ($s && $s['status'] === 'pending') {
            db()->query("UPDATE {p}newsletter_subscribers SET status = 'subscribed', confirmed_at = NOW() WHERE id = ?", [$s['id']]);
        }
        return $this->view('front/message', ['title' => $s ? 'पुष्टि हो गई' : 'लिंक मान्य नहीं', 'icon' => $s ? 'fa-circle-check' : 'fa-circle-xmark',
            'text' => $s ? 'धन्यवाद! अब आपको ' . setting('site_name') . ' का न्यूज़लेटर मिलेगा।' : 'यह लिंक पुराना या ग़लत है।', 'seo' => ['title' => 'न्यूज़लेटर', 'robots' => 'noindex,nofollow']]);
    }

    /** GET: पुष्टि पेज; POST: अनसब्सक्राइब (ईमेल ऐप का वन-क्लिक भी) */
    public function unsubscribe(Request $request, string $token): Response
    {
        $s = db()->first('SELECT * FROM {p}newsletter_subscribers WHERE token = ?', [$token]);
        if (!$s) {
            throw new HttpException(404);
        }
        if ($request->isPost()) {
            if ($s['status'] !== 'unsubscribed') {
                NewsletterService::unsubscribe((int) $s['id']);
            }
            if (!$request->str('_csrf')) {
                return new Response('', 200); // RFC 8058 वन-क्लिक
            }
            return $this->view('front/message', ['title' => 'अनसब्सक्राइब हो गया', 'icon' => 'fa-circle-check', 'text' => 'अब आपको न्यूज़लेटर नहीं भेजा जाएगा। कभी भी वेबसाइट से दोबारा जुड़ सकते हैं।',
                'seo' => ['title' => 'अनसब्सक्राइब', 'robots' => 'noindex,nofollow']]);
        }
        return $this->view('front/unsubscribe', ['s' => $s, 'token' => $token, 'seo' => ['title' => 'अनसब्सक्राइब', 'robots' => 'noindex,nofollow']]);
    }

    // ---------- वेब पुश ----------
    public function pushSubscribe(Request $request): Response
    {
        if (setting('push_enabled', '0') !== '1') {
            return $this->json(['ok' => false], 404);
        }
        $sub = json_decode((string) $request->input('subscription', ''), true);
        $ep = (string) ($sub['endpoint'] ?? '');
        $p256 = (string) ($sub['keys']['p256dh'] ?? '');
        $auth = (string) ($sub['keys']['auth'] ?? '');
        if (!preg_match('~^https://[a-z0-9.-]+(:\d+)?/~i', $ep) || strlen($ep) > 700 || strlen(WebPush::unb64u($p256)) !== 65 || strlen(WebPush::unb64u($auth)) < 16) {
            return $this->json(['ok' => false, 'message' => 'सब्सक्रिप्शन सही नहीं'], 422);
        }
        $topics = implode(',', array_values(array_intersect(['breaking', 'epaper'], (array) $request->input('topics', ['breaking'])))) ?: 'breaking';
        db()->query('INSERT INTO {p}push_subscriptions (endpoint, endpoint_hash, p256dh, auth, reader_id, topics, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), reader_id = COALESCE(VALUES(reader_id), reader_id), topics = VALUES(topics), fails = 0',
            [$ep, hash('sha256', $ep), $p256, $auth, ReaderAuth::id(), $topics, mb_substr($request->userAgent(), 0, 255)]);
        return $this->json(['ok' => true]);
    }

    public function pushUnsubscribe(Request $request): Response
    {
        $ep = $request->str('endpoint');
        if ($ep !== '') {
            db()->query('DELETE FROM {p}push_subscriptions WHERE endpoint_hash = ?', [hash('sha256', $ep)]);
        }
        return $this->json(['ok' => true]);
    }

    /** शेयर बटन दबाने की गिनती (Phase 13): ट्रेंडिंग और "सबसे ज़्यादा शेयर" के लिए */
    public function share(Request $request): Response
    {
        $ok = \App\Services\AnalyticsService::share($request->int('news_id'), (string) $request->input('network', ''));
        return $this->json(['ok' => $ok])->header('Cache-Control', 'no-store');
    }
}
