<?php
declare(strict_types=1);

namespace App\Services;

/**
 * PWA (§15): Web App Manifest, होम स्क्रीन आइकन (GD से, कैश), सर्विस वर्कर की सेटिंग।
 * आइकन का स्रोत: सेटिंग "ऐप आइकन" → फ़ेविकॉन → मोबाइल लोगो → (कुछ नहीं) ब्रांड रंग का बना आइकन।
 */
final class PwaService
{
    public const SIZES = [180, 192, 512];

    public static function enabled(): bool
    {
        return setting('pwa_enabled', '1') === '1';
    }

    public static function name(): string
    {
        return trim((string) setting('pwa_name', '')) ?: (string) setting('site_name', 'News');
    }

    public static function shortName(): string
    {
        $s = trim((string) setting('pwa_short_name', ''));
        if ($s === '') {
            $s = self::name();
            $s = mb_strlen($s) > 12 ? (preg_split('/\s+/u', $s)[0] ?: mb_substr($s, 0, 12)) : $s;
        }
        return mb_substr($s, 0, 15);
    }

    public static function brand(): string
    {
        $c = (string) setting('primary_color', '#d71920');
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : '#d71920';
    }

    public static function background(): string
    {
        $c = (string) setting('pwa_background', '#ffffff');
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : '#ffffff';
    }

    /** आइकन का स्रोत (uploads में, GD पढ़ सके ऐसी इमेज) */
    public static function source(): ?string
    {
        foreach (['pwa_icon', 'favicon', 'logo_mobile'] as $k) {
            $p = (string) setting($k, '');
            $f = BASE_PATH . '/public/uploads/' . $p;
            if ($p === '' || !preg_match('/\.(png|jpe?g|webp|gif)$/i', $p) || !is_file($f)) {
                continue;
            }
            // फ़ेविकॉन/मोबाइल लोगो लगभग चौकोर हो तभी (चौड़ा लोगो आइकन में बिगड़ता है); "ऐप आइकन" हमेशा
            $sz = @getimagesize($f);
            if ($k === 'pwa_icon' || ($sz && $sz[1] > 0 && $sz[0] / $sz[1] >= 0.8 && $sz[0] / $sz[1] <= 1.25)) {
                return $p;
            }
        }
        return null;
    }

    /** स्रोत/रंग बदलते ही बदलता है: आइकन URL और सर्विस वर्कर के कैश नाम में */
    public static function iconVersion(): string
    {
        $src = self::source();
        $m = $src ? (string) @filemtime(BASE_PATH . '/public/uploads/' . $src) : '';
        return substr(md5('i2|' . $src . '|' . $m . '|' . self::brand() . '|' . self::background()), 0, 10);
    }

    public static function iconUrl(int $size, bool $maskable = false): string
    {
        return route($maskable ? 'pwa.icon.maskable' : 'pwa.icon', ['size' => $size]) . '?v=' . self::iconVersion();
    }

