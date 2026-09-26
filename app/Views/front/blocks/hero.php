<?php $layout = $set['layout'] ?? 'hero-3col'; $lead = $items[0]; $rest = array_slice($items, 1); ?>
<?php if ($layout === 'hero-slider'): ?>
  <div class="box"><div class="slider" data-slider data-autoplay="1">
    <div class="slides"><?php foreach ($items as $n): ?><div class="slide"><?= news_card($n, 'overlay', ['h' => 'h2']) ?></div><?php endforeach; ?></div>
    <button type="button" class="sl-btn prev" aria-label="पिछली"><i class="fa-solid fa-angle-left"></i></button><button type="button" class="sl-btn next" aria-label="अगली"><i class="fa-solid fa-angle-right"></i></button>
  </div></div>
<?php elseif ($layout === 'hero-grid'): ?>
  <div class="box hero-grid">
    <div class="hg-lead"><?= news_card($lead, 'overlay', ['h' => 'h2']) ?></div>
    <div class="hg-rest"><?php foreach (array_slice($rest, 0, 4) as $n): ?><?= news_card($n, 'card', ['size' => 'thumb']) ?><?php endforeach; ?></div>
  </div>
<?php else: ?>
  <div class="top">
    <div class="box col1">
      <?= news_card($lead, 'overlay', ['h' => 'h2']) ?>
      <?php if ($rest): ?><div class="pair"><?php foreach (array_slice($rest, 0, 4) as $n): ?><?= news_card($n, 'card', ['size' => 'thumb']) ?><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="box col2">
      <?= block_head('ताज़ा ख़बरें', app('router')->has('latest') ? route('latest') : null) ?>
      <div class="latest"><?php foreach ($latest as $n): ?><?= news_card($n, 'list') ?><?php endforeach; ?></div>
    </div>
    <?php if ($popular): ?>
    <div class="box col3">
      <?= block_head('सबसे ज़्यादा पढ़ी') ?>
      <ol class="ranked"><?php foreach ($popular as $n): ?><li><?= news_card($n, 'link') ?></li><?php endforeach; ?></ol>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
