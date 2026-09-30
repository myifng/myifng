<?php use App\Services\MultimediaService; ?>
<?php if (!empty($local['media'])): ?>
  <section class="box"><?= block_head('यहाँ के वीडियो और फ़ोटो') ?>
    <div class="cgrid cols-3"><?php foreach ($local['media'] as $m): ?>
      <a class="loc-media" href="<?= e(MultimediaService::url($m['kind'], $m)) ?>"><span class="lm-img"><?= $m['image'] ? media_img($m['image'], 'medium', $m['title']) : '' ?><i class="fa-solid <?= $m['kind'] === 'video' ? 'fa-play' : 'fa-images' ?>" aria-hidden="true"></i></span><b><?= e($m['title']) ?></b></a>
    <?php endforeach; ?></div>
  </section>
<?php endif; ?>
<?php if (!empty($local['nearby'])): ?>
  <section class="box"><?= block_head('आसपास के इलाक़े') ?><nav class="chips"><?php foreach ($local['nearby'] as $l): ?><a href="<?= e(url($l['path'])) ?>"><?= $l['parent'] ? '<i class="fa-solid fa-arrow-up"></i> ' : '' ?><?= e($l['name']) ?></a><?php endforeach; ?></nav></section>
<?php endif; ?>
