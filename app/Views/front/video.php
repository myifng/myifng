<?php
use App\Models\Video;
use App\Services\EmbedService;
$this->layout('layouts/front');
$short = $v['type'] === 'short';
?>
<div class="wrap page-wrap with-side video-page">
  <article class="box article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php $i = 0; foreach ($crumbs as [$n, $u]): if (!$u) continue; ?><?= $i++ ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($u) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <div class="vp-player<?= $short ? ' is-short' : '' ?>">
      <?php if (!$player): ?><div class="vp-missing">वीडियो अभी उपलब्ध नहीं है।</div>
      <?php elseif ($player['type'] === 'iframe'): ?><iframe src="<?= e($player['src']) ?>" title="<?= e($v['title']) ?>" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"<?= !empty($player['sandbox']) ? ' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"' : '' ?>></iframe>
      <?php else: ?><video src="<?= e($player['src']) ?>" controls playsinline preload="metadata"<?= ($t = mm_thumb_src($v, 'large')) ? ' poster="' . e($t) . '"' : '' ?>></video><?php endif; ?>
    </div>
    <div class="art-flags"><span class="kicker"><?= e(Video::TYPES[$v['type']]) ?></span><?php if ($v['category']): ?> <a class="kicker" href="<?= e(route('category', ['slug' => $v['category_slug']])) ?>"><?= e($v['category']) ?></a><?php endif; ?></div>
    <h1 class="page-title"><?= e($v['title']) ?></h1>
    <div class="byline">
      <div class="by-meta">
        <?php if ($v['credit']): ?><b><?= e($v['credit']) ?></b><?php endif; ?>
        <span><time datetime="<?= e(date('c', strtotime($v['published_at']))) ?>"><?= hindi_date($v['published_at'], true) ?></time></span>
        <?php if ($v['duration']): ?><span><i class="fa-regular fa-clock"></i> <?= e(EmbedService::duration((int) $v['duration'])) ?></span><?php endif; ?>
        <?php if (setting('show_views') === '1'): ?><span><i class="fa-regular fa-eye"></i> <?= num($v['views']) ?></span><?php endif; ?>
      </div>
      <?= $this->insert('partials/front/share', ['shareUrl' => $shareUrl, 'shareTitle' => $v['title']]) ?>
    </div>
    <?php if ($v['description']): ?><div class="prose"><?php foreach (preg_split('/\n{2,}/', trim($v['description'])) as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($related): ?>
      <section class="related"><?= block_head('और वीडियो', route('videos')) ?><div class="cgrid cols-4"><?php foreach ($related as $r): ?><?= mm_card($r) ?><?php endforeach; ?></div></section>
    <?php endif; ?>
  </article>
  <?php ob_start(); ?>
    <?php if ($more): ?>
      <div class="box"><h2 class="box-title"><?= e($v['playlist']) ?></h2><div class="rows"><?php foreach ($more as $m): ?><?= mm_card($m, 'row') ?><?php endforeach; ?></div>
        <a class="more" href="<?= e(route('videos.playlist', ['slug' => $v['playlist_slug']])) ?>">पूरी प्लेलिस्ट <i class="fa-solid fa-angle-right"></i></a></div>
    <?php endif; ?>
  <?php $before = ob_get_clean(); ?>
  <?= $this->insert('partials/front/sidebar', ['side' => $side, 'before' => $before]) ?>
</div>
