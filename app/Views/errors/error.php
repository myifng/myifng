<?php
/** त्रुटि पेज (403, 404, 419, 429, 500, 503)। डेटाबेस बंद हो तब भी चले, इसलिए सेटिंग सावधानी से पढ़ते हैं। */
$site = 'News';
$home = '/';
$css = '';
try {
    $site = (string) setting('site_name', 'News');
    $home = url();
    $css = asset('css/admin.css');
} catch (\Throwable) {
}
$icons = [403 => 'fa-lock', 404 => 'fa-compass', 419 => 'fa-hourglass-end', 429 => 'fa-gauge-high', 500 => 'fa-plug-circle-exclamation', 503 => 'fa-screwdriver-wrench'];
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= (int) $status ?> · <?= htmlspecialchars($title) ?> · <?= htmlspecialchars($site) ?></title>
<?php if ($css): ?><link rel="stylesheet" href="<?= htmlspecialchars($css) ?>"><?php endif; ?>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f4f4;color:#1c1b1d;font-family:"Mukta","Noto Sans Devanagari",system-ui,sans-serif;padding:24px 16px}
@media (prefers-color-scheme:dark){body{background:#111013;color:#ecebee}.err-card{background:#1b1a1e!important;border-color:#2e2c33!important}}
.err-card{max-width:520px;width:100%;background:#fff;border:1px solid #e6e3e4;border-radius:14px;padding:36px 28px;text-align:center}
.err-code{font-size:72px;font-weight:800;line-height:1;color:#d71920;letter-spacing:-2px}
.err-card h1{font-size:24px;margin:10px 0 6px}.err-card p{color:#6b6770;margin:0 0 22px;line-height:1.6}
.err-card a{display:inline-block;background:#d71920;color:#fff;text-decoration:none;padding:9px 18px;border-radius:8px;font-weight:700;margin:4px}
.err-card a.alt{background:transparent;color:inherit;border:1px solid #d9d5d7}
pre.trace{margin-top:20px;text-align:left;font-size:12px;background:#1c1b1d;color:#f5f5f5;padding:14px;border-radius:8px;overflow:auto;max-height:340px;white-space:pre-wrap}
</style>
</head>
<body>
<main class="err-card">
  <div class="err-code"><?= (int) $status ?></div>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($message) ?></p>
  <a href="<?= htmlspecialchars($home) ?>">होम पेज</a>
  <?php if ((int) $status === 419): ?><a class="alt" href="javascript:history.back()">वापस जाएँ</a><?php endif; ?>
  <?php if (!empty($exception)): ?>
    <pre class="trace"><?= htmlspecialchars(get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) ?></pre>
  <?php endif; ?>
</main>
</body>
</html>
