<?php
use App\Services\NewsService;
$this->layout('layouts/front');
$yt = $news['video_url'] && preg_match('~(?:youtu\.be/|v=|shorts/|live/|embed/)([A-Za-z0-9_-]{11})~', $news['video_url'], $m) ? $m[1] : null;
$share = array_filter(explode(',', (string) setting('share_buttons', 'whatsapp,facebook,x,telegram,copy,native')));
$u = rawurlencode($shareUrl);
$t = rawurlencode($news['title']);
$shareLinks = [
    'whatsapp' => ['https://wa.me/?text=' . $t . '%20' . $u, 'fa-brands fa-whatsapp', 'WhatsApp', 's-wa'],
    'facebook' => ['https://www.facebook.com/sharer/sharer.php?u=' . $u, 'fa-brands fa-facebook-f', 'Facebook', 's-fb'],
    'x' => ['https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, 'fa-brands fa-x-twitter', 'X', 's-x'],
    'telegram' => ['https://t.me/share/url?url=' . $u . '&text=' . $t, 'fa-brands fa-telegram', 'Telegram', 's-tg'],
    'linkedin' => ['https://www.linkedin.com/sharing/share-offsite/?url=' . $u, 'fa-brands fa-linkedin-in', 'LinkedIn', 's-in'],
];
$locName = $locationChain ? end($locationChain)['name'] : null;
?>
<div class="wrap page-wrap with-side">
  <article class="box article news-article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <?php if ($previewNote): ?><p class="updated"><i class="fa-solid fa-eye"></i> <?= e($previewNote) ?></p><?php endif; ?>
    <div class="art-flags">
      <?php foreach (\App\Models\News::FLAGS as $f => [$label, $icon]): if (!empty($news[$f]) && in_array($f, ['is_breaking', 'is_live', 'is_exclusive', 'is_sponsored'], true)): ?><span class="flag flag-<?= e(substr($f, 3)) ?>"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?></span><?php endif; endforeach; ?>
      <?php if ($category): ?><a class="kicker" href="<?= e(route('category', ['slug' => $category['slug']])) ?>"><?= e($category['name']) ?></a><?php endif; ?>
    </div>
    <h1 class="page-title"><?= e($news['title']) ?></h1>
    <?php if ($news['subtitle']): ?><p class="dek"><?= e($news['subtitle']) ?></p><?php endif; ?>
    <div class="byline">
      <div class="by-meta">
        <?php if ($reporter): ?><b><?= e($reporter['name']) ?></b><?php endif; ?>
        <?php if ($locName): ?><span><i class="fa-solid fa-location-dot"></i> <?php $last = end($locationChain); ?><?= $last['path'] ? '<a href="' . e(url($last['path'])) . '">' . e($locName) . '</a>' : e($locName) ?></span><?php endif; ?>
        <span><time datetime="<?= e($news['published_at'] ? date('c', strtotime($news['published_at'])) : '') ?>"><?= $news['published_at'] ? hindi_date($news['published_at'], true) : 'अभी प्रकाशित नहीं' ?></time></span>
        <?php if ($news['corrected_at']): ?><span>अपडेट: <?= hindi_date($news['corrected_at'], true) ?></span><?php endif; ?>
        <?php if (setting('reading_time', '1') === '1'): ?><span><?= NewsService::readingTime((int) $news['word_count']) ?> मिनट में पढ़ें</span><?php endif; ?>
        <?php if (setting('show_views') === '1'): ?><span><i class="fa-regular fa-eye"></i> <?= num($news['views']) ?></span><?php endif; ?>
      </div>
      <?php if ($share && !$isPreview): ?>
        <div class="share" aria-label="शेयर करें">
          <?php foreach ($share as $k): if (isset($shareLinks[$k])): [$href, $ic, $lab, $cls] = $shareLinks[$k]; ?><a class="<?= e($cls) ?>" href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e($lab) ?> पर शेयर करें"><i class="<?= e($ic) ?>"></i></a><?php endif; endforeach; ?>
          <?php if (in_array('copy', $share, true)): ?><button type="button" class="s-cp" data-copy-link="<?= e($shareUrl) ?>" aria-label="लिंक कॉपी करें"><i class="fa-solid fa-link"></i></button><?php endif; ?>
          <?php if (in_array('native', $share, true)): ?><button type="button" class="s-native" data-native-share data-title="<?= e($news['title']) ?>" data-url="<?= e($shareUrl) ?>" aria-label="शेयर करें" hidden><i class="fa-solid fa-share-nodes"></i></button><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php if (!$isPreview): ?><?= ad_slot('article_top') ?><?php endif; ?>
    <?php if ($news['summary']): ?><p class="summary"><?= e($news['summary']) ?></p><?php endif; ?>
    <?php if ($news['featured_image']): ?>
      <figure class="feature"><?= media_img($news['featured_image'], 'large', $news['image_caption'] ?: $news['title'], ['loading' => 'eager', 'fetchpriority' => 'high']) ?>
        <?php if ($news['image_caption'] || $news['image_credit']): ?><figcaption><?= e($news['image_caption']) ?><?= $news['image_credit'] ? ' <small>(' . e($news['image_credit']) . ')</small>' : '' ?></figcaption><?php endif; ?>
      </figure>
    <?php endif; ?>
    <?php if ($news['correction_note']): ?><aside class="correction" role="note"><b><i class="fa-solid fa-circle-info"></i> सुधार/अपडेट (<?= hindi_date($news['corrected_at'], true) ?>):</b> <?= e($news['correction_note']) ?></aside><?php endif; ?>
    <?php if ($yt): ?><div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="वीडियो: <?= e($news['title']) ?>" loading="lazy" allowfullscreen></iframe></div>
    <?php elseif ($news['video_url']): ?><video class="art-video" src="<?= e(upload_url($news['video_url'])) ?>" controls preload="metadata"></video><?php endif; ?>
    <?php if ($news['audio_file']): ?><audio class="art-audio" src="<?= e(upload_url($news['audio_file'])) ?>" controls preload="none"></audio><?php endif; ?>
    <?php if (!empty($live)): ?><?= $this->insert('partials/front/live-blog', ['live' => $live, 'liveUpdates' => $liveUpdates, 'isPreview' => $isPreview]) ?><?php endif; ?>
    <div class="prose"><?= $content /* सेव करते समय sanitize */ ?></div>
    <?php if ($rel['gallery']): ?>
      <div class="art-gallery"><?php foreach ($rel['gallery'] as $g): ?><figure><a href="<?= e(media_url($g, 'large')) ?>" target="_blank" rel="noopener"><?= media_img($g, 'medium') ?></a><?php if ($g['caption']): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($news['source'] || $news['news_credit']): ?><p class="updated"><?= $news['source'] ? 'स्रोत: ' . e($news['source']) : '' ?><?= $news['news_credit'] ? ($news['source'] ? ' · ' : '') . e($news['news_credit']) : '' ?></p><?php endif; ?>
    <?php if (!$isPreview): ?><?= ad_slot('article_bottom') ?><?php endif; ?>
    <?php if ($rel['tag_links']): ?><div class="art-tags"><?php foreach ($rel['tag_links'] as $tg): ?><a href="<?= e(route('tag', ['slug' => $tg['slug']])) ?>">#<?= e($tg['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php if ($reporter && setting('author_box', '1') === '1'): ?>
      <div class="author-box"><?= avatar_html($reporter['avatar'], $reporter['name']) ?><div><b><?= e($reporter['name']) ?></b><?php if ($reporter['bio']): ?><p><?= e($reporter['bio']) ?></p><?php endif; ?></div></div>
    <?php endif; ?>
    <?php if ($related): ?>
      <section class="related"><?= block_head('ये भी पढ़ें') ?><div class="cgrid cols-3"><?php foreach ($related as $r): ?><?= news_card($r, 'card') ?><?php endforeach; ?></div></section>
    <?php endif; ?>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
