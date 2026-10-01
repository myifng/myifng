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
        $c = preg_match('/^#[0-9a-f]{6}$/i', (string) ($n['category_color'] ?? '')) ? readable_color($n['category_color']) : 'var(--brand)';
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

/**
 * वेबसाइट के फ़ॉर्म का खाना (पुराना मान और त्रुटि के साथ)
 *   ff('text', 'full_name', 'पूरा नाम', ['required' => true])
 *   ff('select', 'gender', 'लिंग', ['options' => [...]])
 */
function ff(string $type, string $name, string $label, array $o = []): string
{
    $id = 'ff_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $err = error($name);
    $val = in_array($type, ['file', 'password'], true) ? '' : (string) old($name, $o['value'] ?? '');
    $req = !empty($o['required']);
    $a = $req ? ' required' : '';
    foreach (($o['attrs'] ?? []) as $k => $v) {
        $a .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    $a .= $err ? ' aria-invalid="true" aria-describedby="' . $id . '_e"' : (!empty($o['help']) ? ' aria-describedby="' . $id . '_h"' : '');
    $lab = '<label for="' . $id . '">' . e($label) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    if ($type === 'select') {
        $opts = '<option value="">' . e($o['empty'] ?? 'चुनें…') . '</option>';
        foreach ($o['options'] ?? [] as $k => $v) {
            $extra = is_array($v) ? ' data-parent="' . e($v[1]) . '"' : '';
            $opts .= '<option value="' . e($k) . '"' . selected($k, $val) . $extra . '>' . e(is_array($v) ? $v[0] : $v) . '</option>';
        }
        $input = '<select id="' . $id . '" name="' . e($name) . '"' . $a . '>' . $opts . '</select>';
    } elseif ($type === 'textarea') {
        $input = '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 3) . '"' . $a . '>' . e($val) . '</textarea>';
    } else {
        $input = '<input type="' . e($type) . '" id="' . $id . '" name="' . e($name) . '"' . ($type !== 'file' ? ' value="' . e($val) . '"' : '') . $a . '>';
    }
    $help = $err ? '<small class="ff-err" id="' . $id . '_e">' . e($err) . '</small>' : (!empty($o['help']) ? '<small class="ff-help" id="' . $id . '_h">' . e($o['help']) . '</small>' : '');
    return '<div class="ff' . ($err ? ' has-err' : '') . (!empty($o['wide']) ? ' ff-wide' : '') . '">' . $lab . $input . $help . '</div>';
}

/**
 * Phase 7: वीडियो / गैलरी / वेब स्टोरी / ऑडियो का कार्ड
 *   mm_card($row)            → $row['kind'] (MultimediaService::list देता है) से
 *   mm_card($row, 'tall')    → 9:16 (वेब स्टोरी, शॉर्ट्स)
 *   mm_card($row, 'row')     → छोटी पंक्ति (साइडबार, प्लेलिस्ट)
 */
function mm_thumb_src(array $m, string $size = 'medium'): ?string
{
    if (!empty($m['cover'])) {
        return media_url($m['cover'], $size);
    }
    if (($m['kind'] ?? '') === 'video' && ($m['source'] ?? '') === 'youtube' && ($id = \App\Services\EmbedService::youtubeId($m['source_url'] ?? null))) {
        return 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
    }
    if (($m['kind'] ?? '') === 'audio' && !empty($m['series_cover'])) {
        return media_url($m['series_cover'], $size);
    }
    return null;
}

function mm_card(array $m, string $variant = 'card', array $o = []): string
{
    $kind = $m['kind'] ?? 'video';
    $url = e(\App\Services\MultimediaService::url($kind, $m));
    $title = e($m['title']);
    $tag = $o['h'] ?? 'h3';
    $src = mm_thumb_src($m, $variant === 'row' ? 'thumb' : 'medium');
    $img = $src ? '<img src="' . e($src) . '" alt="' . $title . '" loading="lazy">' : '<span class="th-empty" style="--c:var(--brand)"><b>' . e(mb_substr((string) $m['title'], 0, 1)) . '</b></span>';
    $dur = \App\Services\EmbedService::duration(isset($m['duration']) ? (int) $m['duration'] : null);
    $badge = match ($kind) {
        'video' => '<span class="mm-badge"><i class="fa-solid fa-play"></i>' . ($dur ? ' ' . e($dur) : '') . '</span>',
        'gallery' => '<span class="mm-badge"><i class="fa-solid fa-images"></i> ' . num($m['photo_count'] ?? 0) . '</span>',
        'story' => '<span class="mm-badge"><i class="fa-solid fa-clone"></i> ' . num($m['slide_count'] ?? 0) . '</span>',
        'audio' => '<span class="mm-badge"><i class="fa-solid fa-headphones"></i>' . ($dur ? ' ' . e($dur) : '') . '</span>',
        default => '',
    };
    $time = !empty($m['published_at']) ? '<time class="time" datetime="' . e($m['published_at']) . '"><i class="fa-regular fa-clock" aria-hidden="true"></i> ' . e(news_time($m['published_at'])) . '</time>' : '';
    $kicker = !empty($o['kicker']) && !empty($m['category']) ? '<span class="kicker">' . e($m['category']) . '</span>' : '';
    $cls = 'mm mm-' . e($kind);
    return match ($variant) {
        'tall' => '<a class="' . $cls . ' mm-tall" href="' . $url . '"><span class="th">' . $img . $badge . '</span><' . $tag . ' class="hd">' . $title . '</' . $tag . '></a>',
        'row' => '<a class="story row ' . $cls . '" href="' . $url . '"><span class="th sm">' . $img . $badge . '</span><span class="row-body"><' . $tag . ' class="hd">' . $title . '</' . $tag . '>' . $time . '</span></a>',
        default => '<a class="story card-story ' . $cls . '" href="' . $url . '"><span class="th">' . $img . $badge . '</span>' . $kicker . '<' . $tag . ' class="hd">' . $title . '</' . $tag . '>' . $time . '</a>',
    };
}

/** Phase 9: विज्ञापन स्लॉट का HTML (विज्ञापन न हो तो ख़ाली) */
function ad_slot(string $key): string
{
    return \App\Services\AdService::slot($key);
}

/** Phase 11: फ़ॉलो बटन (लॉगिन न हो तो भी दिखे; दबाने पर लॉगिन) */
function follow_button(string $type, int $id, string $label, array $follows = []): string
{
    $on = in_array($id, $follows[$type] ?? [], true);
    return '<form method="post" action="' . e(route('account.follow')) . '" class="follow-form">' . csrf_field() . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="follow-chip' . ($on ? ' on' : '') . '" data-follow="' . e($type) . ':' . $id . '" aria-pressed="' . ($on ? 'true' : 'false') . '"><i class="fa-solid ' . ($on ? 'fa-check' : 'fa-plus') . '"></i> ' . e($label) . '</button></form>';
}

/** ख़बर/पेज के टेक्स्ट का संरेखण (सेटिंग → फ़ीचर): prose पर लगने वाली class */
function prose_align_class(): string
{
    return match (setting('article_text_align', 'left')) { 'justify' => ' al-justify', 'justify_lg' => ' al-justify-lg', default => '' };
}
