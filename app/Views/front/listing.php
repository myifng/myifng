<?php
$this->layout('layouts/front');
$lead = $items && $items->page === 1 && !$search && $items->items ? $items->items[0] : null;
$rest = $items ? ($lead ? array_slice($items->items, 1) : $items->items) : [];
$c = preg_match('/^#[0-9a-f]{6}$/i', (string) $color) ? $color : null;
?>
<div class="wrap page-wrap with-side listing-page"<?= $c ? ' style="--cat:' . e($c) . '"' : '' ?>>
  <div class="main-col">
    <?php if ($banner && !empty($special)): ?><div class="special-banner"><?= media_img($banner, 'large', $heading, ['loading' => 'eager']) ?></div><?php endif; ?>
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $u]): ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><?= $u ? '<a href="' . e($u) . '">' . e($n) . '</a>' : '<span aria-current="page">' . e($n) . '</span>' ?><?php endforeach; ?></nav>
      <div class="lh-row">
        <h1 class="list-title"><?php if (!empty($icon)): ?><i class="fa-solid <?= e(preg_replace('/^fa-(solid|regular|brands)\s+/', '', (string) $icon)) ?>" aria-hidden="true"></i> <?php endif; ?><?= e($heading) ?></h1>
        <?= $actions /* कंट्रोलर में escape */ ?>
      </div>
      <?php if ($desc): ?><p class="list-desc"><?= e($desc) ?></p><?php endif; ?>
      <?php if ($search): ?>
        <form class="search-form" action="<?= e(route('search')) ?>" method="get" role="search">
          <input type="search" name="q" value="<?= e($search['q']) ?>" placeholder="क्या खोजना है?" aria-label="खोज" maxlength="100" autofocus>
          <select name="category" aria-label="श्रेणी"><option value="">सभी श्रेणियाँ</option><?php foreach ($search['categories'] as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $search['category']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
          <button class="btn" type="submit">खोजें</button>
        </form>
      <?php endif; ?>
      <?php if ($chips): ?>
        <nav class="chips" aria-label="<?= e($chipsLabel ?? 'उप-श्रेणियाँ') ?>"><?php foreach ($chips as [$n, $u, $active]): ?><a href="<?= e($u) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($n) ?></a><?php endforeach; ?></nav>
      <?php endif; ?>
    </header>
    <?= ad_slot('category_top') ?>

    <?php if ($items && $items->items): ?>
      <?php if ($lead): ?><div class="box list-lead"><?= news_card($lead, 'wide', ['h' => 'h2']) ?></div><?php endif; ?>
      <?php if ($rest): ?><div class="box listing"><?php foreach ($rest as $n): ?><?= news_card($n, 'wide', ['h' => 'h2']) ?><?php endforeach; ?></div><?php endif; ?>
      <?php if ($items->pages > 1): ?>
        <nav class="pager" aria-label="पेज">
          <?php if ($items->page > 1): ?><a href="<?= e($items->url($items->page - 1)) ?>" rel="prev"><i class="fa-solid fa-angle-left"></i> पिछला</a><?php endif; ?>
          <span>पेज <?= num($items->page) ?> / <?= num($items->pages) ?></span>
          <?php if ($items->page < $items->pages): ?><a href="<?= e($items->url($items->page + 1)) ?>" rel="next">अगला <i class="fa-solid fa-angle-right"></i></a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="box empty"><p><?= e($empty) ?></p><a class="more" href="<?= e(route('latest')) ?>">ताज़ा ख़बरें पढ़ें <i class="fa-solid fa-angle-right"></i></a></div>
    <?php endif; ?>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
