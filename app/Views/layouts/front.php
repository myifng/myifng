<?php
/**
 * वेबसाइट का साझा लेआउट। हेडर/फ़ुटर मेनू बिल्डर और सेटिंग से बनते हैं।
 * पेज ये भेज सकता है: $seo = [title, description, keywords, image, robots, canonical], $layoutOptions = [header, footer]
 */
use App\Services\MenuService;

$seo ??= [];
$layoutOptions ??= ['header' => true, 'footer' => true];
$site = (string) setting('site_name');
$docTitle = !empty($seo['title']) ? $seo['title'] . ' | ' . $site : $site . (setting('tagline') ? ' | ' . setting('tagline') : '');
$desc = $seo['description'] ?? setting('site_description');
$img = !empty($seo['image']) ? (preg_match('~^https?://~', $seo['image']) ? $seo['image'] : media_url($seo['image'], 'large')) : (setting('logo') ? upload_url(setting('logo')) : '');
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$brand2 = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('secondary_color')) ? setting('secondary_color') : '#15161a';
$fonts = array_unique([setting('font_heading', 'Mukta'), setting('font_body', 'Noto Sans Devanagari')]);
$fontUrl = 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800', $fonts)) . '&display=swap';
$theme = in_array($_COOKIE['theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['theme'] : '';
?><!doctype html>
<html lang="<?= e(setting('language', 'hi')) ?>"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($docTitle) ?></title>
<meta name="description" content="<?= e(\App\Helpers\Str::limit((string) $desc, 170)) ?>">
<?php if (!empty($seo['keywords'])): ?><meta name="keywords" content="<?= e($seo['keywords']) ?>"><?php endif; ?>
<meta name="robots" content="<?= e($seo['robots'] ?? 'index,follow') ?>">
<?php if (!empty($seo['canonical'])): ?><link rel="canonical" href="<?= e($seo['canonical']) ?>"><?php endif; ?>
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:type" content="<?= e($seo['og_type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($seo['title'] ?? $site) ?>">
<meta property="og:description" content="<?= e(\App\Helpers\Str::limit((string) $desc, 200)) ?>">
<?php if (!empty($seo['canonical'])): ?><meta property="og:url" content="<?= e($seo['canonical']) ?>"><?php endif; ?>
<?php if ($img): ?><meta property="og:image" content="<?= e($img) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if (!empty($seo['published'])): ?><meta property="article:published_time" content="<?= e(date('c', strtotime($seo['published']))) ?>">
<meta property="article:modified_time" content="<?= e(date('c', strtotime((string) $seo['modified']))) ?>"><?php if (!empty($seo['section'])): ?><meta property="article:section" content="<?= e($seo['section']) ?>"><?php endif; ?><?php endif; ?>
<?= $seo['jsonld'] ?? '' /* SeoService: JSON_HEX_TAG के साथ सुरक्षित */ ?>
<?php if (setting('search_console')): ?><meta name="google-site-verification" content="<?= e(setting('search_console')) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= e($brand) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= e($fontUrl) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e($brand) ?>;--brand-2:<?= e($brand2) ?>;--f-head:"<?= e(setting('font_heading', 'Mukta')) ?>";--f-body:"<?= e(setting('font_body', 'Noto Sans Devanagari')) ?>"}</style>
<?php if (setting('gtm_id')): ?><script async src="https://www.googletagmanager.com/gtm.js?id=<?= e(setting('gtm_id')) ?>"></script><?php endif; ?>
<?php if (setting('ga_id')): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e(setting('ga_id')) ?>"></script><?php endif; ?>
<?= setting('header_code') /* एडमिन (settings.manage) का भरोसेमंद कोड; ऑडिट में दर्ज */ ?>
</head>
<body<?= setting('sticky_header', '1') === '1' ? ' class="sticky-nav"' : '' ?> data-ga="<?= e(setting('ga_id')) ?>">
<a class="skip" href="#main">मुख्य सामग्री पर जाएँ</a>
<?php if (!empty($isPreview)): ?><div class="preview-bar"><i class="fa-solid fa-eye"></i> प्रीव्यू: यह पेज अभी सिर्फ़ स्टाफ़ को दिख रहा है।</div><?php endif; ?>
<?php if ($layoutOptions['header']): ?><?= $this->insert('partials/front/header', ['main' => MenuService::tree('main'), 'top' => MenuService::tree('top'), 'mobile' => MenuService::tree('mobile')]) ?>
<?= $this->insert('partials/front/ticker') ?><?php endif; ?>

<main id="main" class="site-main">
  <?= $this->insert('partials/front/flash') ?>
  <?= $this->section('content') ?>
</main>

<?php if ($layoutOptions['footer']): ?><?= $this->insert('partials/front/footer') ?><?php endif; ?>
<?= $this->insert('partials/front/breaking-alerts', ['where' => 'mobile']) ?>
<?= $this->insert('partials/front/bottom-nav') ?>
<script src="<?= asset('js/app.js') ?>" defer></script>
<?= setting('footer_code') ?>
</body>
</html>
