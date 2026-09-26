<div class="box">
  <?= block_head($title ?: 'ट्रेंडिंग') ?>
  <?php if ($tags): ?><div class="chips"><?php foreach ($tags as $t): ?><a href="<?= e(route($t['kind'], ['slug' => $t['slug']])) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <?php if ($items): ?><div class="cgrid cols-3 mt"><?php foreach ($items as $n): ?><?= news_card($n, 'row') ?><?php endforeach; ?></div><?php endif; ?>
</div>
