<?php
/**
 * वेब स्टोरी का फ़ुलस्क्रीन प्लेयर (अपना पेज, हेडर/फ़ुटर नहीं)। बिना JS के सारी स्लाइड नीचे-नीचे पढ़ी जा सकती हैं।
 * Google के लिए AMP संस्करण: rel="amphtml"
 */
$site = (string) setting('site_name');
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$desc = \App\Helpers\Str::limit((string) ($s['meta_description'] ?: ($s['description'] ?: $s['title'])), 170);
$fontUrl = 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', (string) setting('font_heading', 'Mukta')) . ':wght@500;700;800&display=swap';
$total = count($slides);
?><!doctype html>
<html lang="<?= e(setting('language', 'hi')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($s['meta_title'] ?: $s['title']) . ' | ' . $site) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($url) ?>">
<link rel="amphtml" href="<?= e(route('story.amp', ['slug' => $s['slug']])) ?>">
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:title" content="<?= e($s['title']) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<?php if ($poster): ?><meta property="og:image" content="<?= e($poster) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#000000">
<?= $jsonld ?>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($fontUrl) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e($brand) ?>;--f-head:"<?= e(setting('font_heading', 'Mukta')) ?>"}</style>
</head>
<body class="story-body">
<main class="wstory" data-story data-exit="<?= e(route('stories')) ?>" aria-roledescription="वेब स्टोरी" aria-label="<?= e($s['title']) ?>">
  <div class="wst-bars" aria-hidden="true"><?php foreach ($slides as $i => $sl): ?><span><i></i></span><?php endforeach; ?></div>
  <div class="wst-top">
    <a class="wst-brand" href="<?= e(url()) ?>"><?= setting('logo') ? '<img src="' . e(upload_url((string) setting('logo'))) . '" alt="' . e($site) . '">' : e($site) ?></a>
    <span class="wst-actions">
      <button type="button" data-story-pause aria-label="रोकें / चलाएँ"><i class="fa-solid fa-pause"></i></button>
      <button type="button" data-story-mute aria-label="आवाज़" hidden><i class="fa-solid fa-volume-xmark"></i></button>
      <button type="button" data-story-share data-title="<?= e($s['title']) ?>" data-url="<?= e($url) ?>" aria-label="शेयर करें"><i class="fa-solid fa-share-nodes"></i></button>
      <a href="<?= e(route('stories')) ?>" aria-label="बंद करें"><i class="fa-solid fa-xmark"></i></a>
    </span>
  </div>
  <?php foreach ($slides as $i => $sl): ?>
    <section class="wst-slide pos-<?= e($sl['text_position']) ?> theme-<?= e($sl['theme']) ?><?= $sl['media'] ? '' : ' no-media' ?>" data-duration="<?= (int) $sl['duration'] ?>" aria-label="स्लाइड <?= $i + 1 ?> / <?= $total ?>">
      <?php if ($sl['media']): ?>
        <div class="wst-media">
          <?php if ($sl['media_type'] === 'video'): ?><video src="<?= e(upload_url($sl['media'])) ?>" muted playsinline loop preload="<?= $i ? 'none' : 'auto' ?>"></video>
          <?php else: ?><?= media_img($sl['media'], 'large', (string) ($sl['heading'] ?: $s['title']), ['loading' => $i < 2 ? 'eager' : 'lazy']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($sl['heading'] || $sl['body'] || $sl['href']): ?>
        <div class="wst-text">
          <?php if ($sl['heading']): ?><?= $i === 0 ? '<h1>' . e($sl['heading']) . '</h1>' : '<h2>' . e($sl['heading']) . '</h2>' ?><?php endif; ?>
          <?php if ($sl['body']): ?><p><?= e($sl['body']) ?></p><?php endif; ?>
          <?php if ($sl['href']): ?><a class="wst-cta" href="<?= e($sl['href']) ?>"<?= \App\Services\EmbedService::isExternal($sl['href']) ? ' target="_blank" rel="noopener nofollow"' : '' ?>><?= e($sl['cta_label'] ?: 'और पढ़ें') ?> <i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <section class="wst-slide wst-end" aria-label="और वेब स्टोरी">
    <div class="wst-text">
      <h2><?= e($s['title']) ?></h2>
      <p><button type="button" class="wst-replay" data-story-replay><i class="fa-solid fa-rotate-left"></i> फिर से देखें</button></p>
      <?php if ($next): ?><div class="wst-next"><?php foreach ($next as $n): ?><?= mm_card($n, 'tall', ['h' => 'h3']) ?><?php endforeach; ?></div><?php endif; ?>
      <a class="wst-cta" href="<?= e(route('stories')) ?>">सभी वेब स्टोरी</a>
    </div>
  </section>
  <button type="button" class="wst-tap prev" data-story-prev aria-label="पिछली स्लाइड"></button>
  <button type="button" class="wst-tap next" data-story-next aria-label="अगली स्लाइड"></button>
</main>
<script src="<?= asset('js/app.js') ?>" defer></script>
</body>
</html>
