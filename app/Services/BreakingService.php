<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BreakingNews;

/**
 * ब्रेकिंग कंट्रोल रूम: अभी चालू आइटम → टिकर, होमपेज अलर्ट बैनर, मोबाइल अलर्ट।
 * चालू = status active + शुरू हो चुका + ख़त्म नहीं हुआ (अपने आप समाप्ति के लिए cron नहीं चाहिए)।
 * 60 सेकंड कैश ('home' समूह में; बदलाव पर साफ़)।
 */
final class BreakingService
{
    public const ACTIVE = "b.status = 'active' AND b.starts_at <= NOW() AND (b.ends_at IS NULL OR b.ends_at > NOW())";

    /** @return array<int, array> प्राथमिकता, फिर नया पहले */
    public static function active(): array
    {
        return cache()->remember('home.breaking', 60, static function (): array {
            $rows = db()->all('SELECT b.id, b.title, b.type, b.priority, b.url, b.show_ticker, b.show_banner, b.mobile_alert, b.starts_at, n.slug AS news_slug
                FROM {p}breaking_news b LEFT JOIN {p}news n ON n.id = b.news_id AND ' . NewsQuery::PUBLISHED . '
                WHERE ' . self::ACTIVE . ' ORDER BY b.priority DESC, b.starts_at DESC, b.id DESC LIMIT 20');
            return array_map(static fn($b) => $b + ['link' => self::link($b)], $rows);
        });
    }

    public static function link(array $b): ?string
    {
        if (!empty($b['news_slug'])) {
            return route('news.show', ['slug' => $b['news_slug']]);
        }
        return EmbedService::href($b['url'] ?? null);
    }

    /**
     * टिकर: कंट्रोल रूम के आइटम + (48 घंटे की) ब्रेकिंग फ़्लैग वाली ख़बरें; कुछ न हो तो ताज़ा ख़बरें
     * @return array{breaking: bool, items: array<int, array{title: string, url: ?string, type: string, priority: int}>}
     */
    public static function ticker(int $limit = 12): array
    {
        $items = [];
        $seen = [];
        foreach (self::active() as $b) {
            if ($b['show_ticker']) {
                $items[] = ['title' => $b['title'], 'url' => $b['link'], 'type' => $b['type'], 'priority' => (int) $b['priority']];
                $seen[] = $b['link'];
            }
        }
        $news = NewsQuery::ticker($limit);
        if ($news['breaking'] || !$items) {
            foreach ($news['items'] as $n) {
                $u = news_url($n);
                if (!in_array($u, $seen, true)) {
                    $items[] = ['title' => $n['title'], 'url' => $u, 'type' => $news['breaking'] ? 'breaking' : 'latest', 'priority' => 1];
                }
            }
        }
        return ['breaking' => $news['breaking'] || array_filter($items, static fn($i) => $i['type'] !== 'latest'), 'items' => array_slice($items, 0, $limit)];
    }

    public static function banner(): ?array
    {
        foreach (self::active() as $b) {
            if ($b['show_banner']) {
                return $b;
            }
        }
        return null;
    }

    public static function mobileAlert(): ?array
    {
        foreach (self::active() as $b) {
            if ($b['mobile_alert']) {
                return $b;
            }
        }
        return null;
    }

    /** प्रशासन में: scheduled / live / expired / off */
    public static function state(array $b): string
    {
        if ($b['status'] !== 'active') {
            return 'off';
        }
        if (strtotime($b['starts_at']) > time()) {
            return 'scheduled';
        }
        return $b['ends_at'] && strtotime($b['ends_at']) <= time() ? 'expired' : 'live';
    }

    public const STATES = ['live' => ['success', 'चल रहा है'], 'scheduled' => ['info', 'शेड्यूल'], 'expired' => ['secondary', 'समाप्त'], 'off' => ['secondary', 'बंद']];

    /** डिफ़ॉल्ट समाप्ति: अभी + सेटिंग के घंटे (0 = कभी नहीं) */
    public static function defaultEnds(?string $from = null): ?string
    {
        $h = max(0, min(720, (int) setting('breaking_expiry_hours', '6')));
        return $h ? date('Y-m-d H:i:s', strtotime($from ?? 'now') + $h * 3600) : null;
    }

    public static function changed(): void
    {
        cache()->flush('home');
    }

    public static function countActive(): int
    {
        return (int) db()->value('SELECT COUNT(*) FROM {p}breaking_news b WHERE ' . self::ACTIVE);
    }

    /** पुश: अभी सिर्फ़ कतार (Phase 11 का नोटिफ़िकेशन सेंटर भेजेगा) */
    public static function queuePush(int $id): void
    {
        BreakingNews::update($id, ['push_status' => 'queued']);
    }
}
