<?php
$f = flash();
$icons = ['success' => 'fa-circle-check', 'danger' => 'fa-circle-exclamation', 'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
if ($f):
    $t = isset($icons[$f['type']]) ? $f['type'] : 'info'; ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
    <i class="fa-solid <?= e($icons[$t]) ?> mt-1"></i><div><?= e($f['message']) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="बंद करें"></button>
  </div>
<?php endif; ?>
<?php if ($errs = errors()): ?>
  <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
    <i class="fa-solid fa-circle-exclamation mt-1"></i>
    <div><b>कुछ जानकारी ठीक करनी है:</b><ul class="mb-0 ps-3"><?php foreach ($errs as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  </div>
<?php endif; ?>
