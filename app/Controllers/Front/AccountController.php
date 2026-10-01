<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Reader;
use App\Services\NewsQuery;
use App\Services\NotificationService;
use App\Services\ReaderAuth;
use App\Services\ReaderService;

/** पाठक खाता: रजिस्टर, सत्यापन, लॉगिन, पासवर्ड, "आपके लिए", सेव, इतिहास, फ़ॉलो, सूचनाएँ, सेटिंग */
final class AccountController extends FrontController
{
    private const NOINDEX = ['robots' => 'noindex,nofollow'];

    private function guard(): void
    {
        if (!ReaderAuth::enabled()) {
            throw new HttpException(404);
        }
    }

    /** लॉगिन ज़रूरी: न हो तो लॉगिन पेज (वापसी के पते के साथ) */
    private function me(Request $request): array|Response
    {
        $this->guard();
        $r = ReaderAuth::user();
        return $r ?? $this->redirect(route('account.login') . '?next=' . rawurlencode($request->path()));
    }

    private function safeNext(string $next): string
    {
        return preg_match('~^/(?!/)[a-z0-9/_-]*$~i', $next) && !str_starts_with($next, '/admin') ? url(ltrim($next, '/')) : route('account');
    }

    private function mail(string $to, string $subject, string $html): bool
    {
        return NotificationService::mail($to, $subject, $html);
    }

    // ---------- लॉगिन / रजिस्टर ----------

    public function loginForm(Request $request): Response
    {
        $this->guard();
        if (ReaderAuth::check()) {
            return $this->redirect($this->safeNext($request->str('next')));
        }
        return $this->view('front/account/login', ['next' => $request->str('next'), 'seo' => ['title' => 'लॉगिन'] + self::NOINDEX]);
    }

    public function login(Request $request): Response
    {
        $this->guard();
        $v = $this->validate($request, ['email' => 'required|email|max:190', 'password' => 'required|max:200'], ['email' => 'ईमेल', 'password' => 'पासवर्ड']);
        [$r, $err] = ReaderAuth::attempt((string) $v['email'], (string) $v['password'], $request);
        if (!$r) {
            throw new ValidationException(['email' => $err], ['email' => $v['email'], 'next' => $request->str('next')]);
        }
        ReaderAuth::login($r);
        return $this->redirect($this->safeNext($request->str('next')))->with('success', 'नमस्ते ' . $r['name'] . '!');
    }

    public function registerForm(Request $request): Response
    {
        $this->guard();
        if (ReaderAuth::check()) {
            return $this->redirect(route('account'));
        }
        return $this->view('front/account/register', ['seo' => ['title' => 'खाता बनाएँ'] + self::NOINDEX]);
    }

    public function register(Request $request): Response
    {
        $this->guard();
        if ($request->str('website') !== '') { // honeypot
            return $this->redirect(route('account.login'))->with('success', 'खाता बन गया।');
        }
        $v = $this->validate($request, [
            'name' => 'required|min:2|max:120', 'email' => 'required|email|max:190', 'mobile' => 'nullable|mobile',
            'password' => 'required|min:8|max:200', 'password_confirmation' => 'required', 'agree' => 'required',
        ], ['name' => 'नाम', 'email' => 'ईमेल', 'mobile' => 'मोबाइल', 'password' => 'पासवर्ड', 'password_confirmation' => 'पासवर्ड दोबारा', 'agree' => 'नियमों की सहमति']);
        $email = mb_strtolower(trim((string) $v['email']));
        $errors = [];
        if ($v['password'] !== $v['password_confirmation']) {
            $errors['password_confirmation'] = 'दोनों पासवर्ड एक जैसे नहीं हैं।';
        }
        if (!preg_match('/[A-Za-z]/', (string) $v['password']) || !preg_match('/\d/', (string) $v['password'])) {
            $errors['password'] = 'पासवर्ड में अक्षर और अंक दोनों हों।';
        }
        $name = trim(strip_tags((string) $v['name']));
        if ($name === '') {
            $errors['name'] = 'नाम लिखें।';
        }
        $exists = db()->first('SELECT id, status FROM {p}readers WHERE email = ?', [$email]);
        if ($errors) {
            throw new ValidationException($errors, array_diff_key($request->post(), array_flip(['password', 'password_confirmation', '_csrf'])));
        }
        $verify = setting('readers_verify_email', '1') === '1';
        if ($exists) {
            // खाता होने की जानकारी किसी और को न मिले: वही संदेश, मौजूदा पते पर सूचना
            if ($exists['status'] === 'pending') {
                $this->sendVerify((int) $exists['id'], $email, $name);
            }
            return $this->redirect(route('account.login'))->with('success', $verify ? 'अगर यह ईमेल सही है तो उस पर पुष्टि का लिंक भेज दिया गया है। लिंक खोलकर खाता चालू करें।' : 'इस ईमेल से पहले से खाता है। लॉगिन करें।');
        }
        $id = Reader::create(['name' => mb_substr($name, 0, 120), 'email' => $email, 'mobile' => $v['mobile'] ?: null, 'password' => password_hash((string) $v['password'], PASSWORD_DEFAULT),
            'status' => $verify ? 'pending' : 'active', 'email_verified_at' => $verify ? null : date('Y-m-d H:i:s'), 'location_id' => ReaderService::cityId(null)]);
        if ($request->bool('newsletter')) {
            \App\Services\NewsletterService::subscribe($email, $name, 'register', $id, !$verify);
        }
        if ($verify) {
            $this->sendVerify($id, $email, $name);
            return $this->redirect(route('account.login'))->with('success', 'अगर यह ईमेल सही है तो उस पर पुष्टि का लिंक भेज दिया गया है। लिंक खोलकर खाता चालू करें।');
        }
        ReaderAuth::login(Reader::find($id));
        return $this->redirect(route('account.following'))->with('success', 'खाता बन गया! अपनी पसंद की श्रेणी और शहर फ़ॉलो करें।');
    }

