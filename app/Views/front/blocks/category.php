<?php $layout = $set['layout'] ?? 'lead-list'; $c = preg_match('/^#[0-9a-f]{6}$/i', (string) $cat['color']) ? $cat['color'] : null; ?>
<div class="box catbox"<?= $c ? ' style="--cat:' . e($c) . '"' : '' ?>>
  <?= block_head($heading, $more) ?>
  <?php if ($subs && $layout !== 'half'): ?><nav class="subnav" aria-label="<?= e($cat['name']) ?> की उप-श्रेणियाँ"><?php foreach ($subs as $s): ?><a href="<?= e(route('category', ['slug' => $s['slug']])) ?>"><?= e($s['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
  <?php if ($layout === 'grid'): ?>
    <div class="cgrid cols-4"><?php foreach ($items as $n): ?><?= news_card($n, 'card', ['kicker' => false]) ?><?php endforeach; ?></div>
  <?php else: ?>
    <div class="<?= $layout === 'half' ? 'lead-stack' : 'lead-list' ?>">
      <div class="lead"><?= news_card($items[0], 'card', ['kicker' => false, 'summary' => true, 'size' => 'large']) ?></div>
      <div class="rows"><?php foreach (array_slice($items, 1) as $n): ?><?= news_card($n, 'row') ?><?php endforeach; ?></div>
    </div>
  <?php endif; ?>
</div>
