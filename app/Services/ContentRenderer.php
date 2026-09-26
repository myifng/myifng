<?php
declare(strict_types=1);

namespace App\Services;

/**
 * सेव किया (साफ़) HTML दिखाने से पहले:
 *  - shortcode: [site_name] [contact_email] [contact_phone] [address] [year] → सेटिंग के मान
 *  - <figure class="embed" data-youtube="ID"> → सुरक्षित YouTube प्लेयर
 */
final class ContentRenderer
{
    public static function render(?string $html): string
    {
        $html = (string) $html;
        $codes = [
            '[site_name]' => e(setting('site_name')),
            '[contact_email]' => e(setting('contact_email')),
            '[contact_phone]' => e(setting('contact_phone')),
            '[address]' => e(setting('address')),
            '[year]' => date('Y'),
        ];
        $html = strtr($html, $codes);
        return (string) preg_replace_callback(
            '~<figure class="embed"[^>]*data-youtube="([A-Za-z0-9_-]{11})"[^>]*>.*?</figure>~s',
            static fn($m) => '<figure class="embed"><div class="ratio ratio-16x9"><iframe src="https://www.youtube-nocookie.com/embed/' . $m[1] . '" title="वीडियो" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></figure>',
            $html
        );
    }

    /** पढ़ने का समय (मिनट), हिंदी के लिए ~200 शब्द/मिनट */
    public static function readingTime(?string $html): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $html))) ?: []);
        return max(1, (int) round($words / 200));
    }
}
