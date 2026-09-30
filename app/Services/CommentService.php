<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Comment;

/** टिप्पणियाँ: दिखाना, भेजना (स्पैम जाँच + मॉडरेशन), ब्लॉक */
final class CommentService
{
    /** इस ख़बर पर टिप्पणी खुली है? [bool, कारण] */
    public static function open(array $news): array
    {
        if (setting('comments_enabled', '1') !== '1' || !(int) ($news['allow_comments'] ?? 1)) {
            return [false, null];
        }
        $days = (int) setting('comments_close_days', '0');
        if ($days > 0 && $news['published_at'] && strtotime((string) $news['published_at']) < time() - $days * 86400) {
            return [false, 'इस ख़बर पर टिप्पणियाँ अब बंद हैं।'];
        }
        return [true, null];
    }

    /** स्वीकृत टिप्पणियाँ: [[टिप्पणी + replies], …], कुल */
    public static function thread(int $newsId): array
    {
        $rows = db()->all("SELECT c.id, c.parent_id, c.name, c.body, c.created_at, c.user_id, c.reader_id FROM {p}comments c WHERE c.news_id = ? AND c.status = 'approved' ORDER BY c.created_at LIMIT 500", [$newsId]);
        $top = [];
        $replies = [];
        foreach ($rows as $r) {
            $r['parent_id'] ? $replies[(int) $r['parent_id']][] = $r : $top[(int) $r['id']] = $r + ['replies' => []];
        }
        foreach ($replies as $pid => $list) {
            if (isset($top[$pid])) {
                $top[$pid]['replies'] = $list;
            }
        }
        return [array_reverse(array_values($top)), count($rows)];
    }

    public static function count(int $newsId): int
    {
        return (int) db()->value("SELECT COUNT(*) FROM {p}comments WHERE news_id = ? AND status = 'approved'", [$newsId]);
    }

    /** फ़ॉर्म का समय-टोकन (बहुत जल्दी भेजे फ़ॉर्म = बॉट) */
    public static function stamp(): string
    {
        $t = (string) time();
        return $t . '.' . substr(hash_hmac('sha256', 'cm|' . $t, (string) config('app.key')), 0, 16);
    }

    private static function stampAge(string $s): ?int
    {
        [$t, $h] = array_pad(explode('.', $s, 2), 2, '');
        return ctype_digit($t) && hash_equals(substr(hash_hmac('sha256', 'cm|' . $t, (string) config('app.key')), 0, 16), $h) ? time() - (int) $t : null;
    }

    private static function blocked(string $type, string $value): bool
    {
        return $value !== '' && (bool) db()->value('SELECT 1 FROM {p}comment_blocks WHERE type = ? AND value = ?', [$type, $value]);
    }

    /** शब्द-सूची (सेटिंग + ब्लॉक टेबल) में से कोई शब्द? */
    private static function badWord(string $text): bool
    {
        $words = array_merge(preg_split('/[\n,]+/u', (string) setting('comments_blocked_words')) ?: [], array_column(db()->all("SELECT value FROM {p}comment_blocks WHERE type = 'word'"), 'value'));
        $t = mb_strtolower($text);
        foreach ($words as $w) {
            $w = mb_strtolower(trim($w));
            if ($w !== '' && str_contains($t, $w)) {
                return true;
            }
        }
        return false;
    }

