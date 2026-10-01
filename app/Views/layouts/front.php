<?php
/**
 * वेबसाइट का साझा लेआउट। हेडर/फ़ुटर मेनू बिल्डर और सेटिंग से बनते हैं।
 * पेज ये भेज सकता है: $seo = [title, description, keywords, image, robots, canonical], $layoutOptions = [header, footer]
 */
use App\Services\MenuService;

$seo ??= [];
$layoutOptions ??= ['header' => true, 'footer' => true];
$site = (string) setting('site_name');
$head = \App\Services\SeoService::head($seo); // शीर्षक, robots, canonical, OG (SEO सेटिंग के साथ)
$docTitle = $head['title'];
$desc = $head['description'];
$img = $head['image'];
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$brand2 = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('secondary_color')) ? setting('secondary_color') : '#15161a';
$fonts = array_unique([setting('font_heading', 'Mukta'), setting('font_body', 'Noto Sans Devanagari')]);
$fontUrl = 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800', $fonts)) . '&display=swap';
$pwa = \App\Services\PwaService::enabled();
$theme = in_array($_COOKIE['theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['theme'] : '';
?><!doctype html>
<html lang="<?= e(setting('language', 'hi')) ?>"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($docTitle) ?></title>
<meta name="description" content="<?= e(\App\Helpers\Str::limit((string) $desc, 170)) ?>">
<?php if (!empty($seo['keywords'])): ?><meta name="keywords" content="<?= e($seo['keywords']) ?>"><?php endif; ?>
<meta name="robots" content="<?= e($head['robots']) ?>">
<?php if ($head['canonical'] !== ''): ?><link rel="canonical" href="<?= e($head['canonical']) ?>"><?php endif; ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($site) ?>" href="<?= e(route('feed')) ?>">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:locale" content="<?= e(setting('language', 'hi') === 'en' ? 'en_IN' : 'hi_IN') ?>">
<meta property="og:type" content="<?= e($seo['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($head['og_title']) ?>">
<meta property="og:description" content="<?= e(\App\Helpers\Str::limit($head['og_description'], 200)) ?>">
<?php if ($head['canonical'] !== ''): ?><meta property="og:url" content="<?= e($head['canonical']) ?>"><?php endif; ?>
<?php if ($img): ?><meta property="og:image" content="<?= e($img) ?>"><?php endif; ?>
<?php if (setting('seo_fb_app_id')): ?><meta property="fb:app_id" content="<?= e(setting('seo_fb_app_id')) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if ($head['twitter']): ?><meta name="twitter:site" content="<?= e($head['twitter']) ?>"><?php endif; ?>
<meta name="twitter:title" content="<?= e($head['og_title']) ?>">
<meta name="twitter:description" content="<?= e(\App\Helpers\Str::limit($head['og_description'], 200)) ?>">
<?php if ($img): ?><meta name="twitter:image" content="<?= e($img) ?>"><?php endif; ?>
<?php if (!empty($seo['published'])): ?><meta property="article:published_time" content="<?= e(date('c', strtotime($seo['published']))) ?>">
<meta property="article:modified_time" content="<?= e(date('c', strtotime((string) $seo['modified']))) ?>"><?php if (!empty($seo['section'])): ?><meta property="article:section" content="<?= e($seo['section']) ?>"><?php endif; ?><?php endif; ?>
<?= $seo['jsonld'] ?? '' /* SeoService: JSON_HEX_TAG के साथ सुरक्षित */ ?>
<?php if (setting('search_console')): ?><meta name="google-site-verification" content="<?= e(setting('search_console')) ?>"><?php endif; ?>
<?php if (setting('seo_bing')): ?><meta name="msvalidate.01" content="<?= e(setting('seo_bing')) ?>"><?php endif; ?>
<?php if (setting('seo_yandex')): ?><meta name="yandex-verification" content="<?= e(setting('seo_yandex')) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($brand) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php if (setting('push_enabled', '0') === '1' && ($vk = \App\Services\Notify\WebPush::keys())): ?><meta name="push-key" content="<?= e($vk['public']) ?>"><meta name="push-sw" content="<?= e(route('push.sw')) ?>"><meta name="push-sub" content="<?= e(route('push.subscribe')) ?>"><meta name="push-unsub" content="<?= e(route('push.unsubscribe')) ?>"><?php endif; ?>
<?php if (app('router')->has('ad.impressions')): ?><meta name="ad-imp" content="<?= e(route('ad.impressions')) ?>"><?php endif; ?>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php else: ?><link rel="icon" type="image/png" href="<?= e(\App\Services\PwaService::iconUrl(192)) ?>"><?php endif; ?>
<?php if ($pwa): ?><link rel="manifest" href="<?= e(route('pwa.manifest')) ?>">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default"><meta name="apple-mobile-web-app-title" content="<?= e(\App\Services\PwaService::shortName()) ?>"><?php endif; ?>
<link rel="apple-touch-icon" href="<?= e(\App\Services\PwaService::iconUrl(180)) ?>">
<?php if ($pwa || setting('push_enabled', '0') === '1'): ?><meta name="sw" content="<?= e(route('push.sw')) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($fontUrl) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e(readable_color($brand)) ?>;--brand-2:<?= e($brand2) ?>;--f-head:"<?= e(setting('font_heading', 'Mukta')) ?>";--f-body:"<?= e(setting('font_body', 'Noto Sans Devanagari')) ?>"}</style>
<?php if (setting('gtm_id')): ?><script async src="https://www.googletagmanager.com/gtm.js?id=<?= e(setting('gtm_id')) ?>"></script><?php endif; ?>
<?php if (setting('ga_id')): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(setting('ga_id')) ?>"></script><?php endif; ?>
<?= setting('header_code') /* एडमिन (settings.manage) का भरोसेमंद कोड; ऑडिट में दर्ज */ ?>
</head>
<body<?= setting('sticky_header', '1') === '1' ? ' class="sticky-nav"' : '' ?> data-ga="<?= e(setting('ga_id')) ?>">
<a class="skip" href="#main">मुख्य सामग्री पर जाएँ</a>
<?php if (!empty($isPreview)): ?><div class="preview-bar"><i class="fa-solid fa-eye"></i> प्रीव्यू: यह पेज अभी सिर्फ़ स्टाफ़ को दिख रहा है।</div><?php endif; ?>
<?php if ($layoutOptions['header']): ?><?= $this->insert('partials/front/header', ['main' => MenuService::tree('main'), 'top' => MenuService::tree('top'), 'mobile' => MenuService::tree('mobile')]) ?>
<?= $this->insert('partials/front/ticker') ?>
<?= ($bh = ad_slot('below_header')) !== '' ? '<aside class="wrap ad-row" aria-label="विज्ञापन (ऊपर)">' . $bh . '</aside>' : '' ?><?php endif; ?>

<main id="main" class="site-main">
  <?= $this->insert('partials/front/flash') ?>
  <?= $this->section('content') ?>
</main>

<?php if ($layoutOptions['footer']): ?><?= ($fa = ad_slot('footer')) !== '' ? '<aside class="wrap ad-row" aria-label="विज्ञापन (नीचे से पहले)">' . $fa . '</aside>' : '' ?><?= $this->insert('partials/front/footer') ?><?php endif; ?>
<?= $this->insert('partials/front/breaking-alerts', ['where' => 'mobile']) ?>
<?= ad_slot('mobile_sticky') ?><?= ad_slot('popup') ?>
<?php if ($pwa && setting('pwa_install_prompt', '1') === '1'): ?>
<div class="pwa-install" data-pwa-install role="dialog" aria-labelledby="pwaInstallTitle" hidden>
  <img src="<?= e(\App\Services\PwaService::iconUrl(192)) ?>" alt="" width="44" height="44">
  <div class="pi-body"><b id="pwaInstallTitle"><?= e(\App\Services\PwaService::shortName()) ?> ऐप इंस्टॉल करें</b><small data-pi-text>होम स्क्रीन से एक टैप में ख़बरें, बिना नेट के भी पढ़ें।</small></div>
  <button type="button" class="btn pi-yes" data-pi-yes>इंस्टॉल</button>
  <button type="button" class="pi-no" data-pi-no aria-label="अभी नहीं"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
</div>
<?php endif; ?>
<?= $this->insert('partials/front/bottom-nav') ?>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= setting('footer_code') ?>
</body>
</html>
