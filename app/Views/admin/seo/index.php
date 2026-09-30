<?php
$this->layout('layouts/admin');
$title = 'SEO कमांड सेंटर';
$tone = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
$siteOk = count(array_filter($site, static fn($s) => $s[1]));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">SEO</li></ol></nav>
    <h1>SEO कमांड सेंटर</h1>
    <p>Google Search, Google News और Discover के लिए साइट की सेहत एक जगह।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(route('sitemap')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-sitemap me-1"></i> साइटमैप</a>
    <a class="btn btn-outline-secondary" href="<?= e(route('robots')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-robot me-1"></i> robots.txt</a>
  </div>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => 'index']) ?>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <section class="panel h-100"><div class="panel-body seo-score">
      <div class="seo-ring text-<?= $tone ?>" style="--p:<?= $score ?>" role="img" aria-label="SEO स्कोर <?= $score ?> प्रतिशत"><b><?= $score ?></b><small>/100</small></div>
      <div>
        <h2 class="h6 mb-1">ख़बरों का SEO स्कोर</h2>
        <p class="small mb-1"><?= num($counts['healthy'] ?? 0) ?> / <?= num($counts['total'] ?? 0) ?> प्रकाशित ख़बरों में कोई गंभीर कमी नहीं।</p>
        <p class="small text-body-secondary mb-0">गंभीर: विवरण, इमेज, श्रेणी, कम शब्द, एक जैसे शीर्षक।</p>
      </div>
    </div></section>
  </div>
  <?php foreach ([['Google News (48 घंटे)', num($news48), 'fa-newspaper', route('sitemap.part', ['part' => 'news'])], ['404 (30 दिन, नए)', num($nf['c'] ?? 0), 'fa-link-slash', route('admin.seo.404')],
      ['टूटे अंदरूनी लिंक', num($nf['i'] ?? 0), 'fa-chain-broken', route('admin.seo.links')], ['रीडायरेक्ट (हिट)', num($redirects['c'] ?? 0) . ' (' . num($redirects['h'] ?? 0) . ')', 'fa-diamond-turn-right', route('admin.redirects.index')]] as [$l, $v, $ic, $href]): ?>
    <div class="col-6 col-lg-2"><a class="panel h-100 d-block text-reset text-decoration-none" href="<?= e($href) ?>"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= e($v) ?></b></div></a></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-list-check me-2 text-body-secondary"></i>प्रकाशित ख़बरों की कमियाँ</h2></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <tbody><?php foreach ($issues as $k => [$label, , $serious, $hint]): $c = $counts[$k] ?? 0; ?>
          <tr>
            <td><b><?= e($label) ?></b><?= $serious ? ' <span class="badge text-bg-danger-subtle text-danger-emphasis">गंभीर</span>' : '' ?><div class="small text-body-secondary"><?= e($hint) ?></div></td>
            <td class="text-end text-nowrap"><?php if ($c): ?><a class="btn btn-sm <?= $serious ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" href="<?= e(route('admin.seo.audit')) ?>?issue=<?= e($k) ?>"><?= num($c) ?> ख़बरें</a><?php else: ?><span class="text-success small"><i class="fa-solid fa-circle-check"></i> ठीक</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="panel">
      <div class="panel-head"><h2><i class="fa-solid fa-heart-pulse me-2 text-body-secondary"></i>साइट की जाँच</h2><span class="small text-body-secondary"><?= $siteOk ?>/<?= count($site) ?></span></div>
      <ul class="seo-checks">
        <?php foreach ($site as [$label, $ok, $hint, $href]): ?>
          <li class="<?= $ok ? 'ok' : 'bad' ?>"><i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <div><b><?= e($label) ?></b><?php if (!$ok): ?><small><?= e($hint) ?><?php if ($href): ?> <a href="<?= e($href) ?>">ठीक करें</a><?php endif; ?></small><?php endif; ?></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <section class="panel mt-3">
      <div class="panel-head"><h2><i class="fa-solid fa-sitemap me-2 text-body-secondary"></i>साइटमैप</h2></div>
      <div class="panel-body">
        <p class="small">Search Console में सिर्फ़ <code><?= e(\App\Services\SeoService::canonical(route('sitemap'))) ?></code> जमा करें; बाकी इसी में शामिल हैं। Google News के लिए <code>sitemap-news.xml</code> भी।</p>
        <ul class="list-unstyled small mb-0 sitemap-list">
          <li><a href="<?= e(route('sitemap')) ?>" target="_blank" rel="noopener">sitemap.xml</a> <span class="text-body-secondary">(इंडेक्स)</span></li>
          <?php foreach ($sitemaps as [$name, $mod]): ?><li><a href="<?= e(url($name)) ?>" target="_blank" rel="noopener"><?= e($name) ?></a><?= $mod ? ' <span class="text-body-secondary">· ' . e(hindi_date((string) $mod, true)) . '</span>' : '' ?></li><?php endforeach; ?>
          <li><a href="<?= e(route('feed')) ?>" target="_blank" rel="noopener">RSS फ़ीड</a></li>
        </ul>
      </div>
    </section>
  </div>
</div>

<?php if ($topNf): ?>
<section class="panel mt-3">
  <div class="panel-head"><h2><i class="fa-solid fa-link-slash me-2 text-body-secondary"></i>सबसे ज़्यादा 404</h2><a class="small" href="<?= e(route('admin.seo.404')) ?>">सब देखें</a></div>
  <div class="table-responsive"><table class="table align-middle mb-0 data-table">
    <thead><tr><th>पता</th><th>हिट</th><th>आख़िरी बार</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($topNf as $r): ?>
      <tr><td class="font-monospace small text-break"><?= e($r['path']) ?><?= $r['is_internal'] ? ' <span class="badge text-bg-warning">अंदरूनी लिंक</span>' : '' ?></td>
        <td><?= num($r['hits']) ?><?= $r['bot_hits'] ? ' <small class="text-body-secondary">+' . num($r['bot_hits']) . ' बॉट</small>' : '' ?></td>
        <td class="small"><?= e(hindi_date($r['last_seen'], true)) ?></td>
        <td class="text-end"><?php if (can('redirects.create')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.redirects.create')) ?>?source=<?= e(rawurlencode($r['path'])) ?>&amp;nf=<?= (int) $r['id'] ?>&amp;return=404">रीडायरेक्ट बनाएँ</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