    private function sendVerify(int $id, string $email, string $name): void
    {
        $link = route('account.verify', ['token' => ReaderAuth::token($id, 'verify', 60 * 24)]);
        $this->mail($email, 'खाता सत्यापित करें', \App\Services\MailTemplate::title('अपना खाता चालू करें', '👋') . \App\Services\MailTemplate::hello($name)
            . \App\Services\MailTemplate::p('<b>' . e((string) setting('site_name')) . '</b> पर खाता बनाने के लिए धन्यवाद! खाता चालू करने के लिए नीचे का बटन दबाएँ।')
            . \App\Services\MailTemplate::button($link, 'खाता सत्यापित करें') . \App\Services\MailTemplate::note('यह लिंक <b>24 घंटे</b> तक चलेगा। अगर आपने खाता नहीं बनाया, तो इस ईमेल को अनदेखा करें।'));
    }

    public function verify(Request $request, string $token): Response
    {
        $this->guard();
        $r = ReaderAuth::consume($token, 'verify');
        if (!$r) {
            return $this->redirect(route('account.login'))->with('danger', 'यह लिंक पुराना या ग़लत है। लॉगिन पेज से "लिंक दोबारा भेजें" दबाएँ।');
        }
        if ($r['status'] === 'pending') {
            Reader::update((int) $r['id'], ['status' => 'active', 'email_verified_at' => date('Y-m-d H:i:s')]);
            db()->query("UPDATE {p}newsletter_subscribers SET status = 'subscribed', confirmed_at = NOW() WHERE reader_id = ? AND status = 'pending'", [$r['id']]);
            $r = Reader::find((int) $r['id']);
        }
        if ($r['status'] !== 'active') {
            return $this->redirect(route('account.login'))->with('danger', 'यह खाता बंद है।');
        }
        ReaderAuth::login($r);
        return $this->redirect(route('account.following'))->with('success', 'ईमेल सत्यापित हो गया, स्वागत है! अपनी पसंद की श्रेणी और शहर फ़ॉलो करें।');
    }

    public function resend(Request $request): Response
    {
        $this->guard();
        $email = mb_strtolower(trim($request->str('email')));
        $r = filter_var($email, FILTER_VALIDATE_EMAIL) ? db()->first("SELECT id, name, email FROM {p}readers WHERE email = ? AND status = 'pending'", [$email]) : null;
        if ($r) {
            $this->sendVerify((int) $r['id'], $r['email'], $r['name']);
        }
        return $this->redirect(route('account.login'))->with('success', 'अगर इस ईमेल का खाता सत्यापन के लिए बाकी है, तो नया लिंक भेज दिया गया है।');
    }

    public function logout(Request $request): Response
    {
        ReaderAuth::logout();
        return $this->redirect(url())->with('success', 'आप लॉगआउट हो गए।');
    }

