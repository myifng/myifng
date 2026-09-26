<?php
/** वेबसाइट पर सूचना (फ़ॉर्म जमा, त्रुटि) */
$f = flash();
$errs = errors();
if ($f): $t = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info'; ?>
  <div class="wrap"><div class="notice notice-<?= e($t) ?>" role="<?= $t === 'danger' ? 'alert' : 'status' ?>"><?= e($f['message']) ?></div></div>
<?php endif; ?>
<?php if ($errs): ?>
  <div class="wrap"><div class="notice notice-danger" role="alert"><b>कृपया ये सुधारें:</b><ul><?php foreach ($errs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>
