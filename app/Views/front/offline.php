<?php
/** ऑफ़लाइन पेज: सर्विस वर्कर पहले से कैश करता है; इसलिए इसमें मेनू, विज्ञापन, टिकर जैसी बदलती चीज़ें नहीं */
$site = (string) setting('site_name');
$brand = \App\Services\PwaService::brand();
$words = preg_split('/\s+/u', $site, 2);
$theme = in_array($_COOKIE['theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['theme'] : '';
?><!doctype html>
<html lang="<?= e(setting('language', 'hi')) ?>"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>आप ऑफ़लाइन हैं · <?= e($site) ?></title>
<meta name="theme-color" content="<?= e($brand) ?>">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e(readable_color($brand)) ?>}</style>
</head>
<body class="offline-page">
<header class="off-head"><a class="logo" href="<?= e(url()) ?>"><?php if (setting('logo')): ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e($site) ?>"><?php else: ?><span class="la"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="lb"><?= e($words[1]) ?></span><?php endif; ?><?php endif; ?></a></header>
<main id="main" class="wrap off-main">
  <section class="box off-card" aria-labelledby="offTitle">
    <i class="fa-solid fa-wifi off-ico" aria-hidden="true"></i>
    <h1 id="offTitle">आप अभी ऑफ़लाइन हैं</h1>
    <p>इंटरनेट से जुड़ते ही ताज़ा ख़बरें अपने-आप दिखेंगी। तब तक नीचे हाल में पढ़ी ख़बरें बिना नेट के पढ़ सकते हैं।</p>
    <button type="button" class="btn" data-retry><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> दोबारा कोशिश करें</button>
  </section>
  <section class="box off-saved" data-offline-list hidden aria-labelledby="offSaved">
    <h2 id="offSaved" class="off-h2"><i class="fa-solid fa-download" aria-hidden="true"></i> ऑफ़लाइन पढ़ें</h2>
    <ul class="off-list" data-offline-items></ul>
  </section>
</main>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
