<?php
/**
 * पूरे ऐप में इस्तेमाल होने वाले छोटे फ़ंक्शन।
 */
declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\HttpException;

function app(?string $service = null): mixed
{
    return $service === null ? App::instance() : App::instance()->get($service);
}

function config(string $key, mixed $default = null): mixed
{
    return app('config')->get($key, $default);
}

function db(): \App\Core\Database
{
    return app('db');
}

function cache(): \App\Core\Cache
{
    return app('cache');
}

function logger(): \App\Core\Logger
{
    return app('logger');
}

/** HTML में सुरक्षित छापने के लिए: हर आउटपुट इसी से */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** साइट का पूरा URL */
function url(string $path = ''): string
{
    return rtrim((string) config('app.url'), '/') . '/' . ltrim($path, '/');
}

/** public/assets की फ़ाइल, बदलने पर कैश अपने आप टूटे */
function asset(string $path): string
{
    $rel = 'public/assets/' . ltrim($path, '/');
    $file = BASE_PATH . '/' . $rel;
    return url($rel) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function upload_url(?string $path): string
{
    return $path ? url('public/uploads/' . ltrim($path, '/')) : '';
}

/** नामित रूट का पूरा URL */
function route(string $name, array $params = []): string
{
    return url(app('router')->url($name, $params));
}

function back_url(): string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    return $ref !== '' && str_starts_with($ref, rtrim((string) config('app.url'), '/')) ? $ref : url();
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return Csrf::field();
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/** पिछला भरा मान (वैलिडेशन फ़ेल होने पर फ़ॉर्म दोबारा भरने के लिए) */
function old(string $key, mixed $default = ''): mixed
{
    $old = app('session')->getFlash('old', []);
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function errors(): array
{
    return app('session')->getFlash('errors', []);
}

function error(string $field): ?string
{
    return errors()[$field] ?? null;
}

function auth(): \App\Core\Auth
{
    return app('auth');
}

function user(?string $key = null): mixed
{
    $u = auth()->user();
    return $key === null ? $u : ($u[$key] ?? null);
}

function can(string $permission): bool
{
    return app('gate')->can($permission);
}

function is_super_admin(): bool
{
    return app('gate')->isSuperAdmin();
}

/** वेबसाइट सेटिंग (डेटाबेस से, कैश के साथ) */
function setting(string $key, mixed $default = ''): mixed
{
    static $all = null;
    if ($all === null || $key === '__reload__') {
        $all = \App\Services\SettingService::all();
        if ($key === '__reload__') {
            return null;
        }
    }
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function view(string $template, array $data = []): \App\Core\Response
{
    return new \App\Core\Response(app('view')->render($template, $data));
}

function flash(): ?array
{
    return app('session')->getFlash('flash');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function num(mixed $n): string
{
    return number_format((float) $n, 0, '.', ',');
}

const HINDI_MONTHS = ['जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितंबर', 'अक्टूबर', 'नवंबर', 'दिसंबर'];
const HINDI_DAYS = ['रविवार', 'सोमवार', 'मंगलवार', 'बुधवार', 'गुरुवार', 'शुक्रवार', 'शनिवार'];

function hindi_date(mixed $date, bool $time = false, bool $day = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
    $s = (int) date('j', $ts) . ' ' . HINDI_MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    if ($day) {
        $s = HINDI_DAYS[(int) date('w', $ts)] . ', ' . $s;
    }
    return $time ? $s . ', ' . date('H:i', $ts) : $s;
}

function time_ago(mixed $date): string
{
    if (!$date) {
        return '—';
    }
    $diff = time() - strtotime((string) $date);
    if ($diff < 60) {
        return 'अभी';
    }
    if ($diff < 3600) {
        return (int) floor($diff / 60) . ' मिनट पहले';
    }
    if ($diff < 86400) {
        return (int) floor($diff / 3600) . ' घंटे पहले';
    }
    if ($diff < 86400 * 30) {
        return (int) floor($diff / 86400) . ' दिन पहले';
    }
    return hindi_date($date);
}

/** ब्राउज़र का छोटा नाम (लॉगिन हिस्ट्री, ऑडिट लॉग के लिए) */
function device_name(?string $ua): string
{
    $ua = (string) $ua;
    $os = match (true) {
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'macOS',
        str_contains($ua, 'Linux') => 'Linux',
        default => 'अन्य',
    };
    $br = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') => 'Opera',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'ब्राउज़र',
    };
    return "$br · $os";
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** नाम का पहला अक्षर (अवतार के लिए) */
function initials(?string $name): string
{
    return mb_substr(trim((string) $name) ?: '?', 0, 1);
}

/** मौजूदा पेज इस रूट से शुरू होता है? (साइडबार हाइलाइट) */
function is_route(string $prefix): bool
{
    $path = app('request')->path();
    $target = parse_url(route($prefix), PHP_URL_PATH) ?: '';
    $base = rtrim((string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?: ''), '/');
    $target = '/' . trim(substr($target, strlen($base)), '/');
    return $path === $target || ($target !== '/' . trim((string) config('app.admin_path', 'admin'), '/') && str_starts_with($path, $target . '/'));
}
/**
 * मीडिया का URL किसी आकार में: media_url('media/2026/09/abc.jpg', 'medium') → .../abc-medium.jpg (हो तो), वरना मूल
 * $media: path (string) या media टेबल की पंक्ति (array)
 */
function media_url(array|string|null $media, string $size = 'medium', bool $webp = false): string
{
    if (is_array($media)) {
        return \App\Services\MediaService::url($media, $size, $webp);
    }
    if (!$media) {
        return '';
    }
    $v = media_variant($media, $size, $webp);
    return upload_url($v ?? $media);
}

/** path से वेरिएंट का path (फ़ाइल मौजूद हो तो), वरना null */
function media_variant(string $path, string $size, bool $webp = false): ?string
{
    static $seen = [];
    if ($size === 'original' || !preg_match('~^(media/.+)\.(jpg|png|webp)$~', $path, $m)) {
        return null;
    }
    $v = $m[1] . '-' . $size . '.' . ($webp ? 'webp' : $m[2]);
    return $seen[$v] ??= is_file(BASE_PATH . '/public/uploads/' . $v) ? $v : null;
}

/** <picture> (WebP + मूल प्रारूप), lazy loading के साथ */
function media_img(array|string|null $media, string $size = 'medium', string $alt = '', array $attrs = []): string
{
    if (!$media) {
        return '';
    }
    $src = media_url($media, $size);
    $webp = media_url($media, $size, true);
    $alt = $alt !== '' ? $alt : (is_array($media) ? (string) ($media['alt'] ?: $media['title']) : '');
    $attrs += ['loading' => 'lazy', 'decoding' => 'async'];
    $a = '';
    foreach ($attrs as $k => $val) {
        $a .= ' ' . e($k) . '="' . e($val) . '"';
    }
    $img = '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $a . '>';
    return $webp !== $src && str_ends_with($webp, '.webp') ? '<picture><source type="image/webp" srcset="' . e($webp) . '">' . $img . '</picture>' : $img;
}

/** पाठक का चुना हुआ शहर (कुकी) */
function my_city(): ?array
{
    return \App\Services\LocationService::myCity();
}

require_once __DIR__ . '/form.php';
