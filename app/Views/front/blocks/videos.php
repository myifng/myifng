<?php $layout = $set['layout'] ?? 'dark-strip'; ?>
<?php if ($layout === 'shorts'): ?>
<div class="box">
  <?= block_head($title ?: 'शॉर्ट्स', $more) ?>
  <div class="hscroll" data-hscroll><div class="hs-track tall-track"><?php foreach ($items as $v): ?><div class="hs-item tall"><?= mm_card($v, 'tall') ?></div><?php endforeach; ?></div>
    <button type="button" class="sl-btn prev" aria-label="पीछे"><i class="fa-solid fa-angle-left"></i></button><button type="button" class="sl-btn next" aria-label="आगे"><i class="fa-solid fa-angle-right"></i></button></div>
</div>
<?php elseif ($layout === 'grid'): ?>
<div class="box">
  <?= block_head($title ?: 'वीडियो', $more) ?>
  <div class="cgrid cols-4"><?php foreach ($items as $v): ?><?= mm_card($v) ?><?php endforeach; ?></div>
</div>
<?php else: $lead = array_shift($items); ?>
<div class="box video-strip">
  <?= block_head($title ?: 'वीडियो', $more) ?>
  <div class="vs-grid">
    <a class="vs-lead" href="<?= e(\App\Services\MultimediaService::url('video', $lead)) ?>">
      <span class="th"><?php $src = mm_thumb_src($lead, 'large'); ?><?= $src ? '<img src="' . e($src) . '" alt="' . e($lead['title']) . '" loading="lazy">' : '' ?><span class="vs-play" aria-hidden="true"><i class="fa-solid fa-play"></i></span></span>
      <span class="hd"><?= e($lead['title']) ?></span>
    </a>
    <?php if ($items): ?><div class="vs-list"><?php foreach ($items as $v): ?><?= mm_card($v, 'row') ?><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>
