<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/** पोल: खुला है?, नतीजे, एक वोट (ब्राउज़र+IP हैश / पाठक), विजेट HTML */
final class PollService
{
    public static function find(int $id): ?array
    {
        $p = db()->first("SELECT * FROM {p}polls WHERE id = ? AND status <> 'draft'", [$id]);
        if (!$p) {
            return null;
        }
        $p['options'] = db()->all('SELECT id, label, votes FROM {p}poll_options WHERE poll_id = ? ORDER BY sort_order, id', [$id]);
        return $p;
    }

    /** [खुला?, कारण] */
    public static function isOpen(array $p): array
    {
        if ($p['status'] !== 'active') {
            return [false, 'यह पोल बंद हो चुका है।'];
        }
        if ($p['start_at'] && strtotime((string) $p['start_at']) > time()) {
            return [false, 'यह पोल ' . hindi_date((string) $p['start_at'], true) . ' से शुरू होगा।'];
        }
        if ($p['end_at'] && strtotime((string) $p['end_at']) <= time()) {
            return [false, 'यह पोल ख़त्म हो चुका है।'];
        }
        return [true, null];
    }

    public static function voterHash(int $pollId, Request $request): string
    {
        return hash_hmac('sha256', 'poll|' . $pollId . '|' . $request->ip() . '|' . $request->userAgent(), (string) config('app.key'));
    }

    public static function hasVoted(array $p, Request $request): bool
    {
        if (isset($_COOKIE['pv_' . $p['id']])) {
            return true;
        }
        $rid = ReaderAuth::id();
        return (bool) db()->value('SELECT 1 FROM {p}poll_votes WHERE poll_id = ? AND (voter_hash = ?' . ($rid ? ' OR reader_id = ' . (int) $rid : '') . ')', [$p['id'], self::voterHash((int) $p['id'], $request)]);
    }

    public static function showResults(array $p, bool $voted): bool
    {
        return match ($p['show_results']) {
            'always' => true,
            'after_close' => !self::isOpen($p)[0],
            default => $voted || !self::isOpen($p)[0],
        };
    }

    /** वोट; त्रुटि संदेश या null */
    public static function vote(array $p, array $optionIds, Request $request): ?string
    {
        [$open, $why] = self::isOpen($p);
        if (!$open) {
            return $why;
        }
        if (NewsQuery::isBot()) {
            return 'वोट नहीं हो सका।';
        }
        if ((int) $p['require_login'] && !ReaderAuth::check()) {
            return 'वोट के लिए लॉगिन करें।';
        }
        $valid = array_map('intval', array_column($p['options'], 'id'));
        $ids = array_values(array_unique(array_intersect(array_map('intval', $optionIds), $valid)));
        if (!$ids || (!(int) $p['multiple'] && count($ids) > 1)) {
            return (int) $p['multiple'] ? 'कम से कम एक विकल्प चुनें।' : 'एक विकल्प चुनें।';
        }
        if (self::hasVoted($p, $request)) {
            return 'आप इस पोल में पहले ही वोट दे चुके हैं।';
        }
        try {
            db()->transaction(static function () use ($p, $ids, $request) {
                db()->insert('poll_votes', ['poll_id' => $p['id'], 'voter_hash' => self::voterHash((int) $p['id'], $request), 'reader_id' => ReaderAuth::id(), 'options' => implode(',', $ids), 'created_at' => date('Y-m-d H:i:s')]);
                db()->query('UPDATE {p}poll_options SET votes = votes + 1 WHERE poll_id = ? AND id IN (' . implode(',', $ids) . ')', [$p['id']]);
                db()->query('UPDATE {p}polls SET total_votes = total_votes + ?, voters = voters + 1 WHERE id = ?', [count($ids), $p['id']]);
            });
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return 'आप इस पोल में पहले ही वोट दे चुके हैं।';
            }
            throw $e;
        }
        setcookie('pv_' . $p['id'], '1', ['expires' => time() + 86400 * 365, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => $request->isSecure()]);
        $_COOKIE['pv_' . $p['id']] = '1';
        cache()->flush('home');
        return null;
    }

    /** विजेट (ख़बर में [poll:ID], होमपेज ब्लॉक, पोल पेज) */
    public static function widget(int $id, bool $full = false): string
    {
        $p = self::find($id);
        if (!$p) {
            return '';
        }
        $request = app('request');
        $voted = self::hasVoted($p, $request);
        return app('view')->render('partials/front/poll', ['p' => $p, 'voted' => $voted, 'open' => self::isOpen($p), 'results' => self::showResults($p, $voted), 'full' => $full]);
    }

    /** सामग्री में [poll:12] */
    public static function shortcodes(string $html): string
    {
        return preg_replace_callback('~(?:<p>\s*)?\[poll:(\d{1,9})\](?:\s*</p>)?~', static fn($m) => self::widget((int) $m[1]), $html) ?? $html;
    }
}
