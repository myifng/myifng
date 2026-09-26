<?php
use App\Models\AudioItem;
use App\Services\EmbedService;
$this->layout('layouts/front');
$cover = mm_thumb_src($a, 'medium');
?>
<div class="wrap page-wrap with-side audio-page">
  <article class="box article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php $i = 0; foreach ($crumbs as [$n, $u]): if (!$u) continue; ?><?= $i++ ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($u) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <div class="ap-head">
      <span class="ap-cover"><?= $cover ? '<img src="' . e($cover) . '" alt="">' : '<i class="fa-solid fa-headphones"></i>' ?></span>
      <div>
        <div class="art-flags"><span class="kicker"><?= e(AudioItem::TYPES[$a['type']]) ?><?= $a['episode_no'] ? ' · एपिसोड ' . num($a['episode_no']) : '' ?></span>
          <?php if ($a['series']): ?><a class="kicker" href="<?= e(route('podcast', ['slug' => $a['series_slug']])) ?>"><?= e($a['series']) ?></a><?php endif; ?></div>
        <h1 class="page-title"><?= e($a['title']) ?></h1>
        <div class="by-meta"><span><time datetime="<?= e(date('c', strtotime($a['published_at']))) ?>"><?= hindi_date($a['published_at'], true) ?></time></span>
          <?php if ($a['duration']): ?><span><i class="fa-regular fa-clock"></i> <?= e(EmbedService::duration((int) $a['duration'])) ?></span><?php endif; ?></div>
      </div>
    </div>
    <?php if ($src): ?><audio class="ap-player" src="<?= e($src) ?>" controls preload="metadata"></audio><?php else: ?><p class="notice notice-info">ऑडियो जल्द उपलब्ध होगा।</p><?php endif; ?>
    <div class="byline"><div class="by-meta"><?php if ($news): ?><span><i class="fa-regular fa-newspaper"></i> पढ़ें: <a href="<?= e(route('news.show', ['slug' => $news['slug']])) ?>"><?= e($news['title']) ?></a></span><?php endif; ?></div>
      <?= $this->insert('partials/front/share', ['shareUrl' => $shareUrl, 'shareTitle' => $a['title']]) ?></div>
    <?php if ($a['description']): ?><div class="prose"><?php foreach (preg_split('/\n{2,}/', trim($a['description'])) as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?></div><?php endif; ?>
    <?php if ($a['transcript']): ?>
      <details class="transcript"><summary><i class="fa-solid fa-align-left"></i> पूरा पाठ पढ़ें</summary>
        <div class="prose"><?php foreach (preg_split('/\n{2,}/', trim($a['transcript'])) as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?></div></details>
    <?php endif; ?>
    <?php if ($more): ?>
      <section class="related"><?= block_head($a['series'] ? 'इस सीरीज़ के और एपिसोड' : 'और ऑडियो', $a['series'] ? route('podcast', ['slug' => $a['series_slug']]) : route('audio')) ?>
        <div class="cgrid cols-4"><?php foreach ($more as $m): ?><?= mm_card($m) ?><?php endforeach; ?></div></section>
    <?php endif; ?>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
