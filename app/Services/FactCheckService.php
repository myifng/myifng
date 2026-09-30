<?php
declare(strict_types=1);

namespace App\Services;

/** फ़ैक्ट चेक (§32): फ़ैसले, सार्वजनिक पता, ClaimReview स्कीमा, स्रोतों की सूची */
final class FactCheckService
{
    /** फ़ैसला => [लेबल, रंग-class, आइकन, ClaimReview रेटिंग 1-5, अंग्रेज़ी नाम] */
    public const VERDICTS = [
        'false' => ['झूठ', 'fc-false', 'fa-circle-xmark', 1, 'False'],
        'misleading' => ['भ्रामक', 'fc-misleading', 'fa-triangle-exclamation', 2, 'Misleading'],
        'partly_true' => ['आंशिक सच', 'fc-partly', 'fa-circle-half-stroke', 3, 'Partly true'],
        'unverified' => ['अपुष्ट', 'fc-unverified', 'fa-circle-question', 0, 'Unverified'],
        'true' => ['सच', 'fc-true', 'fa-circle-check', 5, 'True'],
    ];

    public const MEDIUMS = ['social' => 'सोशल मीडिया', 'whatsapp' => 'WhatsApp', 'video' => 'वीडियो', 'tv' => 'TV / चैनल', 'speech' => 'भाषण / बयान', 'news' => 'ख़बर / वेबसाइट', 'other' => 'अन्य'];
    public const STATUSES = ['draft' => ['ड्राफ़्ट', 'secondary'], 'review' => ['समीक्षा में', 'warning'], 'published' => ['प्रकाशित', 'success'], 'archived' => ['आर्काइव', 'light']];

    public const PUBLISHED = "f.status = 'published' AND f.published_at <= NOW()";

    public static function url(array $f): string
    {
        return route('factcheck.show', ['slug' => $f['slug']]);
    }

    public static function verdict(string $v): array
    {
        return self::VERDICTS[$v] ?? self::VERDICTS['unverified'];
    }

    /** फ़ैसले का बैज (HTML) */
    public static function badge(string $v, string $size = ''): string
    {
        [$l, $cls, $ic] = self::verdict($v);
        return '<span class="fc-badge ' . $cls . ($size ? ' ' . $size : '') . '"><i class="fa-solid ' . $ic . '" aria-hidden="true"></i> ' . e($l) . '</span>';
    }

    /** "नाम | https://…" लाइनें → [[title, url]] (सिर्फ़ http/https) */
    public static function parseSources(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            $line = trim(strip_tags($line));
            if ($line === '') {
                continue;
            }
            [$t, $u] = str_contains($line, '|') ? array_map('trim', explode('|', $line, 2)) : [$line, ''];
            if ($u === '' && preg_match('~^https?://\S+$~i', $t)) {
                [$t, $u] = [parse_url($t, PHP_URL_HOST) ?: $t, $t];
            }
            if ($u !== '' && !preg_match('~^https?://[^\s<>"]+$~i', $u)) {
                $u = '';
            }
            $out[] = [mb_substr($t, 0, 190), mb_substr($u, 0, 500)];
            if (count($out) >= 30) {
                break;
            }
        }
        return $out;
    }

    public static function sources(?string $json): array
    {
        $a = json_decode((string) $json, true);
        return is_array($a) ? $a : [];
    }

    public static function sourcesText(?string $json): string
    {
        return implode("\n", array_map(static fn($s) => $s[1] ? $s[0] . ' | ' . $s[1] : $s[0], self::sources($json)));
    }

    /** सूची: प्रकाशित, वैकल्पिक फ़ैसला */
    public static function latest(int $limit = 6, ?string $verdict = null, int $offset = 0): array
    {
        $w = self::PUBLISHED . ($verdict && isset(self::VERDICTS[$verdict]) ? ' AND f.verdict = ?' : '');
        return db()->all("SELECT f.id, f.title, f.slug, f.claim, f.verdict, f.summary, f.image, f.published_at, f.claim_medium, c.name category
            FROM {p}fact_checks f LEFT JOIN {p}categories c ON c.id = f.category_id WHERE $w ORDER BY f.published_at DESC LIMIT " . max(1, min(100, $limit)) . ' OFFSET ' . max(0, $offset),
            $verdict && isset(self::VERDICTS[$verdict]) ? [$verdict] : []);
    }

    /** ख़बर से जुड़ी प्रकाशित फ़ैक्ट चेक */
    public static function forNews(int $newsId): array
    {
        return db()->all('SELECT f.title, f.slug, f.verdict, f.claim FROM {p}fact_checks f WHERE f.news_id = ? AND ' . self::PUBLISHED . ' ORDER BY f.published_at DESC LIMIT 3', [$newsId]);
    }

    /** Google ClaimReview */
    public static function schema(array $f, ?string $author): string
    {
        [, , , $rating, $en] = self::verdict($f['verdict']);
        $claim = ['@type' => 'Claim', 'datePublished' => $f['claim_date'] ?: null,
            'author' => $f['claim_by'] ? ['@type' => 'Organization', 'name' => $f['claim_by']] : null,
            'appearance' => $f['claim_url'] ? [['@type' => 'CreativeWork', 'url' => $f['claim_url']]] : null];
        $s = ['@context' => 'https://schema.org', '@type' => 'ClaimReview', 'url' => self::url($f), 'claimReviewed' => mb_substr(strip_tags((string) $f['claim']), 0, 500),
            'datePublished' => date('c', strtotime((string) $f['published_at'])), 'itemReviewed' => array_filter($claim),
            'author' => ['@type' => 'Organization', 'name' => (string) setting('site_name'), 'url' => url()],
            'reviewRating' => array_filter(['@type' => 'Rating', 'ratingValue' => $rating ?: null, 'bestRating' => $rating ? 5 : null, 'worstRating' => $rating ? 1 : null, 'alternateName' => self::verdict($f['verdict'])[0] . ' (' . $en . ')'])];
        if ($author) {
            $s['author']['member'] = ['@type' => 'Person', 'name' => $author];
        }
        return SeoService::json($s);
    }
}
