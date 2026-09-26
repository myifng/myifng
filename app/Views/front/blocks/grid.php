<div class="box">
  <?php if ($heading): ?><?= block_head($heading, $more) ?><?php endif; ?>
  <div class="cgrid cols-<?= (int) ($set['columns'] ?? 4) ?>"><?php foreach ($items as $n): ?><?= news_card($n, 'card') ?><?php endforeach; ?></div>
</div>
