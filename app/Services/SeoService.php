<?php
declare(strict_types=1);

namespace App\Services;

/** Schema.org JSON-LD (Google News/Discover के लिए): WebSite, NewsArticle, BreadcrumbList */
final class SeoService
{
    public static function json(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }

    private static function publisher(): array
    {
        $p = ['@type' => 'NewsMediaOrganization', 'name' => (string) setting('site_name'), 'url' => url()];
        if (setting('logo')) {
            $p['logo'] = ['@type' => 'ImageObject', 'url' => upload_url((string) setting('logo'))];
        }
        return $p;
    }

    public static function website(): string
    {
        return self::json([
            '@context' => 'https://schema.org', '@graph' => [
                ['@type' => 'WebSite', 'name' => (string) setting('site_name'), 'url' => url(), 'inLanguage' => (string) setting('language', 'hi'),
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                self::publisher(),
            ],
        ]);
    }

    /** $crumbs = [[नाम, url|null], …] */
    public static function breadcrumbs(array $crumbs, ?string $current = null): string
    {
        $items = [];
        foreach ($crumbs as $i => [$name, $u]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name] + (($u ?? $current) ? ['item' => $u ?? $current] : []);
        }
        return self::json(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }

    public static function article(array $n, ?string $reporter, ?string $section, array $tags): string
    {
        $data = [
            '@context' => 'https://schema.org', '@type' => 'NewsArticle',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => NewsService::url($n)],
            'headline' => mb_substr((string) $n['title'], 0, 110), 'description' => (string) ($n['summary'] ?: $n['meta_description']),
            'datePublished' => date('c', strtotime((string) $n['published_at'])),
            'dateModified' => date('c', strtotime((string) ($n['corrected_at'] ?: $n['updated_at']))),
            'inLanguage' => (string) setting('language', 'hi'), 'publisher' => self::publisher(),
            'author' => $reporter ? ['@type' => 'Person', 'name' => $reporter] : self::publisher(),
            'wordCount' => (int) $n['word_count'],
        ];
        if ($n['featured_image']) {
            $data['image'] = [media_url($n['featured_image'], 'large'), media_url($n['featured_image'], 'original')];
        }
        if ($section) {
            $data['articleSection'] = $section;
        }
        if ($tags) {
            $data['keywords'] = implode(', ', $tags);
        }
        return self::json($data);
    }
}
