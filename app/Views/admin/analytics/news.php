<?php
use App\Services\AnalyticsService as A;
use App\Controllers\Admin\AnalyticsController as AC;
$this->layout('layouts/admin');
$title = 'ख़बर का प्रदर्शन';
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$h = array_fill(0, 24, 0);
foreach ($hours as $x) { $h[(int) $x['h']] = (int) $x['c']; }
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.analytics.index')) ?>">एनालिटिक्स</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.analytics.content', ['dim' => 'news'])) ?>">ख़बरें</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $n['id'] ?></li></ol></nav>
    <h1 class="h3"><?= e($n['title']) ?></h1>
    <p><?= e(trim(($n['category'] ?? '') . ' · ' . ($n['reporter'] ?? ''), ' ·')) ?><?= $n['published_at'] ? ' · प्रकाशित ' . hindi_date($n['published_at'], true) : ' · अभी प्रकाशित नहीं' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($n['status'] === 'published'): ?><a class="btn btn-outline-secondary" href="<?= e(\App\Services\NewsService::url($n)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> देखें</a><?php endif; ?>
    <?php if (can('news.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ([['fa-eye', 'कुल व्यू (शुरू से)', num($sum['total'])], ['fa-calendar', 'पिछले 30 दिन', num($sum['views'])], ['fa-users', 'विज़िटर (30 दिन)', num($sum['visitors'])],
      ['fa-bolt', 'पहले 2 दिन', num((int) ($sum['first']['v'] ?? 0))], ['fa-share-nodes', 'शेयर', num($sum['shares'])], ['fa-ranking-star', 'रैंक (7 दिन)', $sum['rank'] ? '#' . num($sum['rank']) : '—']] as [$ic, $l, $v]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= e($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8"><section class="panel h-100"><div class="panel-head"><h2>रोज़ के व्यू</h2><span class="small text-body-secondary"><?= hindi_date($from) ?> से</span></div>
    <div class="panel-body"><div class="chart-box sm"><canvas role="img" aria-label="रोज़ के व्यू" data-chart='<?= e(json_encode(['type' => 'line', 'labels' => array_map(static fn($d) => date('d/m', strtotime($d['day'])), $series),
      'datasets' => [['label' => 'व्यू', 'data' => array_column($series, 'views'), 'color' => 'brand'], ['label' => 'विज़िटर', 'data' => array_column($series, 'visitors'), 'color' => '#2f7de1']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div></section></div>
  <div class="col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>ट्रेंडिंग नियंत्रण</h2></div>
    <div class="panel-body">
      <?php if ($override): ?>
        <p class="mb-2"><span class="badge <?= $override['action'] === 'pin' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $override['action'] === 'pin' ? 'पिन है' : 'छुपी है' ?></span>
          <?= $override['until'] ? 'तक: ' . hindi_date($override['until'], true) : '(हमेशा)' ?></p>
        <?php if ($canOverride): ?><form method="post" action="<?= e(route('admin.analytics.trending.remove', ['id' => $n['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">ओवरराइड हटाएँ</button></form><?php endif; ?>
      <?php elseif ($canOverride && $n['status'] === 'published'): ?>
        <form method="post" action="<?= e(route('admin.analytics.trending.override')) ?>" class="d-grid gap-2">
          <?= csrf_field() ?><input type="hidden" name="news" value="<?= (int) $n['id'] ?>">
          <label class="form-label mb-0 small" for="ov-h">कितनी देर</label>
          <select id="ov-h" name="hours" class="form-select form-select-sm"><?php foreach (AC::HOURS as $k => $l): ?><option value="<?= e((string) $k) ?>"<?= selected($k, '24') ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <div class="d-flex gap-2"><button class="btn btn-sm btn-success" name="action" value="pin"><i class="fa-solid fa-thumbtack me-1"></i>ट्रेंडिंग में पिन</button>
            <button class="btn btn-sm btn-outline-secondary" name="action" value="hide"><i class="fa-solid fa-eye-slash me-1"></i>छुपाएँ</button></div>
        </form>
      <?php else: ?><p class="small text-body-secondary mb-0">ख़बर अपने स्कोर से ट्रेंडिंग में आती है।</p><?php endif; ?>
    </div></section></div>
</div>

<div class="row g-3">
  <?php foreach ([['ट्रैफ़िक स्रोत', $sources], ['डिवाइस', $devices], ['रेफ़रर साइटें', array_map(static fn($x) => $x + ['name' => $x['k'] ?? 'सीधे'], array_filter($referrers, static fn($x) => $x['k'] !== null))],
      ['शेयर (नेटवर्क)', array_map(static fn($x) => $x + ['name' => A::NETWORKS[$x['k']] ?? $x['k']], $networks)]] as [$head, $rows]): $tot = array_sum(array_map(static fn($x) => (int) $x['c'], $rows)) ?: 1; ?>
    <div class="col-md-6 col-xl-3"><section class="panel h-100"><div class="panel-head"><h2><?= e($head) ?></h2></div>
      <?php if ($rows): ?><ul class="stat-list"><?php foreach ($rows as $x): ?><li><div class="bl-row"><span class="text-truncate"><?= e((string) $x['name']) ?></span><b><?= num((int) $x['c']) ?></b></div><div class="bl-bar"><i style="width:<?= (int) round($x['c'] * 100 / $tot) ?>%"></i></div></li><?php endforeach; ?></ul>
      <?php else: ?><div class="panel-body small text-body-secondary">डेटा नहीं।</div><?php endif; ?>
    </section></div>
  <?php endforeach; ?>
  <div class="col-12"><section class="panel"><div class="panel-head"><h2>किस घंटे पढ़ी गई</h2><span class="small text-body-secondary">पिछले <?= (int) setting('analytics_raw_days', '35') ?> दिन के हिट</span></div>
    <div class="panel-body"><div class="chart-box xs"><canvas role="img" aria-label="घंटे के हिसाब से व्यू" data-chart='<?= e(json_encode(['type' => 'bar', 'legend' => false, 'labels' => array_map(static fn($x) => str_pad((string) $x, 2, '0', STR_PAD_LEFT), range(0, 23)), 'datasets' => [['label' => 'व्यू', 'data' => $h, 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div></section></div>
</div>
