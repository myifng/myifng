<?php use App\Services\EpaperService; $main = $cards[0]; $rest = array_slice($cards, 1); ?>
<div class="box ep-block">
  <?= block_head($title ?: 'आज का ई-पेपर', route('epaper')) ?>
  <div class="epb">
    <a class="epb-main" href="<?= e(EpaperService::url($main['edition'], $main['issue'])) ?>">
      <span class="epi-cover"><?= $main['issue']['cover'] ? '<img src="' . e(upload_url($main['issue']['cover'])) . '" alt="' . e($main['edition']['name']) . ' ई-पेपर" loading="lazy">' : '' ?></span>
      <span><b><?= e($main['edition']['name']) ?></b><small><?= hindi_date($main['issue']['issue_date']) ?></small><span class="btn-cta"><i class="fa-solid fa-book-open"></i> पढ़ें</span></span>
    </a>
    <?php if ($rest): ?>
      <ul class="epb-list"><?php foreach ($rest as $c): ?><li><a href="<?= e(EpaperService::url($c['edition'], $c['issue'])) ?>"><i class="fa-regular fa-newspaper"></i> <?= e($c['edition']['name']) ?> <small><?= hindi_date($c['issue']['issue_date']) ?></small></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
