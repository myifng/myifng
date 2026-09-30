<?php
use App\Services\AnalyticsService as A;
$this->layout('layouts/admin');
$title = 'एनालिटिक्स';
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$delta = static function (int|float $a, int|float $b): string {
    $c = A::change($a, $b);
    if ($c === null) {
        return '<small class="text-body-secondary">पिछली अवधि का डेटा नहीं</small>';
    }
    $up = $c >= 0;
    return '<small class="' . ($up ? 'text-success' : 'text-danger') . '"><i class="fa-solid fa-arrow-' . ($up ? 'up' : 'down') . '"></i> ' . e((string) abs($c)) . '% पिछली अवधि से</small>';
};
$labels = array_map(static fn($d) => date('d/m', strtotime($d['day'])), $series);
$qs = '?' . http_build_query(array_filter(['range' => $r['key'], 'from' => $r['key'] === 'custom' ? $r['from'] : null, 'to' => $r['key'] === 'custom' ? $r['to'] : null]));
$dimTable = static function (string $head, array $rows, string $dim) use ($qs): string {
    $total = array_sum(array_map(static fn($x) => (int) $x['views'], $rows)) ?: 1;
    $h = '<section class="panel h-100"><div class="panel-head"><h2>' . e($head) . '</h2>'
        . (in_array($dim, ['category', 'location', 'reporter'], true) ? '<a class="small" href="' . e(route('admin.analytics.content', ['dim' => $dim]) . $qs) . '">सभी</a>' : '') . '</div>';
    if (!$rows) {
        return $h . '<div class="panel-body text-body-secondary small">इस अवधि में डेटा नहीं।</div></section>';
    }
    $h .= '<ul class="stat-list">';
    foreach ($rows as $x) {
        $p = round($x['views'] * 100 / $total);
        $h .= '<li><div class="bl-row"><span class="text-truncate">' . e((string) $x['name']) . '</span><b>' . num((int) $x['views']) . '</b></div><div class="bl-bar"><i style="width:' . (int) $p . '%"></i></div></li>';
    }
    return $h . '</ul></section>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">एनालिटिक्स</li></ol></nav>
    <h1>एनालिटिक्स</h1>
    <p><?= e($r['label']) ?> · <?= hindi_date($r['from']) ?><?= $r['from'] !== $r['to'] ? ' – ' . hindi_date($r['to']) : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('analytics.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.analytics.export', ['dim' => 'news']) . $qs) ?>"><i class="fa-solid fa-file-csv me-1"></i> ख़बरें CSV</a><?php endif; ?>
  </div>
</div>
<?= $this->insert('admin/analytics/_nav', ['active' => 'index', 'r' => $r]) ?>
<?= $this->insert('admin/analytics/_range', ['r' => $r, 'action' => route('admin.analytics.index')]) ?>

