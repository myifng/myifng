<?php
/** Phase 5 तक वेबसाइट का अस्थायी पेज (ब्रांडिंग सेटिंग से) */
$brand = (string) setting('primary_color', '#d71920');
$words = preg_split('/\s+/u', (string) setting('site_name', 'News'), 2);
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(setting('site_name')) ?> · <?= e(setting('tagline')) ?></title>
<meta name="description" content="<?= e(setting('site_description', setting('tagline'))) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style>:root{--brand:<?= e(preg_match('/^#[0-9a-f]{6}$/i', $brand) ? $brand : '#d71920') ?>}</style>
</head>
<body class="soon-page">
<main class="soon">
  <div class="brand-mark brand-mark-lg">
    <?php if (setting('logo')): ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e(setting('site_name')) ?>"><?php else: ?>
    <span class="bm-a"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="bm-b"><?= e($words[1]) ?></span><?php endif; ?><?php endif; ?>
  </div>
  <p class="soon-tag"><?= e(setting('tagline')) ?></p>
  <h1>हमारी नई न्यूज़ वेबसाइट जल्द आ रही है</h1>
  <p class="soon-text">देश, प्रदेश और आपके ज़िले की हर बड़ी ख़बर, सबसे पहले।</p>
  <?php if (setting('contact_email')): ?><p class="soon-contact">संपर्क: <?= e(setting('contact_email')) ?></p><?php endif; ?>
</main>
</body>
</html>
