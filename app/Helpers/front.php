<?php
/**
 * वेबसाइट के कार्ड/लिंक (एक जगह, सभी पेज और होमपेज ब्लॉक में)
 *   news_card($n, 'card'|'row'|'overlay'|'link'|'list', ['kicker' => true, 'summary' => true, 'size' => 'medium'])
 */
declare(strict_types=1);

function news_url(array $n): string
{
    return \App\Services\NewsService::url($n);
}

/** हाल की: "5 मिनट पहले"; पुरानी: तारीख़ */
function news_time(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    return time() - strtotime($dt) < 86400 ? time_ago($dt) : hindi_date($dt);
}

/** इमेज या (न हो तो) ब्रांड रंग वाला खाली खाना */
function news_thumb(array $n, string $size = 'medium', string $class = ''): string
{
    $video = !empty($n['video_url']) ? '<span class="th-play" aria-hidden="true"></span>' : '';
    $flag = !empty($n['is_live']) ? '<span class="th-flag live-flag">LIVE</span>' : (!empty($n['is_breaking']) ? '<span class="th-flag">ब्रेकिंग</span>' : '');
    if (!empty($n['featured_image'])) {
        $img = media_img($n['featured_image'], $size, (string) $n['title']);
    } else {
        $c = preg_match('/^#[0-9a-f]{6}$/i', (string) ($n['category_color'] ?? '')) ? $n['category_color'] : 'var(--brand)';
        $img = '<span class="th-empty" style="--c:' . e($c) . '"><b>' . e(mb_substr((string) ($n['category'] ?: setting('site_name')), 0, 1)) . '</b></span>';
    }
    return '<span class="th' . ($class ? ' ' . e($class) : '') . '">' . $img . $video . $flag . '</span>';
}

function news_kicker(array $n): string
{
    if (!empty($n['is_exclusive'])) {
        return '<span class="kicker">एक्सक्लूसिव</span>';
    }
    return !empty($n['category']) ? '<span class="kicker">' . e($n['category']) . '</span>' : '';
}

function news_card(array $n, string $variant = 'card', array $o = []): string
{
    $url = e(news_url($n));
    $title = e($n['title']);
    $time = '<time class="time" datetime="' . e($n['published_at']) . '"><i class="fa-regular fa-clock" aria-hidden="true"></i> ' . e(news_time($n['published_at'])) . '</time>';
    $kicker = ($o['kicker'] ?? true) ? news_kicker($n) : '';
    $tag = $o['h'] ?? 'h3';
    switch ($variant) {
        case 'overlay':
            return '<a class="story overlay" href="' . $url . '">' . news_thumb($n, $o['size'] ?? 'large') . '<span class="cap">' . $kicker . '<' . $tag . ' class="hd">' . $title . '</' . $tag . '>' . $time . '</span></a>';
        case 'row':
            return '<a class="story row" href="' . $url . '">' . news_thumb($n, 'thumb', 'sm') . '<span class="row-body"><' . $tag . ' class="hd">' . $title . '</' . $tag . '>' . $time . '</span></a>';
        case 'link':
            return '<a class="link-item" href="' . $url . '">' . $title . '</a>';
        case 'list':
            // ताज़ा सूची: समय + शीर्षक
            return '<a class="latest-item" href="' . $url . '"><span class="t">' . e(date('H:i', strtotime($n['published_at']))) . '</span><span class="hd">' . $title . '</span></a>';
        case 'wide':
            // सूची पेज: बड़ी इमेज + सार
            return '<article class="story wide"><a href="' . $url . '" tabindex="-1" aria-hidden="true">' . news_thumb($n, 'medium') . '</a><div>' . $kicker
                . '<' . $tag . ' class="hd"><a href="' . $url . '">' . $title . '</a></' . $tag . '>'
                . (!empty($n['summary']) ? '<p class="sum">' . e(\App\Helpers\Str::limit((string) $n['summary'], 180)) . '</p>' : '')
                . '<span class="meta">' . $time . (!empty($n['location']) ? '<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . e($n['location']) . '</span>' : '') . '</span></div></article>';
        default:
            return '<a class="story card-story" href="' . $url . '">' . news_thumb($n, $o['size'] ?? 'medium') . $kicker . '<' . $tag . ' class="hd">' . $title . '</' . $tag . '>'
                . (!empty($o['summary']) && !empty($n['summary']) ? '<p class="sum">' . e(\App\Helpers\Str::limit((string) $n['summary'], 120)) . '</p>' : '') . $time . '</a>';
    }
}

/** सेक्शन का सिर (लाल पट्टी) + "और पढ़ें" */
function block_head(string $title, ?string $more = null, string $h = 'h2'): string
{
    return '<div class="bhead"><' . $h . '>' . e($title) . '</' . $h . '>' . ($more ? '<a class="more" href="' . e($more) . '">और पढ़ें <i class="fa-solid fa-angle-right" aria-hidden="true"></i></a>' : '') . '</div>';
}
