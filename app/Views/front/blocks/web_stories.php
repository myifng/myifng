<div class="box">
  <?= block_head($title ?: 'वेब स्टोरी', $more) ?>
  <div class="hscroll" data-hscroll><div class="hs-track tall-track"><?php foreach ($items as $s): ?><div class="hs-item tall"><?= mm_card($s, 'tall') ?></div><?php endforeach; ?></div>
    <button type="button" class="sl-btn prev" aria-label="पीछे"><i class="fa-solid fa-angle-left"></i></button><button type="button" class="sl-btn next" aria-label="आगे"><i class="fa-solid fa-angle-right"></i></button></div>
</div>