    public function forgotForm(Request $request): Response
    {
        $this->guard();
        return $this->view('front/account/forgot', ['seo' => ['title' => 'पासवर्ड भूल गए'] + self::NOINDEX]);
    }

    public function forgot(Request $request): Response
    {
        $this->guard();
        $v = $this->validate($request, ['email' => 'required|email|max:190'], ['email' => 'ईमेल']);
        $r = db()->first("SELECT id, name, email FROM {p}readers WHERE email = ? AND status = 'active'", [mb_strtolower((string) $v['email'])]);
        if ($r) {
            $link = route('account.reset', ['token' => ReaderAuth::token((int) $r['id'], 'reset', 60)]);
            $this->mail($r['email'], 'पासवर्ड बदलें', \App\Services\MailTemplate::title('पासवर्ड रीसेट करें', '🔑') . \App\Services\MailTemplate::hello((string) $r['name'])
                . \App\Services\MailTemplate::p('नीचे के बटन से अपने खाते का नया पासवर्ड बनाएँ।') . \App\Services\MailTemplate::button($link, 'नया पासवर्ड बनाएँ')
                . \App\Services\MailTemplate::note('यह लिंक <b>60 मिनट</b> तक चलेगा। अगर आपने यह नहीं माँगा, तो इसे अनदेखा करें।', 'warn'));
        }
        return $this->redirect(route('account.login'))->with('success', 'अगर यह ईमेल किसी खाते से जुड़ा है तो पासवर्ड बदलने का लिंक भेज दिया गया है।');
    }

    public function resetForm(Request $request, string $token): Response
    {
        $this->guard();
        if (!ReaderAuth::consume($token, 'reset', false)) {
            return $this->redirect(route('account.forgot'))->with('danger', 'यह लिंक पुराना या ग़लत है। दोबारा माँगें।');
        }
        return $this->view('front/account/reset', ['token' => $token, 'seo' => ['title' => 'नया पासवर्ड'] + self::NOINDEX]);
    }

    public function reset(Request $request, string $token): Response
    {
        $this->guard();
        $v = $this->validate($request, ['password' => 'required|min:8|max:200', 'password_confirmation' => 'required'], ['password' => 'पासवर्ड', 'password_confirmation' => 'पासवर्ड दोबारा']);
        if ($v['password'] !== $v['password_confirmation'] || !preg_match('/[A-Za-z]/', (string) $v['password']) || !preg_match('/\d/', (string) $v['password'])) {
            throw new ValidationException(['password' => 'दोनों पासवर्ड एक जैसे हों और उनमें अक्षर व अंक दोनों हों।'], []);
        }
        $r = ReaderAuth::consume($token, 'reset');
        if (!$r) {
            return $this->redirect(route('account.forgot'))->with('danger', 'यह लिंक पुराना या ग़लत है। दोबारा माँगें।');
        }
        Reader::update((int) $r['id'], ['password' => password_hash((string) $v['password'], PASSWORD_DEFAULT)]);
        return $this->redirect(route('account.login'))->with('success', 'पासवर्ड बदल गया। अब लॉगिन करें।');
    }

    // ---------- खाते के पेज ----------

    private function shell(string $tab, array $data, string $title): Response
    {
        return $this->view('front/account/' . $tab, $data + ['tab' => $tab, 'reader' => ReaderAuth::user(),
            'unread' => NotificationService::unread('reader', (int) ReaderAuth::id()), 'seo' => ['title' => $title] + self::NOINDEX]);
    }

    /** "आपके लिए": फ़ॉलो की गई चीज़ें + शहर में लोकप्रिय */
    public function home(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $feed = ReaderService::feedWhere((int) $r['id']);
        $items = $feed ? NewsQuery::page($feed[0], $feed[1], $this->page($request->int('page', 1)), 12) : null;
        $city = ReaderService::cityId($r);
        $cityPopular = [];
        if ($city) {
            [$w, $p] = NewsQuery::locationWhere($city);
            $cityPopular = NewsQuery::list($w . ' AND n.published_at >= NOW() - INTERVAL 7 DAY', $p, 5, 0, 'n.views DESC, n.published_at DESC');
        }
        return $this->shell('home', ['items' => $items, 'cityPopular' => $cityPopular, 'cityName' => $city ? db()->value('SELECT name FROM {p}locations WHERE id = ?', [$city]) : null,
            'latest' => $items ? [] : NewsQuery::latest(8)], 'आपके लिए');
    }

