<?php $brand = (string) setting('primary_color', '#d71920'); $words = preg_split('/\s+/u', (string) setting('site_name', 'News'), 2); ?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($title ?? 'लॉगिन') . ' · ' . setting('site_name', 'News')) ?></title>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style>:root{--brand:<?= e(preg_match('/^#[0-9a-f]{6}$/i', $brand) ? $brand : '#d71920') ?>}</style>
</head>
<body class="auth-page">
<div class="auth-split">
  <section class="auth-art" aria-hidden="true">
    <div class="auth-art-inner">
      <div class="brand-mark brand-mark-lg">
        <?php if (setting('logo_dark') ?: setting('logo')): ?><img src="<?= e(upload_url(setting('logo_dark') ?: setting('logo'))) ?>" alt=""><?php else: ?>
        <span class="bm-a"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="bm-b"><?= e($words[1]) ?></span><?php endif; ?><?php endif; ?>
      </div>
      <p class="auth-tag"><?= e(setting('tagline', 'डिजिटल न्यूज़रूम')) ?></p>
      <?php if (($portal ?? 'admin') === 'reporter'): ?>
      <ul class="auth-points">
        <li><i class="fa-solid fa-pen-nib"></i> अपने क्षेत्र की ख़बर लिखें और डेस्क को भेजें</li>
        <li><i class="fa-solid fa-list-check"></i> असाइनमेंट और ख़बर की स्थिति देखें</li>
        <li><i class="fa-solid fa-id-card"></i> ID कार्ड, अधिकार पत्र और अपना प्रदर्शन</li>
      </ul>
      <?php else: ?>
      <ul class="auth-points">
        <li><i class="fa-solid fa-newspaper"></i> ख़बर लिखें, समीक्षा करें, प्रकाशित करें</li>
        <li><i class="fa-solid fa-id-card"></i> रिपोर्टर नेटवर्क और असाइनमेंट</li>
        <li><i class="fa-solid fa-chart-line"></i> न्यूज़रूम का पूरा हिसाब एक जगह</li>
      </ul>
      <?php endif; ?>
      <div class="ticker-lines"><span></span><span></span><span></span></div>
    </div>
  </section>
  <main class="auth-main">
    <div class="auth-card">
      <?= $this->insert('partials/admin/flash') ?>
      <?= $this->section('content') ?>
    </div>
    <p class="auth-foot">© <?= date('Y') ?> <?= e(setting('site_name')) ?> · सुरक्षित क्षेत्र</p>
  </main>
</div>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
