<?php
declare(strict_types=1);

namespace App\Services;

/**
 * वीडियो/लाइव टीवी/एम्बेड के पते: कच्चा HTML कभी सेव या आउटपुट नहीं होता,
 * सिर्फ़ जाँचे हुए https पते, जिन्हें हमारा अपना <iframe>/<video> दिखाता है।
 */
final class EmbedService
{
    /** YouTube वीडियो की 11 अक्षर वाली ID */
    public static function youtubeId(?string $url): ?string
    {
        return $url && preg_match('~(?:youtu\.be/|[?&]v=|/shorts/|/live/|/embed/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~', $url, $m) ? $m[1] : null;
    }

    /** YouTube चैनल ID (UC…): चैनल का लाइव स्ट्रीम */
    public static function youtubeChannel(?string $url): ?string
    {
        return $url && preg_match('~/channel/(UC[A-Za-z0-9_-]{22})~', $url, $m) ? $m[1] : null;
    }

    /** YouTube का सुरक्षित embed पता (या null) */
    public static function youtubeEmbed(?string $url, bool $autoplay = false): ?string
    {
        $q = $autoplay ? '?autoplay=1&mute=1' : '';
        if ($id = self::youtubeId($url)) {
            return 'https://www.youtube-nocookie.com/embed/' . $id . $q;
        }
        if ($ch = self::youtubeChannel($url)) {
            return 'https://www.youtube.com/embed/live_stream?channel=' . $ch . ($autoplay ? '&autoplay=1&mute=1' : '');
        }
        return null;
    }

    /** पेस्ट किया <iframe …> कोड या सीधा पता → सिर्फ़ https src */
    public static function iframeSrc(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (stripos($input, '<iframe') !== false) {
            if (!preg_match('~\ssrc\s*=\s*["\']([^"\']+)["\']~i', $input, $m)) {
                return null;
            }
            $input = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        return self::isHttps($input) ? $input : null;
    }

    /** स्ट्रीमिंग: https और .m3u8 / .mp4 */
    public static function streamUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        return self::isHttps($url) && preg_match('~\.(m3u8|mp4)(\?[^\s]*)?$~i', $url) ? $url : null;
    }

    public static function isHls(string $url): bool
    {
        return (bool) preg_match('~\.m3u8(\?|$)~i', $url);
    }

    /** CTA/बाहरी लिंक: http(s)://… या साइट के अंदर का /पाथ; javascript: आदि नहीं */
    public static function safeLink(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~^/(?!/)~', $url)) {
            return $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url) ? $url : null;
    }

    /** दिखाने के लिए: साइट का /पाथ → पूरा URL (सब-फ़ोल्डर इंस्टॉल में भी सही) */
    public static function href(?string $url): ?string
    {
        $safe = self::safeLink($url);
        return $safe !== null && $safe[0] === '/' ? url(ltrim($safe, '/')) : $safe;
    }

    public static function isExternal(string $url): bool
    {
        return (bool) preg_match('~^https?://~i', $url) && parse_url($url, PHP_URL_HOST) !== parse_url(url(), PHP_URL_HOST);
    }

    private static function isHttps(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://') && !preg_match('~[\s"\'<>]~', $url);
    }

    /** 125 → "2:05", 3725 → "1:02:05" */
    public static function duration(?int $sec): string
    {
        if (!$sec) {
            return '';
        }
        return $sec >= 3600 ? sprintf('%d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60) : sprintf('%d:%02d', intdiv($sec, 60), $sec % 60);
    }

    /** "2:05" / "1:02:05" / "125" → सेकंड */
    public static function parseDuration(?string $v): ?int
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v)) {
            return (int) $v;
        }
        if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})$/', $v, $m)) {
            return null;
        }
        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
    }

    /** ISO 8601 (VideoObject के लिए): PT2M5S */
    public static function isoDuration(?int $sec): ?string
    {
        if (!$sec) {
            return null;
        }
        return 'PT' . (intdiv($sec, 3600) ? intdiv($sec, 3600) . 'H' : '') . (intdiv($sec % 3600, 60) ? intdiv($sec % 3600, 60) . 'M' : '') . ($sec % 60) . 'S';
    }
}
