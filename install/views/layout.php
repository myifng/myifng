<?php /** इंस्टॉलर का लेआउट */ ?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>इंस्टॉल विज़ार्ड</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../public/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../public/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../public/assets/css/admin.css">
</head>
<body class="install">
<div class="install-wrap">
  <div class="install-top">
    <div class="brand-mark"><span class="bm-a">न्यूज़</span><span class="bm-b">रूम</span></div>
    <span class="text-body-secondary small"><i class="fa-solid fa-screwdriver-wrench me-1"></i>इंस्टॉल विज़ार्ड · v1.0</span>
  </div>

  <?php if ($view !== 'installed'): ?>
  <ol class="steps" aria-label="इंस्टॉल के कदम">
    <?php foreach (STEPS as $n => $label):
        $cls = ($view === 'step7' || $n < $step) ? 'done' : ($n === $step ? 'now' : ''); ?>
      <li class="<?= $cls ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>><span class="n"><?= $cls === 'done' ? '<i class="fa-solid fa-check"></i>' : $n ?></span><?= h($label) ?></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger d-flex gap-2" role="alert"><i class="fa-solid fa-circle-exclamation mt-1"></i><div><?php foreach ($errors as $er): ?><div><?= h($er) ?></div><?php endforeach; ?></div></div>
  <?php endif; ?>

  <?php require __DIR__ . '/' . $view . '.php'; ?>
  <p class="text-center text-body-secondary small mt-4">PHP <?= h(PHP_VERSION) ?> · <?= h(php_uname('s')) ?></p>
</div>
</body>
</html>