<?php if (!$enabled): ?><div class="alert alert-warning">अपना एनालिटिक्स बंद है (सेटिंग → एनालिटिक्स और कोड)। नए व्यू दर्ज नहीं हो रहे।</div><?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-eye me-1"></i>पेज व्यू</span><b><?= num($now['views']) ?></b><?= $delta($now['views'], $before['views']) ?></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-users me-1"></i>यूनीक विज़िटर <abbr title="हर दिन के यूनीक विज़िटर का जोड़ (कुकी के बिना गिनती)">(रोज़)</abbr></span><b><?= num($now['visitors']) ?></b><?= $delta($now['visitors'], $before['visitors']) ?></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-newspaper me-1"></i>ख़बरों के व्यू</span><b><?= num($now['news_views']) ?></b><?= $delta($now['news_views'], $before['news_views']) ?></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-layer-group me-1"></i>पेज / विज़िटर</span><b><?= e((string) $now['per_visitor']) ?></b><small class="text-body-secondary">पहले <?= e((string) $before['per_visitor']) ?></small></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-share-nodes me-1"></i>शेयर</span><b><?= num($now['shares']) ?></b><?= $delta($now['shares'], $before['shares']) ?></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100 rt-card" data-realtime="<?= e(route('admin.analytics.realtime')) ?>"><div class="panel-body kpi"><span><i class="fa-solid fa-circle rt-dot me-1"></i>अभी पढ़ रहे</span><b data-rt="readers"><?= num($realtime['readers']) ?></b><small class="text-body-secondary">पिछले 5 मिनट · <span data-rt="at"><?= e($realtime['at']) ?></span></small></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2><i class="fa-solid fa-chart-line me-2 text-body-secondary"></i>रोज़ के व्यू और विज़िटर</h2></div>
      <div class="panel-body"><div class="chart-box"><canvas role="img" aria-label="रोज़ के पेज व्यू और विज़िटर" data-chart='<?= e(json_encode(['type' => 'line', 'labels' => $labels, 'datasets' => [
          ['label' => 'पेज व्यू', 'data' => array_column($series, 'views'), 'color' => 'brand'], ['label' => 'विज़िटर', 'data' => array_column($series, 'visitors'), 'color' => '#2f7de1'],
          ['label' => 'पिछली अवधि (व्यू)', 'data' => array_column($prev, 'views'), 'color' => 'muted']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="panel h-100">
      <div class="panel-head"><h2><i class="fa-solid fa-bolt me-2 text-body-secondary"></i>रियलटाइम</h2><span class="small text-body-secondary">हर 15 सेकंड</span></div>
      <div class="panel-body">
        <div class="chart-box xs"><canvas role="img" aria-label="पिछले 30 मिनट के व्यू" data-rt-chart data-chart='<?= e(json_encode(['type' => 'bar', 'legend' => false, 'labels' => array_column($realtime['series'], 'm'), 'datasets' => [['label' => 'व्यू / मिनट', 'data' => array_column($realtime['series'], 'c'), 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div>
        <ol class="rt-pages" data-rt="pages">
          <?php foreach ($realtime['pages'] as $p): ?><li><span class="text-truncate" title="<?= e($p['path']) ?>"><?= e($p['title']) ?></span><b><?= num($p['readers']) ?></b></li><?php endforeach; ?>
          <?php if (!$realtime['pages']): ?><li class="text-body-secondary">अभी कोई पाठक नहीं।</li><?php endif; ?>
        </ol>
      </div>
    </section>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2><i class="fa-solid fa-ranking-star me-2 text-body-secondary"></i>सबसे ज़्यादा पढ़ी ख़बरें</h2><a class="small" href="<?= e(route('admin.analytics.content', ['dim' => 'news']) . $qs) ?>">सभी ख़बरें</a></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>ख़बर</th><th class="text-end">व्यू</th><th class="text-end d-none d-md-table-cell">विज़िटर</th><th class="text-end d-none d-md-table-cell">शेयर</th></tr></thead>
        <tbody>
          <?php foreach ($news as $n): ?>
            <tr><td><a class="fw-semibold" href="<?= e(route('admin.analytics.news', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a><div class="small text-body-secondary"><?= e(trim(($n['category'] ?? '') . ' · ' . ($n['reporter'] ?? ''), ' ·')) ?></div></td>
              <td class="text-end fw-semibold"><?= num($n['views']) ?></td><td class="text-end d-none d-md-table-cell"><?= num($n['visitors']) ?></td><td class="text-end d-none d-md-table-cell"><?= num($n['shares']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$news): ?><tr><td colspan="4" class="text-body-secondary">इस अवधि में अभी डेटा नहीं।</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="panel h-100">
      <div class="panel-head"><h2><i class="fa-solid fa-clock me-2 text-body-secondary"></i>किस घंटे पढ़ते हैं</h2></div>
      <div class="panel-body"><div class="chart-box sm"><canvas role="img" aria-label="घंटे के हिसाब से व्यू" data-chart='<?= e(json_encode(['type' => 'bar', 'legend' => false, 'labels' => array_map(static fn($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), range(0, 23)), 'datasets' => [['label' => 'व्यू', 'data' => $hours, 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div>
    </section>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach (['device' => 'डिवाइस', 'source' => 'ट्रैफ़िक स्रोत'] as $dim => $head): $rows = $top[$dim]; ?>
    <div class="col-md-6 col-xl-3">
      <section class="panel h-100"><div class="panel-head"><h2><?= e($head) ?></h2></div>
        <div class="panel-body"><?php if ($rows): ?><div class="chart-box xs"><canvas role="img" aria-label="<?= e($head) ?>" data-chart='<?= e(json_encode(['type' => 'doughnut', 'labels' => array_column($rows, 'name'), 'datasets' => [['label' => $head, 'data' => array_map('intval', array_column($rows, 'views'))]]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div><?php else: ?><p class="small text-body-secondary mb-0">डेटा नहीं।</p><?php endif; ?></div>
      </section>
    </div>
  <?php endforeach; ?>
  <div class="col-md-6 col-xl-3"><?= $dimTable('रेफ़रर साइटें', $top['referrer'], 'referrer') ?></div>
  <div class="col-md-6 col-xl-3"><?= $dimTable('पेज के प्रकार', $top['type'], 'type') ?></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6 col-xl-4"><?= $dimTable('श्रेणियाँ', $top['category'], 'category') ?></div>
  <div class="col-md-6 col-xl-4"><?= $dimTable('लोकेशन / शहर', $top['location'], 'location') ?></div>
  <div class="col-md-6 col-xl-4"><?= $dimTable('रिपोर्टर (उनकी ख़बरों के व्यू)', $top['reporter'], 'reporter') ?></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6 col-xl-4">
    <section class="panel h-100"><div class="panel-head"><h2><i class="fa-solid fa-share-nodes me-2 text-body-secondary"></i>सोशल शेयर</h2></div>
      <div class="panel-body">
        <?php if ($shares['networks']): ?>
          <div class="d-flex flex-wrap gap-2 mb-2"><?php foreach ($shares['networks'] as $s): ?><span class="badge text-bg-light border"><?= e(A::NETWORKS[$s['k']] ?? $s['k']) ?>: <?= num((int) $s['c']) ?></span><?php endforeach; ?></div>
          <ol class="small ps-3 mb-0"><?php foreach ($shares['top'] as $s): ?><li><a href="<?= e(route('admin.analytics.news', ['id' => $s['id']])) ?>"><?= e($s['title']) ?></a> · <?= num((int) $s['c']) ?></li><?php endforeach; ?></ol>
        <?php else: ?><p class="small text-body-secondary mb-0">इस अवधि में शेयर दर्ज नहीं हुए। (ख़बर पेज के शेयर बटन से गिनती होती है।)</p><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-md-6 col-xl-4">
    <section class="panel h-100"><div class="panel-head"><h2><i class="fa-solid fa-book-open me-2 text-body-secondary"></i>ई-पेपर पढ़ाई</h2><b><?= num($epaper['reads']) ?></b></div>
      <div class="panel-body">
        <?php if ($epaper['top']): ?><ol class="small ps-3 mb-0"><?php foreach ($epaper['top'] as $i): ?><li><?= e($i['edition']) ?> · <?= hindi_date($i['issue_date']) ?> — <?= num($i['reads']) ?> <span class="text-body-secondary">(कुल <?= num((int) $i['total']) ?>)</span></li><?php endforeach; ?></ol>
        <?php else: ?><p class="small text-body-secondary mb-0">इस अवधि में ई-पेपर नहीं पढ़ा गया।</p><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-md-12 col-xl-4">
    <section class="panel h-100"><div class="panel-head"><h2><i class="fa-solid fa-rectangle-ad me-2 text-body-secondary"></i>विज्ञापन प्रदर्शन</h2><?php if (can('ads.view')): ?><a class="small" href="<?= e(route('admin.ads.index')) ?>">विज्ञापन</a><?php endif; ?></div>
      <div class="panel-body">
        <div class="d-flex gap-3 mb-2 small"><span>इंप्रेशन <b><?= num($ads['imp']) ?></b></span><span>क्लिक <b><?= num($ads['clk']) ?></b></span><span>CTR <b><?= e((string) $ads['ctr']) ?>%</b></span></div>
        <?php if ($ads['top']): ?><ol class="small ps-3 mb-0"><?php foreach ($ads['top'] as $a): ?><li><?= e($a['title']) ?> — <?= num((int) $a['imp']) ?> / <?= num((int) $a['clk']) ?></li><?php endforeach; ?></ol><?php endif; ?>
      </div>
    </section>
  </div>
</div>

<section class="panel">
  <div class="panel-body small text-body-secondary">
    <i class="fa-solid fa-circle-info me-1"></i>
    यह साइट का अपना एनालिटिक्स है: कुकी नहीं, IP सेव नहीं (रोज़ बदलने वाला हैश), बॉट/एडमिन/स्टाफ़ गिनती में नहीं।
    <?php if ($ga['ga'] || $ga['gtm']): ?>Google Analytics (<?= e($ga['ga'] ?: $ga['gtm']) ?>) भी साथ में चालू है; शेयर का इवेंट वहाँ भी जाता है।<?php elseif (can('settings.manage')): ?>Google Analytics / Tag Manager जोड़ने के लिए <a href="<?= e(route('admin.settings', ['tab' => 'analytics'])) ?>">सेटिंग</a>।<?php endif; ?>
  </div>
</section>
