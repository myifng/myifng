<?php
/** वीडियो / फ़ोटो / वेब स्टोरी / ऑडियो की सूची वाले पेज */
$this->layout('layouts/front');
$tall ??= false;
$playlists ??= [];
$series ??= [];
$banner ??= null;
$desc ??= null;
?>
<div class="wrap page-wrap mm-page mm-<?= e($kind) ?>">
  <header class="list-head box">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php $i = 0; foreach ($crumbs as [$n, $u]): ?><?= $i++ ? ' <span aria-hidden="true">›</span> ' : '' ?><?= $u ? '<a href="' . e($u) . '">' . e($n) . '</a>' : '<span aria-current="page">' . e($n) . '</span>' ?><?php endforeach; ?></nav>
    <?php if ($banner): ?><div class="mm-banner"><?= media_img($banner, 'large', $heading, ['loading' => 'eager']) ?></div><?php endif; ?>
    <div class="lh-row"><h1 class="list-title"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i> <?= e($heading) ?></h1><?= $actions ?? '' ?></div>
    <?php if ($desc): ?><p class="list-desc"><?= e($desc) ?></p><?php endif; ?>
    <?php if ($chips): ?><nav class="chips" aria-label="प्रकार"><?php foreach ($chips as [$n, $u, $active]): ?><a href="<?= e($u) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($n) ?></a><?php endforeach; ?></nav><?php endif; ?>
  </header>

  <?php if ($playlists): ?>
    <section class="box"><?= block_head('प्लेलिस्ट और शो') ?>
      <div class="pl-row"><?php foreach ($playlists as $p): ?><a class="pl-chip" href="<?= e(route('videos.playlist', ['slug' => $p['slug']])) ?>"><?= $p['cover'] ? media_img($p['cover'], 'thumb', '') : '<i class="fa-solid ' . ($p['type'] === 'show' ? 'fa-tv' : 'fa-list') . '"></i>' ?><span><?= e($p['name']) ?></span></a><?php endforeach; ?></div>
    </section>
  <?php endif; ?>
  <?php if ($series): ?>
    <section class="box"><?= block_head('पॉडकास्ट') ?>
      <div class="cgrid cols-4"><?php foreach ($series as $s): ?>
        <a class="story card-story pod-card" href="<?= e(route('podcast', ['slug' => $s['slug']])) ?>"><span class="th sq"><?= $s['cover'] ? media_img($s['cover'], 'medium', $s['title']) : '<span class="th-empty" style="--c:var(--brand)"><b><i class="fa-solid fa-podcast"></i></b></span>' ?></span>
          <h3 class="hd"><?= e($s['title']) ?></h3><span class="time"><?= num($s['episodes']) ?> एपिसोड</span></a>
      <?php endforeach; ?></div>
    </section>
  <?php endif; ?>

  <?php if ($items->items): ?>
    <section class="box">
      <?php if (!empty($listHeading)): ?><?= block_head($listHeading) ?><?php endif; ?>
      <div class="cgrid <?= $tall ? 'tall-grid' : 'cols-4' ?>"><?php foreach ($items->items as $m): ?><?= mm_card($m, $tall ? 'tall' : 'card', ['kicker' => !$tall, 'h' => 'h2']) ?><?php endforeach; ?></div>
    </section>
    <?php if ($items->pages > 1): ?>
      <nav class="pager" aria-label="पेज">
        <?php if ($items->page > 1): ?><a href="<?= e($items->url($items->page - 1)) ?>" rel="prev"><i class="fa-solid fa-angle-left"></i> पिछला</a><?php endif; ?>
        <span>पेज <?= num($items->page) ?> / <?= num($items->pages) ?></span>
        <?php if ($items->page < $items->pages): ?><a href="<?= e($items->url($items->page + 1)) ?>" rel="next">अगला <i class="fa-solid fa-angle-right"></i></a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="box empty"><p>अभी यहाँ कुछ नहीं है। जल्द ही नई सामग्री आएगी।</p><a class="more" href="<?= e(url()) ?>">होमपेज पर जाएँ <i class="fa-solid fa-angle-right"></i></a></div>
  <?php endif; ?>
</div>
