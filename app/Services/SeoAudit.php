<?php
declare(strict_types=1);

namespace App\Services;

/** SEO जाँच: प्रकाशित ख़बरों की कमियाँ, साइट की जाँच-सूची, टूटे अंदरूनी लिंक */
final class SeoAudit
{
    /** [लेबल, SQL शर्त (n = news), गंभीर?, सुझाव] */
    public static function issues(): array
    {
        $p = db()->table('news');
        return [
            'no_desc' => ['विवरण नहीं', "(COALESCE(n.meta_description, '') = '' AND COALESCE(n.summary, '') = '')", true, 'SEO विवरण या सार लिखें (70-160 अक्षर)। Google यही दिखाता है।'],
            'no_image' => ['मुख्य इमेज नहीं', "COALESCE(n.featured_image, '') = ''", true, 'Google News/Discover में इमेज वाली ख़बरें ही अच्छी दिखती हैं।'],
            'small_image' => ['इमेज 1200px से छोटी', "EXISTS (SELECT 1 FROM {p}media m WHERE m.file = n.featured_image AND m.width > 0 AND m.width < 1200)", false, 'Discover के लिए कम से कम 1200px चौड़ी इमेज।'],
            'long_title' => ['SEO शीर्षक लंबा (70+)', "CHAR_LENGTH(COALESCE(NULLIF(n.meta_title, ''), n.title)) > 70", false, 'Google में शीर्षक कट जाएगा; SEO शीर्षक 60 के आसपास रखें।'],
            'short_title' => ['शीर्षक बहुत छोटा (20 से कम)', 'CHAR_LENGTH(n.title) < 20', false, 'शीर्षक में ख़बर की पूरी बात और मुख्य शब्द हों।'],
            'long_desc' => ['SEO विवरण लंबा (160+)', "CHAR_LENGTH(COALESCE(n.meta_description, '')) > 160", false, '160 अक्षर के बाद Google काट देता है।'],
            'thin' => ['कम शब्द (150 से कम)', 'n.word_count < 150', true, 'बहुत छोटी ख़बरें "कम जानकारी वाला पेज" मानी जा सकती हैं।'],
            'no_tags' => ['टैग नहीं', "NOT EXISTS (SELECT 1 FROM {p}news_tags t WHERE t.news_id = n.id)", false, '3-5 टैग जोड़ें; अंदरूनी लिंक और टैग पेज बनते हैं।'],
            'no_category' => ['श्रेणी नहीं', 'n.category_id IS NULL', true, 'हर ख़बर किसी श्रेणी में हो (ब्रेडक्रम्ब और सेक्शन)।'],
            'no_caption' => ['इमेज का कैप्शन नहीं', "COALESCE(n.featured_image, '') <> '' AND COALESCE(n.image_caption, '') = ''", false, 'कैप्शन इमेज का alt टेक्स्ट भी बनता है।'],
            'dup_title' => ['एक जैसे शीर्षक', "n.title IN (SELECT d.title FROM (SELECT title FROM `$p` WHERE status = 'published' AND deleted_at IS NULL GROUP BY title HAVING COUNT(*) > 1) d)", true, 'दो ख़बरों का एक ही शीर्षक Google को भ्रमित करता है।'],
            'noindex' => ['noindex (Google में नहीं)', "n.robots LIKE 'noindex%'", false, 'जान-बूझकर हो तो ठीक; वरना "सर्च इंजन" में Index चुनें।'],
            'canonical_out' => ['canonical दूसरी साइट पर', "COALESCE(n.canonical_url, '') <> ''", false, 'ये ख़बरें साइटमैप में नहीं जातीं।'],
        ];
    }

    /** हर कमी की गिनती + कुल + पूरी तरह ठीक ख़बरें */
    public static function counts(): array
    {
        return cache()->remember('sitemap.audit', 300, static function (): array {
            $sel = ['COUNT(*) AS total'];
            $core = [];
            foreach (self::issues() as $k => [, $sql, $serious]) {
                $sel[] = "SUM(CASE WHEN $sql THEN 1 ELSE 0 END) AS `$k`";
                if ($serious) {
                    $core[] = "($sql)";
                }
            }
            $sel[] = 'SUM(CASE WHEN ' . implode(' OR ', $core) . ' THEN 0 ELSE 1 END) AS healthy';
            $r = db()->first('SELECT ' . implode(', ', $sel) . ' FROM {p}news n WHERE ' . NewsQuery::PUBLISHED);
            return array_map('intval', $r ?? []);
        });
    }

    public static function score(array $c): int
    {
        return $c['total'] ? (int) round(100 * $c['healthy'] / $c['total']) : 100;
    }