    public function saved(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $items = NewsQuery::page('n.id IN (SELECT b.news_id FROM {p}reader_bookmarks b WHERE b.reader_id = ?)', [$r['id']], $this->page($request->int('page', 1)), 12);
        return $this->shell('saved', ['items' => $items], 'सेव की गई ख़बरें');
    }

    public function history(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $page = $this->page($request->int('page', 1));
        $total = (int) db()->value('SELECT COUNT(*) FROM {p}reader_history h JOIN {p}news n ON n.id = h.news_id AND ' . NewsQuery::PUBLISHED . ' WHERE h.reader_id = ?', [$r['id']]);
        $rows = db()->all('SELECT n.id, n.title, n.slug, n.featured_image, h.read_at FROM {p}reader_history h JOIN {p}news n ON n.id = h.news_id AND ' . NewsQuery::PUBLISHED
            . ' WHERE h.reader_id = ? ORDER BY h.read_at DESC LIMIT 30 OFFSET ' . Paginator::offset($page, 30), [$r['id']]);
        return $this->shell('history', ['items' => new Paginator($rows, $total, 30, $page)], 'पढ़ने का इतिहास');
    }

    public function following(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $f = ReaderService::follows((int) $r['id']);
        $names = [];
        foreach ($f as $type => $ids) {
            foreach ($ids as $id) {
                if ($t = ReaderService::target($type, $id)) {
                    $names[$type][$id] = $t;
                }
            }
        }
        $cats = db()->all("SELECT id, name, parent_id FROM {p}categories WHERE status = 'active' ORDER BY parent_id IS NOT NULL, sort_order, name");
        $topics = db()->all("SELECT id, name FROM {p}topics WHERE status = 'active' ORDER BY is_featured DESC, sort_order, name LIMIT 30");
        $reporters = db()->all("SELECT u.id, u.name, COUNT(*) c FROM {p}news n JOIN {p}users u ON u.id = n.reporter_id WHERE n.status = 'published' AND n.deleted_at IS NULL GROUP BY u.id ORDER BY c DESC LIMIT 30");
        return $this->shell('following', ['f' => $f, 'names' => $names, 'cats' => $cats, 'topics' => $topics, 'reporters' => $reporters], 'फ़ॉलो');
    }

    public function notifications(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $rows = db()->all("SELECT * FROM {p}notifications WHERE recipient_type = 'reader' AND recipient_id = ? ORDER BY id DESC LIMIT 50", [$r['id']]);
        db()->query("UPDATE {p}notifications SET read_at = NOW() WHERE recipient_type = 'reader' AND recipient_id = ? AND read_at IS NULL", [$r['id']]);
        return $this->shell('notifications', ['rows' => $rows], 'सूचनाएँ');
    }

    public function settings(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $sub = db()->first("SELECT id, status FROM {p}newsletter_subscribers WHERE email = ? OR reader_id = ? ORDER BY reader_id = ? DESC LIMIT 1", [$r['email'], $r['id'], $r['id']]);
        $city = $r['location_id'] ? db()->first('SELECT id, name FROM {p}locations WHERE id = ?', [$r['location_id']]) : null;
        return $this->shell('settings', ['prefs' => ReaderService::prefs($r), 'sub' => $sub, 'city' => $city], 'खाते की सेटिंग');
    }

    public function saveProfile(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $v = $this->validate($request, ['name' => 'required|min:2|max:120', 'mobile' => 'nullable|mobile', 'location_id' => 'nullable|integer'], ['name' => 'नाम', 'mobile' => 'मोबाइल', 'location_id' => 'शहर']);
        $loc = $v['location_id'] ? (int) db()->value("SELECT id FROM {p}locations WHERE id = ? AND status = 'active'", [(int) $v['location_id']]) : 0;
        Reader::update((int) $r['id'], ['name' => mb_substr(trim(strip_tags((string) $v['name'])), 0, 120) ?: $r['name'], 'mobile' => $v['mobile'] ?: null, 'location_id' => $loc ?: null]);
        return $this->back()->with('success', 'प्रोफ़ाइल सेव हो गई।');
    }

