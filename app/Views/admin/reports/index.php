<?php
use App\Services\AnalyticsService as A;
use App\Services\NewsroomReport as NR;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'न्यूज़रूम रिपोर्ट';
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$qs = '?' . http_build_query(array_filter(['range' => $r['key'], 'from' => $r['key'] === 'custom' ? $r['from'] : null, 'to' => $r['key'] === 'custom' ? $r['to'] : null]));
$cmp = static function (int $a, int $b): string {
    $c = A::change($a, $b);
    return $c === null ? '<small class="text-body-secondary">पहले ' . num($b) . '</small>' : '<small class="text-body-secondary">पहले ' . num($b) . ' (' . ($c >= 0 ? '+' : '') . e((string) $c) . '%)</small>';
};
$csv = static fn(string $k) => can('reports.export') ? '<a class="small" href="' . e(route('admin.reports.export', ['kind' => $k]) . $qs) . '"><i class="fa-solid fa-file-csv me-1"></i>CSV</a>' : '';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">न्यूज़रूम रिपोर्ट</li></ol></nav>
    <h1>न्यूज़रूम रिपोर्ट</h1>
    <p><?= e($r['label']) ?> · <?= hindi_date($r['from']) ?><?= $r['from'] !== $r['to'] ? ' – ' . hindi_date($r['to']) : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="<?= e(route('admin.reports.coverage')) ?>"><i class="fa-solid fa-map-location-dot me-1"></i> लोकल कवरेज</a>
  <?php if (can('analytics.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.analytics.index') . $qs) ?>"><i class="fa-solid fa-chart-line me-1"></i> एनालिटिक्स</a><?php endif; ?></div>
</div>
<?= $this->insert('admin/analytics/_range', ['r' => $r, 'action' => route('admin.reports.index')]) ?>

