<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Response;
use App\Models\Redirect;

/**
 * रीडायरेक्ट और 404 लॉग।
 * हाथ से बने नियम रिक्वेस्ट से पहले चलते हैं; अपने आप बने (स्लग बदलने वाले) सिर्फ़ तब जब पता 404 हो,
 * ताकि उसी पते पर नई सामग्री कभी न छुपे।
 */
final class RedirectService
{
    /** इन पतों से न रीडायरेक्ट बनता है, न 404 लॉग होता है */
    public const BLOCKED = ['/admin', '/install', '/api/', '/ad/', '/live-updates/'];

    public static function blocked(string $path): bool
    {
        foreach (self::BLOCKED as $b) {
            if ($path === rtrim($b, '/') || str_starts_with($path, rtrim($b, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    /** अपनी साइट का पूरा URL या पाथ → "/a/b" (query, #, आख़िरी / हटाकर) */
    public static function normalize(string $path): string
    {
        $path = trim($path);
        foreach (array_filter([(string) config('app.url'), (string) setting('seo_canonical_host')]) as $base) {
            $base = rtrim($base, '/');
            if ($base !== '' && stripos($path, $base) === 0) {
                $path = substr($path, strlen($base));
                break;
            }
        }
        $path = preg_replace('~[?#].*$~s', '', $path) ?? '';
        $path = rawurldecode($path);
        $path = '/' . trim(preg_replace('~/+~', '/', $path) ?? '', '/');
        return mb_substr($path, 0, 500);
    }

    /** अपनी साइट का पूरा URL? */
    public static function own(string $url): bool
    {
        foreach (array_filter([(string) config('app.url'), (string) setting('seo_canonical_host')]) as $base) {
            if (stripos($url, rtrim($base, '/')) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function hash(string $path): string
    {
        return sha1(mb_strtolower($path));
    }

    /** सभी चालू नियम (कैश) */
    private static function rules(): array
    {
        return cache()->remember('redirects.map', 600, static function (): array {
            $out = ['exact' => [], 'prefix' => []];
            foreach (db()->all("SELECT id, source, source_hash, match_type, target, code, is_auto FROM {p}redirects WHERE status = 'active'") as $r) {
                $row = ['id' => (int) $r['id'], 'source' => $r['source'], 'target' => (string) $r['target'], 'code' => (int) $r['code'], 'auto' => (bool) $r['is_auto']];
                if ($r['match_type'] === 'prefix') {
                    $out['prefix'][] = $row;
                } else {
                    $out['exact'][$r['source_hash']] = $row;
                }
            }
            // लंबा प्रीफ़िक्स पहले (सबसे सटीक नियम जीते)
            usort($out['prefix'], static fn($a, $b) => mb_strlen($b['source']) <=> mb_strlen($a['source']));
            return $out;
        });
    }

    public static function changed(): void
    {
        cache()->forget('redirects.map');
    }

    /** पाथ पर कौन-सा नियम लगेगा: [id, code, url(पूरा) या null(410)] */
    public static function match(string $path, bool $withAuto = true): ?array
    {
        $rules = self::rules();
        $hit = $rules['exact'][self::hash($path)] ?? null;
        $rest = '';
        if ($hit && !$withAuto && $hit['auto']) {
            $hit = null;
        }
        if (!$hit) {
            foreach ($rules['prefix'] as $r) {
                if ((!$withAuto && $r['auto']) || !($path === $r['source'] || str_starts_with(mb_strtolower($path), mb_strtolower(rtrim($r['source'], '/')) . '/'))) {
                    continue;
                }
                $hit = $r;
                $rest = ltrim(mb_substr($path, mb_strlen(rtrim($r['source'], '/'))), '/');
                break;
            }
        }
        if (!$hit) {
            return null;
        }
        if ($hit['code'] === 410) {
            return ['id' => $hit['id'], 'code' => 410, 'url' => null];
        }
        $target = $hit['target'];
        if (str_ends_with($target, '/*')) {
            $target = rtrim(substr($target, 0, -1), '/') . ($rest !== '' ? '/' . $rest : '');
        }
        $abs = str_starts_with($target, '/') ? url($target) : $target;
        // लूप से बचाव: वही पता
        if (str_starts_with($target, '/') && self::hash(self::normalize($target)) === self::hash($path)) {
            return null;
        }
        return ['id' => $hit['id'], 'code' => $hit['code'], 'url' => $abs];
    }

    /** रिक्वेस्ट के लिए रीडायरेक्ट रिस्पॉन्स (या null) */
    public static function respond(Request $request, bool $withAuto): ?Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD'], true)) {
            return null;
        }
        $path = $request->path();
        if ($path === '/' || self::blocked($path)) {
            return null;
        }
        try {
            $m = self::match($path, $withAuto);
        } catch (\Throwable) {
            return null; // टेबल अभी नहीं बनी (अपडेट बाकी)
        }
        if (!$m) {
            return null;
        }
        db()->query('UPDATE {p}redirects SET hits = hits + 1, last_hit_at = NOW() WHERE id = ?', [$m['id']]);
        if ($m['code'] === 410) {
            return app('errors')->render(410);
        }
        $url = $m['url'];
        $qs = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        if ($qs !== '' && !str_contains($url, '?')) {
            $url .= '?' . $qs;
        }
        return Response::redirect($url, $m['code'])->header('Cache-Control', 'no-cache')->header('X-Redirect-By', 'CMS');
    }

    /**
     * स्लग बदला: पुराना पता → नया (301, अपने आप)। नया पता किसी पुराने अपने-आप वाले नियम का स्रोत हो तो वह नियम हटे।
     * चेन छोटी: जो नियम पुराने पते पर भेजते थे, अब सीधे नए पर।
     */
    public static function moved(string $oldUrl, string $newUrl, string $entity, bool $prefix = false): void
    {
        $old = self::normalize($oldUrl);
        $new = self::normalize($newUrl);
        self::claim($new);
        if ($old === $new || $old === '/' || self::blocked($old) || setting('seo_auto_redirect', '1') !== '1') {
            return;
        }
        $target = $new . ($prefix ? '/*' : '');
        db()->query('UPDATE {p}redirects SET target = ?, updated_at = NOW() WHERE target IN (?, ?)', [$target, $old, url($old)]);
        if ($prefix) {
            db()->query('UPDATE {p}redirects SET target = ?, updated_at = NOW() WHERE target = ?', [$target, $old . '/*']);
        }
        $existing = db()->first('SELECT id, is_auto FROM {p}redirects WHERE source_hash = ?', [self::hash($old)]);
        $row = ['source' => $old, 'source_hash' => self::hash($old), 'match_type' => $prefix ? 'prefix' : 'exact', 'target' => $target, 'code' => 301,
            'is_auto' => 1, 'entity' => mb_substr($entity, 0, 40), 'status' => 'active'];
        if ($existing) {
            if ((int) $existing['is_auto'] === 1) {
                Redirect::update((int) $existing['id'], $row);
            }
        } else {
            Redirect::create($row + ['note' => 'स्लग बदला', 'created_by' => auth()->id()]);
        }
        db()->query("UPDATE {p}not_found_log SET status = 'fixed' WHERE path_hash = ?", [self::hash($old)]);
        self::changed();
    }

    /** इस पते पर अब असली सामग्री है: अपने आप बना पुराना नियम हटे */
    public static function claim(string $url): void
    {
        $path = self::normalize($url);
        if (db()->query('DELETE FROM {p}redirects WHERE source_hash = ? AND is_auto = 1', [self::hash($path)])->rowCount()) {
            self::changed();
        }
    }

    /** 404 दर्ज (एक पता = एक पंक्ति) */
    public static function log404(Request $request): void
    {
        if ($request->method() !== 'GET' || setting('seo_log_404', '1') !== '1') {
            return;
        }
        $path = mb_substr($request->path(), 0, 500);
        if (self::blocked($path)) {
            return;
        }
        $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
        $internal = $ref !== '' && (str_starts_with($ref, rtrim((string) config('app.url'), '/')) || (setting('seo_canonical_host') && str_starts_with($ref, rtrim((string) setting('seo_canonical_host'), '/')))) ? 1 : 0;
        $bot = NewsQuery::isBot() ? 1 : 0;
        try {
            db()->query('INSERT INTO {p}not_found_log (path, path_hash, hits, bot_hits, referrer, is_internal, user_agent, first_seen, last_seen)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits), bot_hits = bot_hits + VALUES(bot_hits), last_seen = NOW(),
                    referrer = IF(VALUES(referrer) IS NULL, referrer, VALUES(referrer)), is_internal = GREATEST(is_internal, VALUES(is_internal)),
                    user_agent = VALUES(user_agent), status = IF(status = \'fixed\', \'new\', status)',
                [$path, self::hash($path), 1 - $bot, $bot, $ref !== '' ? $ref : null, $internal, mb_substr($request->userAgent(), 0, 255)]);
            if (random_int(1, 200) === 1) {
                db()->query('DELETE FROM {p}not_found_log WHERE last_seen < NOW() - INTERVAL 90 DAY');
            }
        } catch (\Throwable) {
            // लॉग न हो पाए तो भी 404 पेज दिखे
        }
    }

    /**
     * फ़ॉर्म/CSV की पंक्ति जाँचकर साफ़ डेटा; गलती हो तो [null, संदेश]
     * @return array{0: ?array, 1: ?string}
     */
    public static function clean(string $source, string $target, int $code, ?int $exceptId = null): array
    {
        $source = trim($source);
        $prefix = str_ends_with($source, '*');
        $src = self::normalize(rtrim($source, '*'));
        if ($source === '' || $src === '/') {
            return [null, 'पुराना पता लिखें (होमपेज को रीडायरेक्ट नहीं किया जा सकता)।'];
        }
        if (preg_match('~^https?://~i', $source) && !self::own($source)) {
            return [null, 'पुराना पता अपनी ही वेबसाइट का होना चाहिए, जैसे /news/purani-khabar'];
        }
        if (self::blocked($src)) {
            return [null, 'एडमिन/इंस्टॉल/API के पते रीडायरेक्ट नहीं हो सकते।'];
        }
        if (!isset(Redirect::CODES[$code])) {
            return [null, 'रीडायरेक्ट का प्रकार सही नहीं।'];
        }
        $target = trim($target);
        if ($code !== 410) {
            if ($target === '') {
                return [null, 'नया पता लिखें।'];
            }
            if (preg_match('~^https?://~i', $target)) {
                if (!filter_var($target, FILTER_VALIDATE_URL)) {
                    return [null, 'नया पता सही URL नहीं है।'];
                }
                $star = str_ends_with($target, '/*');
                if (self::own($target)) { // अपनी ही साइट का पूरा URL → पाथ
                    $own = self::normalize($star ? substr($target, 0, -2) : $target);
                    $target = $star ? rtrim($own, '/') . '/*' : $own;
                }
            } elseif (str_starts_with($target, '/')) {
                $star = str_ends_with($target, '/*');
                $base = self::normalize($star ? substr($target, 0, -2) : $target);
                $target = $star ? rtrim($base, '/') . '/*' : $base;
            } else {
                return [null, 'नया पता "/" से शुरू हो (अपनी साइट) या http(s):// वाला पूरा URL हो।'];
            }
            if (str_starts_with($target, '/') && self::hash(self::normalize(preg_replace('~/\*$~', '', $target) ?? '')) === self::hash($src)) {
                return [null, 'पुराना और नया पता एक ही है (लूप बनेगा)।'];
            }
            if (str_ends_with($target, '/*') && !$prefix) {
                return [null, 'नए पते के आख़िर में /* तभी, जब पुराना पता भी * पर ख़त्म हो।'];
            }
        } else {
            $target = null;
        }
        $dup = db()->value('SELECT id FROM {p}redirects WHERE source_hash = ?' . ($exceptId ? ' AND id <> ' . (int) $exceptId : ''), [self::hash($src)]);
        if ($dup) {
            return [null, 'इस पुराने पते का रीडायरेक्ट पहले से है (#' . $dup . ')।'];
        }
        return [['source' => $src, 'source_hash' => self::hash($src), 'match_type' => $prefix ? 'prefix' : 'exact', 'target' => $target, 'code' => $code], null];
    }
}
