<?php
/**
 * लॉगिन, पासवर्ड भूलें/रीसेट, OTP का साझा लेआउट।
 * डेस्कटॉप: बाईं ओर ब्रांड पैनल (लोगो, टैगलाइन, सुविधाएँ); मोबाइल: ऊपर लोगो वाली पट्टी, नीचे कार्ड।
 */
$brand = (string) setting('primary_color', '#d71920');
$brand = preg_match('/^#[0-9a-f]{6}$/i', $brand) ? $brand : '#d71920';
$brand2 = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('secondary_color')) ? (string) setting('secondary_color') : '#15161a';
$site = (string) setting('site_name', 'News');
$words = preg_split('/\s+/u', $site, 2);
$isRep = ($portal ?? 'admin') === 'reporter';
$logo = (string) setting('logo');
$logoDark = (string) (setting('logo_dark') ?: '');
$mark = '<span class="bm-a">' . e($words[0]) . '</span>' . (!empty($words[1]) ? '<span class="bm-b">' . e($words[1]) . '</span>' : '');
$contact = (string) setting('contact_email');
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= e($brand2) ?>">
<title><?= e(($title ?? 'लॉगिन') . ' · ' . $site) ?></title>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style>:root{--brand:<?= e(readable_color($brand)) ?>;--brand-2:<?= e($brand2) ?>;--bs-link-color-rgb:<?= implode(',', array_map('hexdec', str_split(substr(readable_color($brand), 1), 2))) ?>}</style>
</head>
<body class="auth-page portal-<?= $isRep ? 'reporter' : 'admin' ?>">
<div class="auth-split">
  <aside class="auth-art">
    <a class="aa-logo" href="<?= e(url()) ?>" aria-label="<?= e($site) ?> वेबसाइट">
      <?php if ($logoDark): ?><img src="<?= e(upload_url($logoDark)) ?>" alt="<?= e($site) ?>">
      <?php elseif ($logo): ?><span class="aa-logo-chip"><img src="<?= e(upload_url($logo)) ?>" alt="<?= e($site) ?>"></span>
      <?php else: ?><span class="brand-mark brand-mark-lg"><?= $mark ?></span><?php endif; ?>
    </a>
    <div class="aa-mid">
      <span class="aa-kicker"><i></i><?= $isRep ? 'रिपोर्टर पोर्टल' : 'न्यूज़रूम कंट्रोल' ?></span>
      <h2 class="aa-title"><?= e((string) setting('tagline', 'सच के साथ, सबसे पहले')) ?></h2>
      <ul class="auth-points">
        <?php foreach ($isRep
            ? [['fa-pen-nib', 'अपने क्षेत्र की ख़बर लिखें और डेस्क को भेजें'], ['fa-list-check', 'असाइनमेंट और ख़बर की स्थिति देखें'], ['fa-id-card', 'ID कार्ड, अधिकार पत्र और अपना प्रदर्शन']]
            : [['fa-newspaper', 'ख़बर लिखें, समीक्षा करें, प्रकाशित करें'], ['fa-users', 'रिपोर्टर नेटवर्क और असाइनमेंट डेस्क'], ['fa-chart-line', 'ट्रैफ़िक, राजस्व और न्यूज़रूम का पूरा हिसाब']] as [$ic, $t]): ?>
          <li><i class="fa-solid <?= $ic ?>" aria-hidden="true"></i><span><?= e($t) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="aa-cards" aria-hidden="true">
      <div class="aa-card"><b class="aa-pill">ब्रेकिंग</b><span></span><span class="w70"></span></div>
      <div class="aa-card two"><b class="aa-pill alt">LIVE</b><span></span><span class="w50"></span></div>
    </div>
    <p class="aa-foot"><i class="fa-solid fa-lock" aria-hidden="true"></i> सुरक्षित लॉगिन · <?= e(preg_replace('~^https?://~', '', rtrim(url(), '/'))) ?></p>
  </aside>
  <main class="auth-main">
    <header class="auth-mhead">
      <a class="am-logo" href="<?= e(url()) ?>" aria-label="<?= e($site) ?> वेबसाइट">
        <?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt="<?= e($site) ?>"><?php else: ?><span class="brand-mark"><?= $mark ?></span><?php endif; ?>
      </a>
      <span class="am-tag"><?= $isRep ? 'रिपोर्टर पोर्टल' : 'न्यूज़रूम' ?></span>
    </header>
    <div class="auth-card">
      <?= $this->insert('partials/admin/flash') ?>
      <?= $this->section('content') ?>
    </div>
    <footer class="auth-foot">
      <span>© <?= date('Y') ?> <?= e($site) ?></span>
      <a href="<?= e(url()) ?>"><i class="fa-solid fa-globe" aria-hidden="true"></i> वेबसाइट</a>
      <?php if ($contact): ?><a href="mailto:<?= e($contact) ?>"><i class="fa-regular fa-envelope" aria-hidden="true"></i> सहायता</a><?php endif; ?>
    </footer>
  </main>
</div>
<script src="<?= asset('js/admin.js') ?>"></script>
</body>
</html>
