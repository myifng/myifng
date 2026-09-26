<div class="box">
  <?= block_head($title ?: 'ऑडियो / पॉडकास्ट', $more) ?>
  <div class="cgrid cols-4"><?php foreach ($items as $a): ?><?= mm_card($a) ?><?php endforeach; ?></div>
</div>
