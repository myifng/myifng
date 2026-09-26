<div class="box">
  <?php if ($heading): ?><?= block_head($heading, $more) ?><?php endif; ?>
  <div class="hscroll" data-hscroll>
    <div class="hs-track"><?php foreach ($items as $n): ?><div class="hs-item"><?= news_card($n, 'card') ?></div><?php endforeach; ?></div>
    <button type="button" class="sl-btn prev" aria-label="पीछे"><i class="fa-solid fa-angle-left"></i></button><button type="button" class="sl-btn next" aria-label="आगे"><i class="fa-solid fa-angle-right"></i></button>
  </div>
</div>
