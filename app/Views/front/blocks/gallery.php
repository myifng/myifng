<div class="box">
  <?= block_head($title ?: 'फ़ोटो गैलरी', $more) ?>
  <div class="cgrid cols-4"><?php foreach ($items as $g): ?><?= mm_card($g) ?><?php endforeach; ?></div>
</div>