    public static function manifest(): array
    {
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/') . '/';
        $short = [];
        foreach ([['latest', 'ताज़ा ख़बरें', 'latest'], ['epaper', 'ई-पेपर', 'epaper'], ['live_tv', 'लाइव टीवी', 'live_tv'], ['search', 'खोज', 'search']] as [$route, $label]) {
            if (app('router')->has($route)) {
                $short[] = ['name' => $label, 'short_name' => $label, 'url' => route($route) . '?utm_source=pwa', 'icons' => [['src' => self::iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png']]];
            }
        }
        return [
            'id' => $base,
            'name' => self::name(),
            'short_name' => self::shortName(),
            'description' => mb_substr((string) setting('site_description', setting('tagline', '')), 0, 300),
            'lang' => setting('language', 'hi'),
            'dir' => 'ltr',
            'start_url' => $base . '?utm_source=pwa',
            'scope' => $base,
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'background_color' => self::background(),
            'theme_color' => self::brand(),
            'categories' => ['news'],
            'icons' => [
                ['src' => self::iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::iconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::iconUrl(512, true), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => $short,
        ];
    }

    /** आइकन PNG का रास्ता (storage/cache/pwa में; पहली बार बनता है) */
    public static function icon(int $size, bool $maskable = false): ?string
    {
        if (!in_array($size, self::SIZES, true) || !function_exists('imagecreatetruecolor')) {
            return null;
        }
        $dir = BASE_PATH . '/storage/cache/pwa';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tag = $size . ($maskable ? '-m' : '');
        $file = $dir . '/icon-' . $tag . '-' . self::iconVersion() . '.png';
        if (is_file($file)) {
            return $file;
        }
        // पुराने वर्ज़न हटाएँ
        foreach (glob($dir . '/icon-' . $tag . '-*.png') ?: [] as $old) {
            @unlink($old);
        }
        $png = self::draw($size, $maskable || $size === 180);
        if ($png === null) {
            return null;
        }
        @file_put_contents($file, $png, LOCK_EX);
        return is_file($file) ? $file : null;
    }

    /** $full = पूरा भरा बैकग्राउंड (maskable / Apple); वरना पारदर्शी किनारे */
    private static function draw(int $size, bool $full): ?string
    {
        $im = imagecreatetruecolor($size, $size);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        $src = self::source();
        $logo = $src ? @imagecreatefromstring((string) file_get_contents(BASE_PATH . '/public/uploads/' . $src)) : false;
        if ($logo) {
            if ($full) {
                imagefilledrectangle($im, 0, 0, $size, $size, self::rgb($im, self::background()));
            }
            // maskable: सुरक्षित घेरा (व्यास 80%) के अंदर; Apple: थोड़ी जगह; बाकी: पूरा
            $box = (int) round($size * ($size === 180 ? 0.84 : ($full ? 0.62 : 1.0)));
            $w = imagesx($logo);
            $h = imagesy($logo);
            $k = min($box / max(1, $w), $box / max(1, $h));
            $nw = max(1, (int) round($w * $k));
            $nh = max(1, (int) round($h * $k));
            imagecopyresampled($im, $logo, intdiv($size - $nw, 2), intdiv($size - $nh, 2), 0, 0, $nw, $nh, $w, $h);
            unset($logo);
        } else {
            self::generated($im, $size, $full);
        }
        ob_start();
        imagepng($im, null, 9);
        unset($im);
        return (string) ob_get_clean() ?: null;
    }

    /** कोई इमेज नहीं: ब्रांड रंग पर सफ़ेद "अख़बार" का निशान */
    private static function generated(\GdImage $im, int $s, bool $full): void
    {
        $brand = self::rgb($im, self::brand());
        if ($full) {
            imagefilledrectangle($im, 0, 0, $s, $s, $brand);
        } else {
            self::roundRect($im, 0, 0, $s - 1, $s - 1, (int) round($s * 0.22), $brand);
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $u = $s / 100; // इकाई
        $x0 = (int) round(30 * $u);
        $y0 = (int) round(30 * $u);
        $x1 = (int) round(70 * $u);
        $y1 = (int) round(70 * $u);
        self::roundRect($im, $x0, $y0, $x1, $y1, (int) round(4 * $u), $white);
        $ink = $brand;
        $pad = (int) round(5 * $u);
        imagefilledrectangle($im, $x0 + $pad, $y0 + $pad, $x1 - $pad, $y0 + $pad + (int) round(6 * $u), $ink); // शीर्षक
        $iy = $y0 + $pad + (int) round(10 * $u);
        imagefilledrectangle($im, $x0 + $pad, $iy, $x0 + $pad + (int) round(13 * $u), $iy + (int) round(13 * $u), $ink); // फ़ोटो
        for ($i = 0; $i < 3; $i++) {
            $ly = $iy + (int) round(($i * 5) * $u);
            imagefilledrectangle($im, $x0 + $pad + (int) round(16 * $u), $ly, $x1 - $pad, $ly + (int) round(2.4 * $u), $ink);
        }
        for ($i = 0; $i < 2; $i++) {
            $ly = $iy + (int) round((17 + $i * 5) * $u);
            imagefilledrectangle($im, $x0 + $pad, $ly, $x1 - $pad, $ly + (int) round(2.4 * $u), $ink);
        }
    }

    private static function roundRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $c): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $c);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $c);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $c);
        }
    }

    private static function rgb(\GdImage $im, string $hex): int
    {
        return imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    }

    /** सर्विस वर्कर को भेजी जाने वाली सेटिंग */
    public static function swConfig(): array
    {
        $on = self::enabled();
        $admin = trim((string) config('app.admin_path', 'admin'), '/');
        $skip = array_map(static fn($p) => preg_quote($p, '/'), array_unique([$admin, 'admin', 'reporter', 'account', 'api', 'install', 'push', 'ad', 'sw.js', 'manifest.webmanifest', 'pwa', 'newsletter', 'form', 'complaint', 'login', 'logout']));
        $files = ['css/app.css', 'js/app.js', 'vendor/fontawesome/css/all.min.css'];
        $ver = config('app.version') . '|' . self::iconVersion() . '|' . (string) @filemtime(BASE_PATH . '/public/assets/js/sw.js');
        $pre = [route('pwa.offline')];
        foreach ($files as $f) {
            $pre[] = asset($f);
            $ver .= '|' . (string) @filemtime(BASE_PATH . '/public/assets/' . $f);
        }
        foreach (['fa-solid-900', 'fa-regular-400', 'fa-brands-400'] as $w) {
            $pre[] = url('public/assets/vendor/fontawesome/webfonts/' . $w . '.woff2');
        }
        $pre[] = self::iconUrl(192);
        return [
            'cache' => $on,
            'v' => substr(md5($ver), 0, 10),
            'offline' => route('pwa.offline'),
            'precache' => $pre,
            'skip' => '^(' . implode('|', $skip) . ')(\/|$|\?)',
            'pages' => $on ? max(0, min(100, (int) setting('pwa_offline_pages', '30'))) : 0,
            'images' => 150,
        ];
    }
}
