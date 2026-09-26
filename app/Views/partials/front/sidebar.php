<aside class="side">
  <?php if ($side['latest']): ?>
  <section class="box">
    <?= block_head('ताज़ा ख़बरें', route('latest')) ?>
    <div class="latest"><?php foreach ($side['latest'] as $n): ?><?= news_card($n, 'list') ?><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
  <?php if ($side['popular']): ?>
  <section class="box">
    <?= block_head('सबसे ज़्यादा पढ़ी') ?>
    <ol class="ranked"><?php foreach ($side['popular'] as $n): ?><li><?= news_card($n, 'link') ?></li><?php endforeach; ?></ol>
  </section>
  <?php endif; ?>
</aside>