<div class="row g-3 mb-3">
  <?php foreach ([['fa-paper-plane', 'भेजी गई', $sum['submitted'], $prev['submitted']], ['fa-circle-check', 'मंज़ूर', $sum['approved'], $prev['approved']], ['fa-circle-xmark', 'अस्वीकार', $sum['rejected'], $prev['rejected']],
      ['fa-globe', 'प्रकाशित', $sum['published'], $prev['published']]] as [$ic, $l, $v, $p]): ?>
    <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= num($v) ?></b><?= $cmp($v, $p) ?></div></div></div>
  <?php endforeach; ?>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-hourglass-half me-1"></i>अभी समीक्षा में</span><b><?= num($sum['pending']) ?></b><small class="<?= $sum['pending_old'] ? 'text-danger' : 'text-body-secondary' ?>"><?= num($sum['pending_old']) ?> 24 घंटे से ज़्यादा</small></div></div></div>
  <div class="col-6 col-xl"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid fa-stopwatch me-1"></i>मंज़ूरी का औसत समय</span><b><?= e(NR::hours($sum['avg_hours'])) ?></b><small class="text-body-secondary">मीडियन <?= e(NR::hours($sum['median_hours'])) ?> · <?= num($sum['approval_n']) ?> ख़बरें</small></div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-8"><section class="panel h-100"><div class="panel-head"><h2>रोज़: भेजी और प्रकाशित</h2><?php if ($sum['direct']): ?><span class="small text-body-secondary"><?= num($sum['direct']) ?> सीधे प्रकाशित (बिना समीक्षा)</span><?php endif; ?></div>
    <div class="panel-body"><div class="chart-box sm"><canvas role="img" aria-label="रोज़ भेजी और प्रकाशित ख़बरें" data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => array_map(static fn($d) => date('d/m', strtotime($d['day'])), $daily),
      'datasets' => [['label' => 'भेजी', 'data' => array_column($daily, 'sent'), 'color' => 'muted'], ['label' => 'प्रकाशित', 'data' => array_column($daily, 'published'), 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div></section></div>
  <div class="col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>कतार में सबसे पुरानी</h2><?php if (can('news.approve')): ?><a class="small" href="<?= e(route('admin.news.index')) ?>?status=submitted">सभी</a><?php endif; ?></div>
    <?php if ($queue): ?><ul class="list-group list-group-flush"><?php foreach ($queue as $q): ?>
      <li class="list-group-item"><a href="<?= e(route('admin.news.edit', ['id' => $q['id']])) ?>" class="d-block text-truncate"><?= e($q['title']) ?></a>
        <small class="text-body-secondary"><?= e(NewsWorkflow::label($q['status'])) ?> · <?= e((string) $q['reporter']) ?> · <?= e(NR::hours((time() - strtotime($q['updated_at'])) / 3600)) ?> से</small></li>
    <?php endforeach; ?></ul><?php else: ?><div class="panel-body small text-success"><i class="fa-solid fa-circle-check"></i> कोई ख़बर इंतज़ार में नहीं।</div><?php endif; ?>
  </section></div>
</div>

<section class="panel mb-3">
  <div class="panel-head"><h2><i class="fa-solid fa-id-card me-2 text-body-secondary"></i>रिपोर्टर का आउटपुट</h2><?= $csv('reporters') ?></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>रिपोर्टर</th><th class="text-end">भेजी</th><th class="text-end">प्रकाशित</th><th class="text-end">अस्वीकार</th><th class="text-end">बाकी</th><th class="text-end d-none d-md-table-cell">औसत शब्द</th><th class="text-end">व्यू</th></tr></thead>
    <tbody><?php foreach ($reporters as $x): ?>
      <tr><td class="fw-semibold"><?= e($x['name']) ?></td><td class="text-end"><?= num((int) $x['submitted']) ?></td><td class="text-end"><?= num((int) $x['published']) ?></td>
        <td class="text-end<?= $x['rejected'] ? ' text-danger' : '' ?>"><?= num((int) $x['rejected']) ?></td><td class="text-end"><?= num((int) $x['pending']) ?></td>
        <td class="text-end d-none d-md-table-cell"><?= $x['words'] ? num((int) $x['words']) : '—' ?></td><td class="text-end fw-semibold"><?= num((int) $x['views']) ?></td></tr>
    <?php endforeach; ?><?php if (!$reporters): ?><tr><td colspan="7" class="text-body-secondary">इस अवधि में कोई गतिविधि नहीं।</td></tr><?php endif; ?></tbody>
  </table></div>
</section>

<div class="row g-3 mb-3">
  <div class="col-xl-7"><section class="panel h-100">
    <div class="panel-head"><h2><i class="fa-solid fa-user-pen me-2 text-body-secondary"></i>एडिटर का वर्कलोड</h2><?= $csv('editors') ?></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>एडिटर</th><th class="text-end">कार्रवाई</th><th class="text-end">मंज़ूर</th><th class="text-end">अस्वीकार</th><th class="text-end">प्रकाशित</th><th class="text-end d-none d-md-table-cell">औसत समय</th><th class="text-end">अभी कतार</th></tr></thead>
      <tbody><?php foreach ($editors as $x): ?>
        <tr><td class="fw-semibold"><?= e($x['name']) ?></td><td class="text-end"><?= num((int) $x['actions']) ?></td><td class="text-end"><?= num((int) $x['approved']) ?></td><td class="text-end"><?= num((int) $x['rejected']) ?></td>
          <td class="text-end"><?= num((int) $x['published']) ?></td><td class="text-end d-none d-md-table-cell"><?= e(NR::hours($x['avg_hours'])) ?></td><td class="text-end"><?= num($x['queue']) ?></td></tr>
      <?php endforeach; ?><?php if (!$editors): ?><tr><td colspan="7" class="text-body-secondary">इस अवधि में कोई समीक्षा नहीं।</td></tr><?php endif; ?></tbody>
    </table></div>
  </section></div>
  <div class="col-xl-5"><section class="panel h-100">
    <div class="panel-head"><h2><i class="fa-solid fa-list-check me-2 text-body-secondary"></i>असाइनमेंट</h2><?= $csv('assignments') ?></div>
    <div class="panel-body">
      <div class="row g-2 text-center mb-2">
        <?php foreach ([['कुल', $assign['total'] ?? 0], ['पूरे', $assign['completed'] ?? 0], ['बाकी', $assign['open'] ?? 0], ['ओवरड्यू', $assign['overdue'] ?? 0]] as [$l, $v]): ?>
          <div class="col-3"><div class="mini-stat"><b class="<?= $l === 'ओवरड्यू' && $v ? 'text-danger' : '' ?>"><?= num((int) $v) ?></b><span><?= e($l) ?></span></div></div>
        <?php endforeach; ?>
      </div>
      <p class="small mb-2">पूरे होने की दर: <b><?= $assign['rate'] === null ? '—' : e((string) $assign['rate']) . '%' ?></b> · समय पर: <b><?= $assign['ontime_rate'] === null ? '—' : e((string) $assign['ontime_rate']) . '%' ?></b></p>
      <?php if ($assign['by']): ?><ul class="stat-list px-0"><?php foreach ($assign['by'] as $x): $p = $x['total'] ? round($x['completed'] * 100 / $x['total']) : 0; ?>
        <li><div class="bl-row"><span class="text-truncate"><?= e($x['name']) ?></span><b><?= num((int) $x['completed']) ?>/<?= num((int) $x['total']) ?><?= $x['overdue'] ? ' <span class="text-danger">(' . num((int) $x['overdue']) . ' ओवरड्यू)</span>' : '' ?></b></div><div class="bl-bar"><i style="width:<?= (int) $p ?>%"></i></div></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
  </section></div>
</div>

<section class="panel">
  <div class="panel-head"><h2><i class="fa-solid fa-bolt me-2 text-body-secondary"></i>ब्रेकिंग का प्रदर्शन</h2>
    <span class="small text-body-secondary"><?= num($breaking['count']) ?> अलर्ट · <?= num($breaking['pushed']) ?> पुश · <?= num($breaking['flagged']) ?> ब्रेकिंग ख़बरें · औसत व्यू <?= num((int) $breaking['avg_views']) ?> (बाकी ख़बरें <?= num((int) $breaking['avg_all']) ?>)</span></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>अलर्ट</th><th class="d-none d-md-table-cell">समय</th><th>पुश</th><th class="text-end">ख़बर के व्यू</th><th class="text-end d-none d-md-table-cell">शेयर</th></tr></thead>
    <tbody><?php foreach ($breaking['items'] as $b): ?>
      <tr><td><b><?= e($b['title']) ?></b><?php if ($b['news_title']): ?><div class="small text-body-secondary text-truncate"><?= e($b['news_title']) ?></div><?php endif; ?></td>
        <td class="d-none d-md-table-cell small"><?= hindi_date($b['created_at'], true) ?></td>
        <td><?= $b['push_status'] === 'sent' ? '<span class="badge text-bg-success">भेजा</span>' : '<span class="badge text-bg-light border">नहीं</span>' ?></td>
        <td class="text-end"><?= $b['news_id'] ? num((int) $b['views']) : '—' ?></td><td class="text-end d-none d-md-table-cell"><?= $b['news_id'] ? num((int) $b['shares']) : '—' ?></td></tr>
    <?php endforeach; ?><?php if (!$breaking['items']): ?><tr><td colspan="5" class="text-body-secondary">इस अवधि में ब्रेकिंग अलर्ट नहीं।</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
