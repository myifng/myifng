<?php
/**
 * Google Web Stories के लिए AMP संस्करण (amp-story)। AMP के नियम से स्क्रिप्ट cdn.ampproject.org से आती है;
 * कैनोनिकल हमारा अपना प्लेयर पेज है। कोई यूज़र HTML नहीं: सब कुछ escape।
 */
$site = (string) setting('site_name');
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$hasVideo = (bool) array_filter($slides, static fn($x) => $x['media'] && $x['media_type'] === 'video');
$publisherLogo = $logo ?: $poster;
?><!doctype html>
<html ⚡ lang="<?= e(setting('language', 'hi')) ?>">
<head>
<meta charset="utf-8">
<title><?= e($s['meta_title'] ?: $s['title']) ?></title>
<link rel="canonical" href="<?= e($url) ?>">
<meta name="viewport" content="width=device-width">
<meta name="description" content="<?= e(\App\Helpers\Str::limit((string) ($s['meta_description'] ?: ($s['description'] ?: $s['title'])), 170)) ?>">
<script async src="https://cdn.ampproject.org/v0.js"></script>
<script async custom-element="amp-story" src="https://cdn.ampproject.org/v0/amp-story-1.0.js"></script>
<?php if ($hasVideo): ?><script async custom-element="amp-video" src="https://cdn.ampproject.org/v0/amp-video-0.1.js"></script><?php endif; ?>
<style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
<style amp-custom>
amp-story { font-family: "Mukta", "Noto Sans Devanagari", sans-serif; }
amp-story-page { background: #111; }
amp-story-page.no-media.theme-light { background: #f6f3ee; }
amp-story-page.no-media.theme-brand { background: <?= e($brand) ?>; }
.txt { padding: 32px 22px; color: #fff; }
.pos-bottom { align-content: end; } .pos-bottom .txt { background: linear-gradient(transparent, rgba(0,0,0,.85)); padding-top: 90px; }
.pos-top { align-content: start; } .pos-top .txt { background: linear-gradient(rgba(0,0,0,.85), transparent); padding-top: 50px; padding-bottom: 90px; }
.pos-center { align-content: center; } .pos-center .txt { background: rgba(0,0,0,.5); }
.theme-light .txt { color: #111; background: linear-gradient(transparent, rgba(255,255,255,.94)); }
.theme-brand .txt { background: linear-gradient(transparent, <?= e($brand) ?>); }
.theme-light .pos-top .txt { background: linear-gradient(rgba(255,255,255,.94), transparent); }
.theme-brand .pos-top .txt { background: linear-gradient(<?= e($brand) ?>, transparent); }
h1, h2 { font-size: 28px; line-height: 1.25; margin: 0 0 8px; font-weight: 800; }
p { font-size: 17px; line-height: 1.5; margin: 0; }
</style>
<?= $jsonld ?>
</head>
<body>
<amp-story standalone title="<?= e($s['title']) ?>" publisher="<?= e($site) ?>" publisher-logo-src="<?= e($publisherLogo) ?>" poster-portrait-src="<?= e($poster) ?>">
<?php foreach ($slides as $i => $sl): ?>
  <amp-story-page id="page-<?= $i + 1 ?>" auto-advance-after="<?= (int) $sl['duration'] ?>s" class="theme-<?= e($sl['theme']) ?><?= $sl['media'] ? '' : ' no-media' ?>">
    <?php if ($sl['media']): ?>
    <amp-story-grid-layer template="fill">
      <?php if ($sl['media_type'] === 'video'): ?>
        <amp-video autoplay loop width="720" height="1280" layout="responsive" poster="<?= e($poster) ?>"><source src="<?= e(upload_url($sl['media'])) ?>" type="video/mp4"></amp-video>
      <?php else: ?>
        <amp-img src="<?= e(media_url($sl['media'], 'large')) ?>" width="720" height="1280" layout="responsive" alt="<?= e((string) ($sl['heading'] ?: $s['title'])) ?>"></amp-img>
      <?php endif; ?>
    </amp-story-grid-layer>
    <?php endif; ?>
    <?php if ($sl['heading'] || $sl['body']): ?>
    <amp-story-grid-layer template="vertical" class="pos-<?= e($sl['text_position']) ?>">
      <div class="txt"><?php if ($sl['heading']): ?><?= $i === 0 ? '<h1>' . e($sl['heading']) . '</h1>' : '<h2>' . e($sl['heading']) . '</h2>' ?><?php endif; ?><?php if ($sl['body']): ?><p><?= e($sl['body']) ?></p><?php endif; ?></div>
    </amp-story-grid-layer>
    <?php endif; ?>
    <?php if ($sl['href']): ?><amp-story-page-outlink layout="nodisplay"><a href="<?= e($sl['href']) ?>"><?= e($sl['cta_label'] ?: 'और पढ़ें') ?></a></amp-story-page-outlink><?php endif; ?>
  </amp-story-page>
<?php endforeach; ?>
</amp-story>
</body>
</html>
