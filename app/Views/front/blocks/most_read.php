<div class="box">
  <?= block_head($title ?: 'सबसे ज़्यादा पढ़ी गईं') ?>
  <ol class="ranked cols"><?php foreach ($items as $n): ?><li><?= news_card($n, 'link') ?></li><?php endforeach; ?></ol>
</div>
