<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\LiveTvProgram;

/**
 * लाइव टीवी: चैनल → सुरक्षित प्लेयर (YouTube / iframe src / HLS या MP4), आज का शेड्यूल, अभी का कार्यक्रम।
 * कोई चालू चैनल न हो तो पुरानी सेटिंग (live_tv_url) वाला YouTube लिंक।
 */
final class LiveTvService
{
    public static function channels(): array
    {
        return cache()->remember('home.livetv', 300, static fn() => db()->all(
            "SELECT * FROM {p}live_tv_channels WHERE status = 'active' ORDER BY is_default DESC, sort_order, name"
        ));
    }

    /** डिफ़ॉल्ट चैनल, या सेटिंग के YouTube लिंक से बना चैनल */
    public static function main(): ?array
    {
        $ch = self::channels();
        if ($ch) {
            return $ch[0];
        }
        $url = (string) setting('live_tv_url');
        return EmbedService::youtubeEmbed($url) ? ['id' => 0, 'name' => 'लाइव टीवी', 'slug' => '', 'source_type' => 'youtube', 'source_url' => $url,
            'logo' => null, 'description' => null, 'is_live' => 1, 'is_default' => 1] : null;
    }

    public static function find(string $slug): ?array
    {
        foreach (self::channels() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** हेडर का LIVE बटन */
    public static function isOn(): bool
    {
        $m = self::main();
        return $m !== null && (int) $m['is_live'] === 1;
    }

    /** @return array{type: string, src: string, hls?: bool}|null */
    public static function player(array $c, bool $autoplay = false): ?array
    {
        return match ($c['source_type']) {
            'youtube' => ($src = EmbedService::youtubeEmbed($c['source_url'], $autoplay)) ? ['type' => 'iframe', 'src' => $src] : null,
            'embed' => ($src = EmbedService::iframeSrc($c['source_url'])) ? ['type' => 'iframe', 'src' => $src] : null,
            'stream' => ($src = EmbedService::streamUrl($c['source_url'])) ? ['type' => 'video', 'src' => $src, 'hls' => EmbedService::isHls($src)] : null,
            default => null,
        };
    }

    /** आज के कार्यक्रम (समय क्रम), हर एक पर 'now' / 'done' */
    public static function today(int $channelId): array
    {
        if (!$channelId) {
            return [];
        }
        $dow = (string) date('w');
        $nowT = date('H:i:s');
        $rows = db()->all("SELECT * FROM {p}live_tv_programs WHERE channel_id = ? AND status = 'active' ORDER BY start_time", [$channelId]);
        $out = [];
        foreach ($rows as $p) {
            if (!in_array($dow, explode(',', (string) $p['days']), true)) {
                continue;
            }
            $overnight = $p['end_time'] <= $p['start_time']; // रात 11 से 1 बजे
            $p['now'] = $overnight ? ($nowT >= $p['start_time'] || $nowT < $p['end_time']) : ($nowT >= $p['start_time'] && $nowT < $p['end_time']);
            $p['done'] = !$p['now'] && !$overnight && $nowT >= $p['end_time'];
            $out[] = $p;
        }
        return $out;
    }

    public static function current(int $channelId): ?array
    {
        foreach (self::today($channelId) as $p) {
            if ($p['now']) {
                return $p;
            }
        }
        return null;
    }

    public static function daysLabel(string $days): string
    {
        $d = array_map('intval', array_filter(explode(',', $days), 'strlen'));
        sort($d);
        if (count($d) === 7) {
            return 'रोज़';
        }
        if ($d === [1, 2, 3, 4, 5]) {
            return 'सोम–शुक्र';
        }
        if ($d === [0, 6]) {
            return 'शनि–रवि';
        }
        return implode(', ', array_map(static fn($x) => LiveTvProgram::DAYS[$x] ?? '', array_merge(array_diff($d, [0]), in_array(0, $d, true) ? [0] : [])));
    }

    public static function time(string $t): string
    {
        return date('g:i A', strtotime($t));
    }

    public static function changed(): void
    {
        cache()->flush('home');
    }
}
