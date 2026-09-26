<div class="box livebox">
  <?= block_head($title ?: 'लाइव टीवी') ?>
  <div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>?<?= !empty($set['autoplay']) ? 'autoplay=1&mute=1' : '' ?>" title="<?= e($title ?: 'लाइव टीवी') ?>" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>
</div>
