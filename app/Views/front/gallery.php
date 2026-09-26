<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap gallery-page">
  <article class="box article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php $i = 0; foreach ($crumbs as [$n, $u]): if (!$u) continue; ?><?= $i++ ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($u) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <?php if ($g['category']): ?><div class="art-flags"><a class="kicker" href="<?= e(route('category', ['slug' => $g['category_slug']])) ?>"><?= e($g['category']) ?></a></div><?php endif; ?>
    <h1 class="page-title"><?= e($g['title']) ?></h1>
    <div class="byline">
      <div class="by-meta">
        <?php if ($g['photographer']): ?><b><i class="fa-solid fa-camera"></i> <?= e($g['photographer']) ?></b><?php endif; ?>
        <?php if ($loc): ?><span><i class="fa-solid fa-location-dot"></i> <?= $loc['path'] ? '<a href="' . e(url($loc['path'])) . '">' . e($loc['name']) . '</a>' : e($loc['name']) ?></span><?php endif; ?>
        <span><time datetime="<?= e(date('c', strtotime($g['published_at']))) ?>"><?= hindi_date($g['published_at'], true) ?></time></span>
        <span><i class="fa-regular fa-images"></i> <?= num(count($photos)) ?> फ़ोटो</span>
      </div>
      <?= $this->insert('partials/front/share', ['shareUrl' => $shareUrl, 'shareTitle' => $g['title']]) ?>
    </div>
    <?php if ($g['description']): ?><p class="summary"><?= nl2br(e($g['description'])) ?></p><?php endif; ?>
    <div class="photo-grid" data-lightbox>
      <?php foreach ($photos as $i => $p): $credit = implode(' · ', array_filter([$p['photographer'] ? 'फ़ोटो: ' . $p['photographer'] : null, $p['credit'], $p['copyright'] ? '© ' . $p['copyright'] : null, $p['location']])); ?>
        <figure class="pg-item" id="photo-<?= $i + 1 ?>">
          <a href="<?= e(media_url($p['image'], 'large')) ?>" data-lb-item data-caption="<?= e($p['caption']) ?>" data-credit="<?= e($credit) ?>" aria-label="फ़ोटो <?= $i + 1 ?> बड़ा देखें"><?= media_img($p['image'], 'medium', (string) ($p['caption'] ?: $g['title'] . ' - फ़ोटो ' . ($i + 1)), ['loading' => $i < 4 ? 'eager' : 'lazy']) ?><span class="pg-no"><?= $i + 1 ?>/<?= count($photos) ?></span></a>
          <?php if ($p['caption'] || $credit): ?><figcaption><?= e($p['caption']) ?><?= $credit ? ' <small>' . e($credit) . '</small>' : '' ?></figcaption><?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php if ($related): ?>
      <section class="related"><?= block_head('और गैलरी', route('galleries')) ?><div class="cgrid cols-4"><?php foreach ($related as $r): ?><?= mm_card($r) ?><?php endforeach; ?></div></section>
    <?php endif; ?>
  </article>
</div>
<div class="lightbox" data-lb hidden role="dialog" aria-modal="true" aria-label="फ़ोटो गैलरी">
  <button type="button" class="lb-close" data-lb-close aria-label="बंद करें (Esc)">×</button>
  <button type="button" class="lb-nav prev" data-lb-prev aria-label="पिछली फ़ोटो"><i class="fa-solid fa-angle-left"></i></button>
  <figure class="lb-stage"><img alt="" data-lb-img><figcaption><span data-lb-count></span> <span data-lb-caption></span> <small data-lb-credit></small></figcaption></figure>
  <button type="button" class="lb-nav next" data-lb-next aria-label="अगली फ़ोटो"><i class="fa-solid fa-angle-right"></i></button>
</div>
