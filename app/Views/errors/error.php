<?php
/** त्रुटि पेज (403, 404, 419, 429, 500, 503)। डेटाबेस बंद हो तब भी चले, इसलिए सेटिंग सावधानी से पढ़ते हैं। */
$site = 'News';
$home = '/';
$css = '';
$brand = '#d71920';
$logo = '';
$search = '';
try {
    $site = (string) setting('site_name', 'News');
    $home = url();
    $css = asset('css/admin.css');
    $c = (string) setting('primary_color', '#d71920');
    $brand = preg_match('/^#[0-9a-f]{6}$/i', $c) ? readable_color($c) : $brand;
    $logo = setting('logo') ? upload_url((string) setting('logo')) : '';
    $search = (int) $status === 404 && app('router')->has('search') ? route('search') : '';
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
:root{--brand:<?= htmlspecialchars($brand) ?>}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f4f4;color:#1c1b1d;font-family:"Mukta","Noto Sans Devanagari",system-ui,sans-serif;padding:24px 16px}
@media (prefers-color-scheme:dark){body{background:#111013;color:#ecebee}.err-card{background:#1b1a1e!important;border-color:#2e2c33!important}}
.err-card{box-sizing:border-box;max-width:520px;width:100%;background:#fff;border:1px solid #e6e3e4;border-radius:14px;padding:36px 28px;text-align:center}
.err-code{font-size:72px;font-weight:800;line-height:1;color:var(--brand);letter-spacing:-2px}
.err-card h1{font-size:24px;margin:10px 0 6px}.err-card p{color:#6b6770;margin:0 0 22px;line-height:1.6}
.err-card a{display:inline-block;background:var(--brand);color:#fff;text-decoration:none;padding:9px 18px;border-radius:8px;font-weight:700;margin:4px}
.err-card a.alt{background:transparent;color:inherit;border:1px solid #d9d5d7}
.err-logo{display:block;margin:0 auto 18px;max-height:46px;max-width:220px}
.err-search{display:flex;gap:8px;margin:0 0 18px}.err-search input{flex:1;min-width:0;font:inherit;font-size:16px;padding:9px 12px;border:1px solid #d9d5d7;border-radius:8px;background:transparent;color:inherit}
.err-search button{font:inherit;font-weight:700;background:var(--brand);color:#fff;border:0;border-radius:8px;padding:0 16px;cursor:pointer}
pre.trace{margin-top:20px;text-align:left;font-size:12px;background:#1c1b1d;color:#f5f5f5;padding:14px;border-radius:8px;overflow:auto;max-height:340px;white-space:pre-wrap}
</style>
</head>
<body>
<main class="err-card">
  <?php if ($logo): ?><a href="<?= htmlspecialchars($home) ?>" style="background:none;padding:0;margin:0"><img class="err-logo" src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($site) ?>"></a><?php endif; ?>
  <div class="err-code" aria-hidden="true"><?= (int) $status ?></div>
  <h1><?= htmlspecialchars($title) ?></h1>
  <p><?= htmlspecialchars($message) ?></p>
  <?php if ($search): ?><form class="err-search" action="<?= htmlspecialchars($search) ?>" method="get" role="search"><input type="search" name="q" placeholder="ख़बर खोजें…" aria-label="ख़बर खोजें" maxlength="100" required><button type="submit">खोजें</button></form><?php endif; ?>
  <a href="<?= htmlspecialchars($home) ?>">होम पेज</a>
  <?php if ((int) $status === 419): ?><a class="alt" href="javascript:history.back()">वापस जाएँ</a><?php endif; ?>
  <?php if (!empty($exception)): ?>
    <pre class="trace"><?= htmlspecialchars(get_class($exception) . ': ' . $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString()) ?></pre>
  <?php endif; ?>
</main>
</body>
</html>