    /**
     * टिप्पणी भेजें। लौटाए: ['status' => pending|approved|spam|fake, 'id' => int] या ['error' => संदेश, 'field' => खाना]
     */
    public static function submit(array $news, Request $request, ?array $reader): array
    {
        if ($request->str('website') !== '') {
            return ['status' => 'fake', 'id' => 0]; // honeypot: बॉट को लगे भेज दिया
        }
        $age = self::stampAge($request->str('ts'));
        if ($age === null || $age < max(0, (int) setting('comments_min_seconds', '5')) || $age > 86400) {
            return ['error' => 'फ़ॉर्म दोबारा खोलकर कुछ सेकंड बाद भेजें।', 'field' => 'body'];
        }
        if (!$reader && setting('comments_who', 'guests') !== 'guests') {
            return ['error' => 'टिप्पणी के लिए लॉगिन करें।', 'field' => 'body'];
        }
        $body = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', strip_tags($request->str('body')))) ?? '');
        $name = $reader ? (string) $reader['name'] : trim(strip_tags($request->str('name')));
        $email = $reader ? (string) $reader['email'] : mb_strtolower(trim($request->str('email')));
        if (mb_strlen($body) < 3 || mb_strlen($body) > 2000) {
            return ['error' => 'टिप्पणी 3 से 2000 अक्षर की हो।', 'field' => 'body'];
        }
        if (!$reader && (mb_strlen($name) < 2 || mb_strlen($name) > 100)) {
            return ['error' => 'अपना नाम लिखें।', 'field' => 'name'];
        }
        if (!$reader && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'सही ईमेल लिखें (दिखाया नहीं जाएगा)।', 'field' => 'email'];
        }
        $ip = ReaderAuth::ipHash($request);
        if (self::blocked('email', $email) || self::blocked('ip', $ip) || ($reader && self::blocked('reader', (string) $reader['id']))) {
            return ['error' => 'आप इस वेबसाइट पर टिप्पणी नहीं कर सकते।', 'field' => 'body'];
        }
        if (db()->value('SELECT 1 FROM {p}comments WHERE ip_hash = ? AND body = ? AND created_at > NOW() - INTERVAL 1 DAY', [$ip, $body])) {
            return ['error' => 'यह टिप्पणी पहले ही भेजी जा चुकी है।', 'field' => 'body'];
        }
        $parent = $request->int('parent_id');
        if ($parent && !db()->value("SELECT id FROM {p}comments WHERE id = ? AND news_id = ? AND parent_id IS NULL AND status = 'approved'", [$parent, $news['id']])) {
            $parent = 0;
        }
        $links = preg_match_all('~https?://|www\.~i', $body);
        $spam = $links > (int) setting('comments_max_links', '2') || self::badWord($body . ' ' . $name);
        $mode = (string) setting('comments_moderation', 'all');
        $trusted = $reader && ((int) $reader['trusted'] || (int) db()->value("SELECT COUNT(*) FROM {p}comments WHERE reader_id = ? AND status = 'approved'", [$reader['id']]) >= 3);
        $status = $spam ? 'spam' : ($mode === 'none' || ($mode === 'trusted' && $trusted) ? 'approved' : 'pending');
        $id = Comment::create(['news_id' => (int) $news['id'], 'parent_id' => $parent ?: null, 'reader_id' => $reader['id'] ?? null, 'name' => mb_substr($name, 0, 100),
            'email' => $email ?: null, 'body' => $body, 'status' => $status, 'ip_hash' => $ip, 'user_agent' => mb_substr($request->userAgent(), 0, 255)]);
        if ($status === 'pending') {
            NotificationService::notify('comment', ['users' => NotificationService::usersWith('comments.approve')],
                ['title' => 'नई टिप्पणी: ' . mb_substr($name, 0, 40), 'body' => mb_substr($body, 0, 140), 'url' => route('admin.comments.index')]);
        } elseif ($status === 'approved') {
            self::approved(Comment::find($id));
        }
        return ['status' => $status, 'id' => $id];
    }

    /** स्वीकृत होने पर: पाठक को बताएँ (अपनी टिप्पणी स्वीकृत / किसी ने जवाब दिया) */
    public static function approved(?array $c): void
    {
        if (!$c) {
            return;
        }
        $news = db()->first('SELECT slug, title FROM {p}news WHERE id = ?', [$c['news_id']]);
        $url = $news ? NewsService::url($news) . '#comment-' . $c['id'] : url();
        $notify = static function (int $readerId, string $title) use ($url, $news) {
            $r = db()->first('SELECT id, prefs FROM {p}readers WHERE id = ?', [$readerId]);
            if ($r && ReaderService::prefs($r)['comments']) {
                NotificationService::notify('comment_reply', ['readers' => [$readerId]], ['title' => $title, 'body' => (string) ($news['title'] ?? ''), 'url' => $url]);
            }
        };
        if ($c['reader_id'] && !$c['user_id']) {
            $notify((int) $c['reader_id'], 'आपकी टिप्पणी प्रकाशित हो गई');
        }
        if ($c['parent_id']) {
            $p = db()->first('SELECT reader_id FROM {p}comments WHERE id = ?', [$c['parent_id']]);
            if ($p && $p['reader_id'] && (int) $p['reader_id'] !== (int) $c['reader_id']) {
                $notify((int) $p['reader_id'], $c['name'] . ' ने आपकी टिप्पणी का जवाब दिया');
            }
        }
    }
}
