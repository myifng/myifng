<?php
/** एडमिन पैनल का साझा लेआउट: सभी एडमिन पेज इसी में खुलते हैं */
$brand = (string) setting('primary_color', '#d71920');
$theme = in_array($_COOKIE['admin_theme'] ?? '', ['light', 'dark'], true) ? $_COOKIE['admin_theme'] : 'light';
?><!doctype html>
<html lang="hi" data-bs-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e(($title ?? 'एडमिन') . ' · ' . setting('site_name', 'News')) ?></title>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<style>:root{--brand:<?= e(preg_match('/^#[0-9a-f]{6}$/i', $brand) ? $brand : '#d71920') ?>}</style>
</head>
<body class="admin">
<a class="visually-hidden-focusable skip-link" href="#main">मुख्य सामग्री पर जाएँ</a>
<div class="shell">
  <?= $this->insert('partials/admin/sidebar') ?>
  <div class="shell-main">
    <?= $this->insert('partials/admin/topbar') ?>
    <main class="content" id="main" tabindex="-1">
      <?= $this->insert('partials/admin/flash') ?>
      <?= $this->section('content') ?>
    </main>
    <footer class="admin-foot">
      <span>© <?= date('Y') ?> <?= e(setting('site_name')) ?></span>
      <span>v<?= e(config('app.version')) ?> · Phase <?= (int) config('app.phase') ?></span>
    </footer>
  </div>
</div>
<div class="sidebar-backdrop" data-sidebar-close></div>

<!-- पुष्टि (हटाने आदि से पहले) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="confirm-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h2 class="h5 mt-3" id="confirmTitle">पक्का करें</h2>
        <p class="text-body-secondary mb-0" data-confirm-text></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">रद्द करें</button>
        <button type="button" class="btn btn-danger" data-confirm-yes>हाँ, आगे बढ़ें</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?= $this->section('vendor_scripts') ?>
<script src="<?= asset('js/admin.js') ?>"></script>
<?= $this->section('scripts') ?>
</body>
</html>
