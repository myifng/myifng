<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\LiveBlog;

/**
 * लाइव ब्लॉग: किसी ख़बर पर समय वाले अपडेट।
 * अपडेट का टेक्स्ट सादा रखा जाता है (HTML नहीं); दिखाते समय escape + पैराग्राफ़ + **बोल्ड** + लिंक।
 * लाइव/रुका = ख़बर पर is_live; ख़त्म = is_live हटता है।
 */
final class LiveBlogService
{
    public static function forNews(int $newsId): ?array
    {
        return db()->first('SELECT * FROM {p}live_blogs WHERE news_id = ?', [$newsId]);
    }

    /** नए पहले; $after से बड़े ID वाले (पोलिंग के लिए) */
    public static function updates(int $blogId, int $after = 0, int $limit = 300): array
    {
        return db()->all('SELECT lu.*, u.name AS author FROM {p}live_updates lu LEFT JOIN {p}users u ON u.id = lu.author_id
                          WHERE lu.live_blog_id = ? AND lu.id > ? ORDER BY lu.posted_at DESC, lu.id DESC LIMIT ' . max(1, min($limit, 500)), [$blogId, $after]);
    }

    public static function start(int $newsId): int
    {
        $id = LiveBlog::create(['news_id' => $newsId, 'status' => 'live', 'started_at' => now(), 'created_by' => auth()->id()]);
        db()->query('UPDATE {p}news SET is_live = 1 WHERE id = ?', [$newsId]);
        self::changed();
        return $id;
    }

    public static function setStatus(array $blog, string $status): void
    {
        LiveBlog::update((int) $blog['id'], ['status' => $status, 'ended_at' => $status === 'ended' ? now() : null]);
        db()->query('UPDATE {p}news SET is_live = ? WHERE id = ?', [$status === 'ended' ? 0 : 1, $blog['news_id']]);
        self::changed();
    }

    public static function changed(): void
    {
        cache()->flush('live');
        cache()->flush('home');
    }

    /** सादा टेक्स्ट → सुरक्षित HTML */
    public static function bodyHtml(?string $text): string
    {
        $text = trim(str_replace("\r", '', (string) $text));
        if ($text === '') {
            return '';
        }
        $out = '';
        foreach (preg_split('/\n{2,}/', $text) as $para) {
            $h = e($para);
            $h = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $h);
            $h = preg_replace_callback('~(https?://[^\s<]+[^\s<.,;:!?)\]\'"])~u', static fn($m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener nofollow">' . $m[1] . '</a>', $h);
            $out .= '<p>' . nl2br($h, false) . '</p>';
        }
        return $out;
    }

    /** 10:35 PM (आज) / 12 अक्टू, 10:35 PM (पुराना) */
    public static function timeLabel(string $dt): string
    {
        $t = strtotime($dt);
        $clock = date('g:i', $t) . ' ' . (date('A', $t) === 'AM' ? 'AM' : 'PM');
        return date('Y-m-d', $t) === date('Y-m-d') ? $clock : hindi_date($dt) . ', ' . $clock;
    }

    /** वेबसाइट (और प्रशासन) पर एक अपडेट का HTML */
    public static function render(array $u, bool $admin = false): string
    {
        $embed = '';
        if ($u['embed_url']) {
            $yt = EmbedService::youtubeEmbed($u['embed_url']);
            $link = EmbedService::href($u['embed_url']);
            $embed = $yt ? '<div class="video-embed"><iframe src="' . e($yt) . '" title="वीडियो" loading="lazy" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe></div>'
                : ($link ? '<a class="lu-link" href="' . e($link) . '" target="_blank" rel="noopener nofollow"><i class="fa-solid fa-arrow-up-right-from-square"></i> ' . e(parse_url($link, PHP_URL_HOST) ?: $link) . '</a>' : '');
        }
        return '<article class="lu' . ($u['is_key'] ? ' is-key' : '') . ($u['is_pinned'] ? ' is-pinned' : '') . '" id="update-' . (int) $u['id'] . '" data-id="' . (int) $u['id'] . '">'
            . '<div class="lu-time"><time datetime="' . e(date('c', strtotime($u['posted_at']))) . '">' . e(self::timeLabel($u['posted_at'])) . '</time>'
            . ($u['is_pinned'] ? '<span class="lu-badge pin"><i class="fa-solid fa-thumbtack"></i> पिन</span>' : '')
            . ($u['is_key'] ? '<span class="lu-badge key">अहम</span>' : '') . '</div>'
            . '<div class="lu-body">' . ($u['title'] ? '<h3>' . e($u['title']) . '</h3>' : '') . self::bodyHtml($u['body'])
            . ($u['image'] ? '<figure>' . media_img($u['image'], 'large', (string) ($u['title'] ?: 'लाइव अपडेट')) . '</figure>' : '') . $embed
            . (!empty($u['author']) && ($admin || setting('live_show_author', '1') === '1') ? '<span class="lu-author">— ' . e($u['author']) . '</span>' : '')
            . '</div></article>';
    }

    /** schema.org LiveBlogPosting (Google "LIVE" बैज) */
    public static function schema(array $news, array $blog, array $updates): string
    {
        $items = [];
        foreach (array_slice($updates, 0, 50) as $u) {
            $items[] = ['@type' => 'BlogPosting', 'headline' => mb_substr((string) ($u['title'] ?: strip_tags((string) $u['body'])), 0, 110) ?: 'अपडेट',
                'datePublished' => date('c', strtotime($u['posted_at'])), 'articleBody' => mb_substr(trim((string) $u['body']), 0, 1000),
                'url' => NewsService::url($news) . '#update-' . $u['id']];
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'LiveBlogPosting', '@id' => NewsService::url($news) . '#live',
            'headline' => mb_substr((string) $news['title'], 0, 110), 'url' => NewsService::url($news),
            'coverageStartTime' => date('c', strtotime($blog['started_at'])), 'liveBlogUpdate' => $items,
            'datePublished' => date('c', strtotime((string) ($news['published_at'] ?: $blog['started_at']))),
            'dateModified' => date('c', strtotime($updates[0]['posted_at'] ?? $blog['updated_at']))];
        if ($blog['status'] === 'ended' && $blog['ended_at']) {
            $data['coverageEndTime'] = date('c', strtotime($blog['ended_at']));
        }
        return SeoService::json($data);
    }
}
