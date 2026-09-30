<?php
declare(strict_types=1);

namespace App\Services;

/**
 * XML साइटमैप (Google/Bing), Google News साइटमैप, robots.txt और RSS।
 * हर साइटमैप कैश (ख़बरें 5 मिनट, बाकी 30 मिनट); सारे URL मुख्य डोमेन (canonical) से।
 */
final class SitemapService
{
    public const CHUNK = 10000;
    private const PUB = "n.status = 'published' AND n.deleted_at IS NULL AND n.published_at <= NOW() AND n.robots NOT LIKE 'noindex%' AND (n.canonical_url IS NULL OR n.canonical_url = '')";

    /** XML के लिए सुरक्षित टेक्स्ट (नियंत्रण अक्षर हटाकर) */
    public static function x(?string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function loc(string $u): string
    {
        return self::x(SeoService::canonical($u));
    }

    private static function date(?string $d): string
    {
        return $d ? date('c', strtotime($d)) : date('c');
    }

    private static function head(string $ns = ''): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . self::x(asset('sitemap.xsl')) . '"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $ns . '>' . "\n";
    }

    /** इंडेक्स में कौन-कौन से साइटमैप: [[नाम, lastmod], …] */
    public static function parts(): array
    {
        return cache()->remember('sitemap.parts', 1800, static function (): array {
            $out = [['sitemap-pages.xml', null], ['sitemap-sections.xml', null]];
            if (setting('sitemap_news', '1') === '1') {
                $out[] = ['sitemap-news.xml', db()->value('SELECT MAX(n.published_at) FROM {p}news n WHERE ' . self::PUB)];
            }
            foreach (db()->all("SELECT DATE_FORMAT(n.published_at, '%Y-%m') ym, COUNT(*) c, MAX(GREATEST(n.published_at, COALESCE(n.corrected_at, n.published_at))) m FROM {p}news n WHERE " . self::PUB . ' GROUP BY ym ORDER BY ym DESC') as $r) {
                for ($i = 1; $i <= (int) ceil($r['c'] / self::CHUNK); $i++) {
                    $out[] = ['sitemap-posts-' . $r['ym'] . ($i > 1 ? '-' . $i : '') . '.xml', $r['m']];
                }
            }
            $mm = static fn(string $t) => db()->value("SELECT MAX(x.published_at) FROM {p}$t x WHERE " . MultimediaService::published('x'));
            if (setting('sitemap_videos', '1') === '1' && ($m = $mm('videos'))) {
                $out[] = ['sitemap-videos.xml', $m];
            }
            if (setting('sitemap_images', '1') === '1' && ($m = $mm('galleries'))) {
                $out[] = ['sitemap-images.xml', $m];
            }
            if ($m = $mm('web_stories')) {
                $out[] = ['sitemap-stories.xml', $m];
            }
            // Phase 14: फ़ैक्ट चेक, चुनाव, खेल
            try {
                $m = db()->value("SELECT MAX(GREATEST(f.published_at, f.updated_at)) FROM {p}fact_checks f WHERE f.status = 'published'");
                if ($m || db()->value("SELECT id FROM {p}elections WHERE status <> 'draft' LIMIT 1") || db()->value("SELECT id FROM {p}sports_tournaments WHERE status <> 'draft' LIMIT 1")) {
                    $out[] = ['sitemap-special.xml', $m];
                }
            } catch (\Throwable) {
            }
            return $out;
        });
    }

