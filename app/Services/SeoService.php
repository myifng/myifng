<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SEO: Schema.org JSON-LD (WebSite, Organization, NewsArticle, BreadcrumbList, FAQPage)
 * और लेआउट के मेटा टैग (शीर्षक, robots, canonical, Open Graph, Twitter) एक जगह से।
 */
final class SeoService
{
    public static function json(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }

    public const SOCIAL = ['facebook', 'twitter', 'youtube', 'instagram', 'telegram', 'linkedin', 'whatsapp_channel', 'sharechat'];

    private static function publisher(bool $full = false): array
    {
        $p = ['@type' => 'NewsMediaOrganization', 'name' => (string) setting('site_name'), 'url' => self::canonical(url())];
        if (setting('logo')) {
            $p['logo'] = ['@type' => 'ImageObject', 'url' => upload_url((string) setting('logo'))];
        }
        if ($full) {
            $same = array_values(array_filter(array_map(static fn($k) => (string) setting($k), self::SOCIAL)));
            if ($same) {
                $p['sameAs'] = $same;
            }
            if (setting('contact_email') || setting('contact_phone')) {
                $p['contactPoint'] = array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => setting('contact_email') ?: null, 'telephone' => setting('contact_phone') ?: null]);
            }
            if (setting('address')) {
                $p['address'] = (string) setting('address');
            }
        }
        return $p;
    }

    /** मुख्य डोमेन (सेटिंग) के हिसाब से पूरा URL */
    public static function canonical(string $u): string
    {
        $host = rtrim((string) setting('seo_canonical_host'), '/');
        $base = rtrim((string) config('app.url'), '/');
        if ($host !== '' && $base !== '' && str_starts_with($u, $base)) {
            return $host . substr($u, strlen($base));
        }
        return $u;
    }

    /** अभी का पता, बिना ट्रैकिंग पैरामीटर (सिर्फ़ page बचे) */
    public static function currentUrl(): string
    {
        $req = app('request');
        $u = url(ltrim($req->path(), '/'));
        $page = (int) ($_GET['page'] ?? 0);
        return self::canonical($page > 1 ? $u . '?page=' . $page : $u);
    }

    /** robots मान + Discover के लिए बड़ी इमेज */
    public static function robots(string $r): string
    {
        $r = $r !== '' ? $r : 'index,follow';
        if (!str_contains($r, 'noindex') && setting('seo_image_preview', '1') === '1') {
            $r .= ',max-image-preview:large,max-snippet:-1,max-video-preview:-1';
        }
        return $r;
    }

    /** लेआउट के लिए सारे मेटा मान (पेज का $seo + सेटिंग) */
    public static function head(array $seo): array
    {
        $site = (string) setting('site_name');
        $sep = (string) setting('seo_separator', '|');
        if (!empty($seo['full_title'])) {
            $title = (string) $seo['full_title'];
        } elseif (!empty($seo['title'])) {
            $title = $sep === '' ? (string) $seo['title'] : $seo['title'] . ' ' . $sep . ' ' . $site;
        } else {
            $title = $site . (setting('tagline') ? ' ' . ($sep ?: '|') . ' ' . setting('tagline') : '');
        }
        $desc = (string) ($seo['description'] ?? setting('site_description'));
        $robots = (string) ($seo['robots'] ?? 'index,follow');
        $canonical = !empty($seo['canonical']) ? (string) $seo['canonical'] : (str_contains($robots, 'noindex') ? '' : self::currentUrl());
        if ($canonical !== '') {
            $canonical = self::canonical($canonical);
        }
        $imgOf = static fn(?string $i) => $i ? (preg_match('~^https?://~', $i) ? $i : media_url($i, 'large')) : '';
        $img = $imgOf($seo['og_image'] ?? null) ?: $imgOf($seo['image'] ?? null)
            ?: (setting('seo_og_image') ? upload_url((string) setting('seo_og_image')) : (setting('logo') ? upload_url((string) setting('logo')) : ''));
        $ogTitle = (string) (($seo['og_title'] ?? '') ?: (($seo['title'] ?? '') ?: $site));
        $ogDesc = (string) (($seo['og_description'] ?? '') ?: $desc);
        $tw = ltrim((string) setting('seo_twitter'), '@');
        return ['title' => $title, 'description' => $desc, 'robots' => self::robots($robots), 'canonical' => $canonical, 'image' => $img,
            'og_title' => $ogTitle, 'og_description' => $ogDesc, 'twitter' => $tw !== '' ? '@' . $tw : ''];
    }

    public static function website(): string
    {
        return self::json([
            '@context' => 'https://schema.org', '@graph' => [
                ['@type' => 'WebSite', 'name' => (string) setting('site_name'), 'url' => url(), 'inLanguage' => (string) setting('language', 'hi'),
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search') . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                self::publisher(true),
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

    /** FAQPage (सिर्फ़ जब सवाल-जवाब हों) */
    public static function faq(array $items): string
    {
        $q = [];
        foreach ($items as $f) {
            if (($f['q'] ?? '') !== '' && ($f['a'] ?? '') !== '') {
                $q[] = ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]];
            }
        }
        return $q ? self::json(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $q]) : '';
    }

    /** ख़बर का FAQ (JSON कॉलम) → [[q, a], …] */
    public static function faqItems(?string $json): array
    {
        $rows = json_decode((string) $json, true);
        return is_array($rows) ? array_values(array_filter($rows, static fn($r) => is_array($r) && ($r['q'] ?? '') !== '' && ($r['a'] ?? '') !== '')) : [];
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
            $m = db()->first('SELECT width, height, alt, caption FROM {p}media WHERE file = ? LIMIT 1', [$n['featured_image']]);
            $data['image'] = [array_filter(['@type' => 'ImageObject', 'url' => media_url($n['featured_image'], 'original'),
                'width' => $m && $m['width'] ? (int) $m['width'] : null, 'height' => $m && $m['height'] ? (int) $m['height'] : null,
                'caption' => $n['image_caption'] ?: ($m['caption'] ?? null)]), media_url($n['featured_image'], 'large')];
        }
        $yt = !empty($n['video_url']) ? EmbedService::youtubeId((string) $n['video_url']) : null;
        if ($yt) {
            $data['video'] = ['@type' => 'VideoObject', 'name' => mb_substr((string) $n['title'], 0, 110), 'description' => (string) ($n['summary'] ?: $n['title']),
                'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg', 'uploadDate' => date('c', strtotime((string) $n['published_at'])),
                'embedUrl' => 'https://www.youtube.com/embed/' . $yt];
        }
        $data['isAccessibleForFree'] = true;
        if ($section) {
            $data['articleSection'] = $section;
        }
        if ($tags) {
            $data['keywords'] = implode(', ', $tags);
        }
        return self::json($data);
    }
}
