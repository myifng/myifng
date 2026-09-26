<div class="box">
  <?= block_head($title ?: 'ताज़ा ख़बरें', $more) ?>
  <?php if (($set['layout'] ?? 'list') === 'cards'): ?>
    <div class="cgrid cols-4"><?php foreach ($items as $n): ?><?= news_card($n, 'card') ?><?php endforeach; ?></div>
  <?php else: ?>
    <div class="latest cols"><?php foreach ($items as $n): ?><?= news_card($n, 'list') ?><?php endforeach; ?></div>
  <?php endif; ?>
</div>