    public static function index(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . self::x(asset('sitemap.xsl')) . '"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::parts() as [$name, $mod]) {
            $x .= '<sitemap><loc>' . self::loc(url($name)) . '</loc>' . ($mod ? '<lastmod>' . self::date($mod) . '</lastmod>' : '') . "</sitemap>\n";
        }
        return $x . '</sitemapindex>';
    }

    /** Google News: पिछले 48 घंटे, 1000 तक */
    public static function news(): string
    {
        return cache()->remember('sitemap.news', 300, static function (): string {
            $x = self::head(' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"');
            $name = (string) (setting('seo_publication') ?: setting('site_name'));
            $lang = (string) setting('language', 'hi');
            foreach (db()->all('SELECT n.slug, n.title, n.published_at FROM {p}news n WHERE ' . self::PUB . ' AND n.published_at >= NOW() - INTERVAL 48 HOUR ORDER BY n.published_at DESC LIMIT 1000') as $n) {
                $x .= '<url><loc>' . self::loc(NewsService::url($n)) . '</loc><news:news><news:publication><news:name>' . self::x($name) . '</news:name><news:language>' . self::x($lang)
                    . '</news:language></news:publication><news:publication_date>' . self::date($n['published_at']) . '</news:publication_date><news:title>' . self::x($n['title']) . "</news:title></news:news></url>\n";
            }
            return $x . '</urlset>';
        });
    }

    /** महीने की ख़बरें (+ मुख्य इमेज) */
    public static function posts(string $ym, int $part): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym) || $part < 1) {
            return null;
        }
        return cache()->remember('sitemap.posts.' . $ym . '.' . $part, 1800, static function () use ($ym, $part): ?string {
            $from = $ym . '-01 00:00:00';
            $to = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
            $rows = db()->all('SELECT n.slug, n.title, n.featured_image, n.image_caption, n.published_at, n.corrected_at, n.updated_at FROM {p}news n WHERE ' . self::PUB
                . ' AND n.published_at >= ? AND n.published_at < ? ORDER BY n.published_at DESC LIMIT ' . self::CHUNK . ' OFFSET ' . (($part - 1) * self::CHUNK), [$from, $to]);
            if (!$rows) {
                return null;
            }
            $img = setting('sitemap_images', '1') === '1';
            $x = self::head($img ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '');
            foreach ($rows as $n) {
                $x .= '<url><loc>' . self::loc(NewsService::url($n)) . '</loc><lastmod>' . self::date($n['corrected_at'] ?: $n['updated_at']) . '</lastmod>';
                if ($img && $n['featured_image']) {
                    $x .= '<image:image><image:loc>' . self::x(media_url($n['featured_image'], 'original')) . '</image:loc></image:image>';
                }
                $x .= "</url>\n";
            }
            return $x . '</urlset>';
        });
    }

    private static function urls(array $list): string
    {
        $x = self::head();
        foreach ($list as [$u, $mod, $freq, $prio]) {
            $x .= '<url><loc>' . self::loc($u) . '</loc>' . ($mod ? '<lastmod>' . self::date($mod) . '</lastmod>' : '') . ($freq ? '<changefreq>' . $freq . '</changefreq>' : '')
                . ($prio ? '<priority>' . $prio . '</priority>' : '') . "</url>\n";
        }
        return $x . '</urlset>';
    }

    /** होम, स्थिर पेज, सूची वाले पेज */
    public static function pages(): string
    {
        return cache()->remember('sitemap.pages', 1800, static function (): string {
            $latest = db()->value('SELECT MAX(n.published_at) FROM {p}news n WHERE ' . self::PUB);
            $list = [[url(), $latest, 'hourly', '1.0'], [route('latest'), $latest, 'hourly', '0.8']];
            $router = app('router');
            foreach (['videos', 'galleries', 'stories', 'audio', 'epaper', 'live_tv'] as $r) {
                if ($router->has($r)) {
                    $list[] = [route($r), null, 'daily', '0.6'];
                }
            }
            foreach (db()->all("SELECT slug, updated_at FROM {p}pages WHERE status = 'published' AND deleted_at IS NULL AND robots NOT LIKE 'noindex%' ORDER BY id") as $p) {
                $list[] = [route('page', ['slug' => $p['slug']]), $p['updated_at'], 'monthly', '0.4'];
            }
            return self::urls($list);
        });
    }

    /** श्रेणी, टॉपिक, टैग, लोकेशन (जिनमें प्रकाशित ख़बरें हैं) */
    public static function sections(): string
    {
        return cache()->remember('sitemap.sections', 1800, static function (): string {
            $list = [];
            foreach (db()->all("SELECT c.slug, MAX(n.published_at) m FROM {p}categories c JOIN {p}news n ON n.category_id = c.id AND " . self::PUB . " WHERE c.status = 'active' GROUP BY c.id ORDER BY c.sort_order") as $c) {
                $list[] = [route('category', ['slug' => $c['slug']]), $c['m'], 'hourly', '0.8'];
            }
            foreach (db()->all("SELECT t.slug, MAX(n.published_at) m FROM {p}topics t JOIN {p}news_topics nt ON nt.topic_id = t.id JOIN {p}news n ON n.id = nt.news_id AND " . self::PUB . " WHERE t.status = 'active' GROUP BY t.id") as $t) {
                $list[] = [route('topic', ['slug' => $t['slug']]), $t['m'], 'daily', '0.6'];
            }
            foreach (db()->all("SELECT l.path, MAX(n.published_at) m FROM {p}locations l JOIN {p}news n ON n.location_id = l.id AND " . self::PUB . " WHERE l.status = 'active' AND l.type <> 'country' GROUP BY l.id") as $l) {
                $list[] = [url($l['path']), $l['m'], 'daily', '0.6'];
            }
            if (setting('seo_noindex_tags', '0') !== '1') {
                foreach (db()->all('SELECT t.slug, MAX(n.published_at) m FROM {p}tags t JOIN {p}news_tags nt ON nt.tag_id = t.id JOIN {p}news n ON n.id = nt.news_id AND ' . self::PUB . ' GROUP BY t.id ORDER BY m DESC LIMIT 5000') as $t) {
                    $list[] = [route('tag', ['slug' => $t['slug']]), $t['m'], 'weekly', '0.4'];
                }
            }
            return self::urls($list);
        });
    }

    /** वीडियो साइटमैप */
    public static function videos(): string
    {
        return cache()->remember('sitemap.videos', 1800, static function (): string {
            $x = self::head(' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"');
            foreach (db()->all('SELECT * FROM {p}videos x WHERE ' . MultimediaService::published('x') . ' ORDER BY x.published_at DESC LIMIT 5000') as $v) {
                $yt = $v['source'] === 'youtube' ? EmbedService::youtubeId((string) $v['source_url']) : null;
                $thumb = $v['cover'] ? media_url($v['cover'], 'large') : ($yt ? 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg' : null);
                $player = $yt ? 'https://www.youtube.com/embed/' . $yt : ($v['source'] === 'embed' ? EmbedService::iframeSrc((string) $v['source_url']) : null);
                $file = $v['file'] ? upload_url($v['file']) : ($v['source'] === 'url' ? EmbedService::streamUrl((string) $v['source_url']) : null);
                if (!$thumb || (!$player && !$file)) {
                    continue; // Google को थंबनेल और वीडियो का पता दोनों चाहिए
                }
                $x .= '<url><loc>' . self::loc(MultimediaService::url('video', $v)) . '</loc><video:video><video:thumbnail_loc>' . self::x($thumb) . '</video:thumbnail_loc><video:title>' . self::x($v['title'])
                    . '</video:title><video:description>' . self::x(mb_substr(strip_tags((string) ($v['description'] ?: $v['title'])), 0, 2000)) . '</video:description>'
                    . ($file ? '<video:content_loc>' . self::x($file) . '</video:content_loc>' : '') . ($player ? '<video:player_loc>' . self::x($player) . '</video:player_loc>' : '')
                    . ($v['duration'] ? '<video:duration>' . min(28800, (int) $v['duration']) . '</video:duration>' : '')
                    . '<video:publication_date>' . self::date($v['published_at']) . "</video:publication_date></video:video></url>\n";
            }
            return $x . '</urlset>';
        });
    }

    /** इमेज साइटमैप: फ़ोटो गैलरी */
    public static function images(): string
    {
        return cache()->remember('sitemap.images', 1800, static function (): string {
            $x = self::head(' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"');
            foreach (db()->all('SELECT x.id, x.slug, x.updated_at FROM {p}galleries x WHERE ' . MultimediaService::published('x') . ' ORDER BY x.published_at DESC LIMIT 5000') as $g) {
                $x .= '<url><loc>' . self::loc(MultimediaService::url('gallery', $g)) . '</loc><lastmod>' . self::date($g['updated_at']) . '</lastmod>';
                foreach (db()->all('SELECT image FROM {p}gallery_photos WHERE gallery_id = ? ORDER BY sort_order LIMIT 1000', [$g['id']]) as $p) {
                    $x .= '<image:image><image:loc>' . self::x(media_url($p['image'], 'original')) . '</image:loc></image:image>';
                }
                $x .= "</url>\n";
            }
            return $x . '</urlset>';
        });
    }

    public static function stories(): string
    {
        return cache()->remember('sitemap.stories', 1800, static function (): string {
            $list = [];
            foreach (db()->all('SELECT x.slug, x.updated_at FROM {p}web_stories x WHERE ' . MultimediaService::published('x') . ' ORDER BY x.published_at DESC LIMIT 5000') as $s) {
                $list[] = [MultimediaService::url('story', $s), $s['updated_at'], null, null];
            }
            return self::urls($list);
        });
    }

    /** फ़ैक्ट चेक + चुनाव (सीटें) + खेल (टूर्नामेंट, मैच) */
    public static function special(): string
    {
        return cache()->remember('sitemap.special', 1800, static function (): string {
            $list = [];
            foreach (db()->all("SELECT f.slug, f.updated_at FROM {p}fact_checks f WHERE f.status = 'published' AND f.published_at <= NOW() ORDER BY f.published_at DESC LIMIT 5000") as $f) {
                $list[] = [route('factcheck.show', ['slug' => $f['slug']]), $f['updated_at'], null, null];
            }
            if (app('router')->has('elections.show')) {
                foreach (db()->all("SELECT id, slug, updated_at FROM {p}elections WHERE status <> 'draft' ORDER BY year DESC LIMIT 200") as $e) {
                    $list[] = [route('elections.show', ['slug' => $e['slug']]), $e['updated_at'], null, null];
                    foreach (db()->all('SELECT s.slug, r.updated_at FROM {p}election_results r JOIN {p}election_seats s ON s.id = r.seat_id WHERE r.election_id = ? LIMIT 1000', [$e['id']]) as $st) {
                        $list[] = [route('elections.seat', ['slug' => $e['slug'], 'seat' => $st['slug']]), $st['updated_at'], null, null];
                    }
                }
            }
            if (app('router')->has('sports.tournament')) {
                foreach (db()->all("SELECT slug, updated_at FROM {p}sports_tournaments WHERE status <> 'draft' LIMIT 500") as $t) {
                    $list[] = [route('sports.tournament', ['slug' => $t['slug']]), $t['updated_at'], null, null];
                }
                foreach (db()->all('SELECT ' . \App\Services\SportsService::MATCH_COLS . ' FROM ' . \App\Services\SportsService::MATCH_FROM . ' WHERE ' . \App\Services\SportsService::VISIBLE . ' ORDER BY m.start_at DESC LIMIT 2000') as $m) {
                    $list[] = [\App\Services\SportsService::url($m), $m['updated_at'], null, null];
                }
            }
            return self::urls($list);
        });
    }

    /** AI ट्रेनिंग वाले क्रॉलर (सेटिंग से रोके जा सकते हैं) */
    public const AI_BOTS = ['GPTBot', 'ChatGPT-User', 'CCBot', 'Google-Extended', 'ClaudeBot', 'anthropic-ai', 'PerplexityBot', 'Bytespider', 'Applebot-Extended', 'meta-externalagent', 'Amazonbot', 'cohere-ai'];

    public static function robots(): string
    {
        $base = rtrim((string) parse_url(SeoService::canonical(url()), PHP_URL_PATH), '/');
        $lines = ['User-agent: *'];
        foreach (['/admin/', '/install/', '/search', '/api/', '/ad/', '/live-updates/', '/application-status', '/join-as-reporter/done'] as $d) {
            $lines[] = 'Disallow: ' . $base . $d;
        }
        $lines[] = 'Allow: ' . $base . '/';
        $extra = trim(str_replace("\r", '', (string) setting('robots_extra')));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = '# अतिरिक्त नियम (SEO सेटिंग)';
            foreach (explode("\n", $extra) as $l) {
                $lines[] = mb_substr(trim($l), 0, 300);
            }
        }
        if (setting('robots_block_ai', '0') === '1') {
            foreach (self::AI_BOTS as $b) {
                $lines[] = '';
                $lines[] = 'User-agent: ' . $b;
                $lines[] = 'Disallow: /';
            }
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . SeoService::canonical(url('sitemap.xml'));
        if (setting('sitemap_news', '1') === '1') {
            $lines[] = 'Sitemap: ' . SeoService::canonical(url('sitemap-news.xml'));
        }
        return implode("\n", $lines) . "\n";
    }

    /** RSS 2.0: ताज़ा या किसी श्रेणी की 50 ख़बरें */
    public static function feed(?array $cat = null): string
    {
        return cache()->remember('sitemap.feed.' . ($cat['id'] ?? 0), 300, static function () use ($cat): string {
            $where = self::PUB;
            $params = [];
            if ($cat) {
                [$w, $params] = NewsQuery::categoryWhere((int) $cat['id']);
                $where .= ' AND ' . $w;
            }
            $rows = db()->all("SELECT n.id, n.slug, n.title, n.summary, n.meta_description, n.featured_image, n.published_at, u.name reporter, c.name cat
                FROM {p}news n LEFT JOIN {p}users u ON u.id = n.reporter_id LEFT JOIN {p}categories c ON c.id = n.category_id WHERE $where ORDER BY n.published_at DESC LIMIT 50", $params);
            $site = (string) setting('site_name');
            $self = $cat ? route('category.feed', ['slug' => $cat['slug']]) : route('feed');
            $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel>'
                . '<title>' . self::x($site . ($cat ? ' - ' . $cat['name'] : '')) . '</title><link>' . self::loc($cat ? route('category', ['slug' => $cat['slug']]) : url()) . '</link>'
                . '<atom:link href="' . self::loc($self) . '" rel="self" type="application/rss+xml"/>'
                . '<description>' . self::x((string) (setting('site_description') ?: setting('tagline'))) . '</description><language>' . self::x((string) setting('language', 'hi')) . '</language>'
                . '<lastBuildDate>' . date(DATE_RSS, $rows ? strtotime($rows[0]['published_at']) : time()) . '</lastBuildDate>' . "\n";
            foreach ($rows as $n) {
                $u = self::loc(NewsService::url($n));
                $x .= '<item><title>' . self::x($n['title']) . '</title><link>' . $u . '</link><guid isPermaLink="true">' . $u . '</guid>'
                    . '<pubDate>' . date(DATE_RSS, strtotime($n['published_at'])) . '</pubDate>'
                    . ($n['reporter'] ? '<dc:creator>' . self::x($n['reporter']) . '</dc:creator>' : '') . ($n['cat'] ? '<category>' . self::x($n['cat']) . '</category>' : '')
                    . '<description>' . self::x((string) ($n['summary'] ?: $n['meta_description'])) . '</description>'
                    . ($n['featured_image'] ? '<media:content url="' . self::x(media_url($n['featured_image'], 'large')) . '" medium="image"/>' : '') . "</item>\n";
            }
            return $x . '</channel></rss>';
        });
    }
}