    /** साइट-स्तर की जाँच: [लेबल, ठीक?, सुझाव, लिंक] */
    public static function site(): array
    {
        $base = (string) config('app.url');
        $robotsExtra = (string) setting('robots_extra');
        $social = array_filter(array_map(static fn($k) => setting($k), SeoService::SOCIAL));
        return [
            ['साइट का लोगो', (bool) setting('logo'), 'स्कीमा (Organization) और Google News के लिए ज़रूरी।', route('admin.settings', ['tab' => 'branding'])],
            ['साइट का विवरण', (bool) (setting('seo_home_description') ?: setting('site_description')), 'होमपेज का विवरण Google में दिखता है।', route('admin.seo.settings', ['tab' => 'seo'])],
            ['HTTPS', str_starts_with($base, 'https://') || str_starts_with((string) setting('seo_canonical_host'), 'https://'), 'SSL लगाकर config/env.php में https:// वाला पता रखें।', null],
            ['Google Search Console verification', (bool) setting('search_console'), 'Search Console में साइट जोड़ें और साइटमैप जमा करें।', route('admin.settings', ['tab' => 'analytics'])],
            ['सोशल प्रोफ़ाइल (sameAs)', count($social) > 0, 'Facebook/X/YouTube के पते जोड़ें; Google संस्था को पहचानता है।', route('admin.settings', ['tab' => 'social'])],
            ['डिफ़ॉल्ट शेयर इमेज', (bool) setting('seo_og_image'), 'बिना इमेज वाले पेज शेयर करने पर यह दिखेगी (1200×630)।', route('admin.seo.settings', ['tab' => 'seo'])],
            ['Google News साइटमैप चालू', setting('sitemap_news', '1') === '1', 'Publisher Center/Search Console में /sitemap-news.xml जमा करें।', route('admin.seo.settings', ['tab' => 'seo'])],
            ['robots.txt पूरी साइट नहीं रोकता', !preg_match('~^\s*Disallow:\s*/\s*$~mi', $robotsExtra), '"Disallow: /" हटाएँ, वरना साइट Google से हट जाएगी।', route('admin.seo.settings', ['tab' => 'seo_robots'])],
            ['कोई पुरानी robots.txt फ़ाइल नहीं', !is_file(BASE_PATH . '/robots.txt'), 'इंस्टॉल फ़ोल्डर में robots.txt फ़ाइल हो तो उसे हटाएँ; वरना अपने-आप वाला robots.txt नहीं चलेगा।', null],
            ['कोई पुरानी sitemap.xml फ़ाइल नहीं', !is_file(BASE_PATH . '/sitemap.xml'), 'पुरानी sitemap.xml फ़ाइल हटाएँ; साइटमैप अब अपने आप बनता है।', null],
        ];
    }

    /** प्रकाशित ख़बरों में अपनी साइट के टूटे लिंक: [[news_id, title, href, कारण], …] */
    public static function brokenLinks(int $limit = 2000): array
    {
        $rows = db()->all('SELECT n.id, n.title, n.content FROM {p}news n WHERE ' . NewsQuery::PUBLISHED . ' ORDER BY n.published_at DESC LIMIT ' . $limit);
        $checks = [
            'news' => ["SELECT slug FROM {p}news WHERE status = 'published' AND deleted_at IS NULL AND published_at <= NOW()", 'ख़बर नहीं मिली (हटी, ड्राफ़्ट या स्लग बदला)'],
            'category' => ["SELECT slug FROM {p}categories WHERE status = 'active'", 'श्रेणी नहीं मिली'],
            'topic' => ["SELECT slug FROM {p}topics WHERE status = 'active'", 'टॉपिक नहीं मिला'],
            'tag' => ['SELECT slug FROM {p}tags', 'टैग नहीं मिला'],
            'page' => ["SELECT slug FROM {p}pages WHERE status = 'published' AND deleted_at IS NULL", 'पेज नहीं मिला'],
        ];
        $known = [];
        $out = [];
        foreach ($rows as $n) {
            if (!preg_match_all('~<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']~i', (string) $n['content'], $m)) {
                continue;
            }
            foreach (array_unique($m[1]) as $href) {
                $h = html_entity_decode($href, ENT_QUOTES);
                if (!str_starts_with($h, '/') && !RedirectService::own($h)) {
                    continue; // बाहरी लिंक
                }
                $path = RedirectService::normalize($h);
                if (!preg_match('~^/(news|category|topic|tag|page)/([^/]+)$~', $path, $pm)) {
                    continue;
                }
                $known[$pm[1]] ??= array_flip(array_column(db()->all($checks[$pm[1]][0]), 'slug'));
                if (isset($known[$pm[1]][$pm[2]])) {
                    continue;
                }
                $redir = RedirectService::match($path);
                $out[] = ['id' => (int) $n['id'], 'title' => $n['title'], 'href' => $h, 'path' => $path,
                    'reason' => $checks[$pm[1]][1], 'redirect' => $redir ? ($redir['url'] ?? '410') : null];
            }
        }
        return $out;
    }
}