    public function savePrefs(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $prefs = [];
        foreach (array_keys(ReaderService::PREFS) as $k) {
            $prefs[$k] = $request->bool('pref_' . $k) ? 1 : 0;
        }
        Reader::update((int) $r['id'], ['prefs' => json_encode($prefs), 'history_enabled' => $request->bool('history_enabled') ? 1 : 0]);
        if (!$request->bool('history_enabled')) {
            db()->query('DELETE FROM {p}reader_history WHERE reader_id = ?', [$r['id']]);
        }
        $news = $request->bool('newsletter');
        $sub = db()->first('SELECT id, status FROM {p}newsletter_subscribers WHERE email = ?', [$r['email']]);
        if ($news && (!$sub || $sub['status'] !== 'subscribed')) {
            \App\Services\NewsletterService::subscribe($r['email'], $r['name'], 'account', (int) $r['id'], true);
        } elseif (!$news && $sub && $sub['status'] === 'subscribed') {
            \App\Services\NewsletterService::unsubscribe((int) $sub['id']);
        }
        return $this->back()->with('success', 'पसंद सेव हो गई।');
    }

    public function password(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        $v = $this->validate($request, ['current_password' => 'required', 'password' => 'required|min:8|max:200', 'password_confirmation' => 'required'],
            ['current_password' => 'मौजूदा पासवर्ड', 'password' => 'नया पासवर्ड', 'password_confirmation' => 'नया पासवर्ड दोबारा']);
        if (!password_verify((string) $v['current_password'], $r['password'])) {
            throw new ValidationException(['current_password' => 'मौजूदा पासवर्ड ग़लत है।'], []);
        }
        if ($v['password'] !== $v['password_confirmation'] || !preg_match('/[A-Za-z]/', (string) $v['password']) || !preg_match('/\d/', (string) $v['password'])) {
            throw new ValidationException(['password' => 'दोनों पासवर्ड एक जैसे हों और उनमें अक्षर व अंक दोनों हों।'], []);
        }
        Reader::update((int) $r['id'], ['password' => password_hash((string) $v['password'], PASSWORD_DEFAULT)]);
        app('session')->regenerate();
        return $this->back()->with('success', 'पासवर्ड बदल गया।');
    }

    public function clearHistory(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        db()->query('DELETE FROM {p}reader_history WHERE reader_id = ?', [$r['id']]);
        return $this->back()->with('success', 'इतिहास मिटा दिया गया।');
    }

    public function destroy(Request $request): Response
    {
        $r = $this->me($request);
        if ($r instanceof Response) {
            return $r;
        }
        if (!password_verify($request->str('password'), $r['password'])) {
            throw new ValidationException(['delete_password' => 'पासवर्ड ग़लत है।'], []);
        }
        ReaderAuth::logout();
        ReaderService::erase((int) $r['id']);
        return $this->redirect(url())->with('success', 'आपका खाता और उसका डेटा हटा दिया गया।');
    }

    // ---------- सेव / फ़ॉलो (AJAX या फ़ॉर्म) ----------

    public function bookmark(Request $request, int $id): Response
    {
        $this->guard();
        if (!ReaderAuth::check()) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'login' => route('account.login') . '?next=' . rawurlencode(parse_url(back_url(), PHP_URL_PATH) ?: '/')], 401)
                : $this->redirect(route('account.login'));
        }
        if (!db()->value('SELECT id FROM {p}news n WHERE n.id = ? AND ' . NewsQuery::PUBLISHED, [$id])) {
            throw new HttpException(404);
        }
        $on = ReaderService::toggleBookmark((int) ReaderAuth::id(), $id);
        return $request->wantsJson() ? $this->json(['ok' => true, 'on' => $on]) : $this->back()->with('success', $on ? 'ख़बर सेव हो गई।' : 'सेव से हटा दी।');
    }

    public function follow(Request $request): Response
    {
        $this->guard();
        if (!ReaderAuth::check()) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'login' => route('account.login')], 401) : $this->redirect(route('account.login'));
        }
        $type = $request->str('type');
        $id = $request->int('id');
        if (!isset(ReaderService::FOLLOW_TYPES[$type]) || !ReaderService::target($type, $id)) {
            return $request->wantsJson() ? $this->json(['ok' => false, 'message' => 'यह फ़ॉलो नहीं हो सकता।'], 422) : $this->back()->with('danger', 'यह फ़ॉलो नहीं हो सकता।');
        }
        $on = ReaderService::toggleFollow((int) ReaderAuth::id(), $type, $id);
        return $request->wantsJson() ? $this->json(['ok' => true, 'on' => $on]) : $this->back()->with('success', $on ? 'फ़ॉलो कर लिया।' : 'फ़ॉलो हटा दिया।');
    }
}
