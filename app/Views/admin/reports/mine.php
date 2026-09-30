<?php
use App\Services\NewsroomReport as NR;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'मेरा प्रदर्शन';
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">मेरा प्रदर्शन</li></ol></nav>
    <h1>मेरा प्रदर्शन</h1>
    <p><?= e($r['label']) ?> · आपकी ख़बरें, उनके पाठक और असाइनमेंट</p>
  </div>
</div>
<?= $this->insert('admin/analytics/_range', ['r' => $r, 'action' => route('admin.performance')]) ?>
<div class="row g-3 mb-3">
  <?php foreach ([['fa-eye', 'ख़बरों के व्यू', num(array_sum(array_column($series, 'views')))], ['fa-paper-plane', 'भेजी', num($sum['submitted'])], ['fa-globe', 'प्रकाशित', num($sum['published'])],
      ['fa-circle-xmark', 'अस्वीकार', num($sum['rejected'])], ['fa-hourglass-half', 'समीक्षा में', num($sum['pending'])], ['fa-stopwatch', 'मंज़ूरी में औसत समय', NR::hours($sum['avg_hours'])]] as [$ic, $l, $v]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= e($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-3">
  <div class="col-xl-8"><section class="panel h-100"><div class="panel-head"><h2>रोज़ के व्यू</h2></div>
    <div class="panel-body"><div class="chart-box sm"><canvas role="img" aria-label="मेरी ख़बरों के रोज़ के व्यू" data-chart='<?= e(json_encode(['type' => 'line', 'legend' => false, 'labels' => array_map(static fn($d) => date('d/m', strtotime($d['day'])), $series), 'datasets' => [['label' => 'व्यू', 'data' => array_column($series, 'views'), 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div></section></div>
  <div class="col-xl-4"><section class="panel h-100"><div class="panel-head"><h2>मेरे असाइनमेंट</h2></div>
    <div class="panel-body small">
      <p class="mb-1">इस अवधि में मिले: <b><?= num((int) ($assign['total'] ?? 0)) ?></b> · पूरे: <b><?= num((int) ($assign['completed'] ?? 0)) ?></b> · बाकी: <b><?= num((int) ($assign['open'] ?? 0)) ?></b></p>
      <p class="mb-1<?= !empty($assign['overdue']) ? ' text-danger' : '' ?>">समय निकल गया: <b><?= num((int) ($assign['overdue'] ?? 0)) ?></b></p>
      <p class="mb-0">पूरे होने की दर: <b><?= $assign['rate'] === null ? '—' : e((string) $assign['rate']) . '%' ?></b> · समय पर: <b><?= $assign['ontime_rate'] === null ? '—' : e((string) $assign['ontime_rate']) . '%' ?></b></p>
    </div></section></div>
</div>
<div class="row g-3">
  <div class="col-xl-7"><section class="panel h-100"><div class="panel-head"><h2>मेरी सबसे ज़्यादा पढ़ी ख़बरें</h2></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>ख़बर</th><th class="text-end">व्यू</th><th class="text-end">शेयर</th></tr></thead><tbody>
      <?php foreach ($top as $n): ?><tr><td><?= e($n['title']) ?><div class="small text-body-secondary"><?= $n['published_at'] ? hindi_date($n['published_at']) : '' ?></div></td><td class="text-end fw-semibold"><?= num((int) $n['views']) ?></td><td class="text-end"><?= num((int) $n['shares']) ?></td></tr><?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="3" class="text-body-secondary">इस अवधि में आपकी ख़बरों के व्यू दर्ज नहीं।</td></tr><?php endif; ?>
    </tbody></table></div></section></div>
  <div class="col-xl-5"><section class="panel h-100"><div class="panel-head"><h2>समीक्षा में / लौटाई गईं</h2></div>
    <?php if ($recent): ?><ul class="list-group list-group-flush"><?php foreach ($recent as $n): ?>
      <li class="list-group-item"><a href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>" class="d-block text-truncate"><?= e($n['title']) ?></a>
        <small class="text-body-secondary"><?= e(NewsWorkflow::label($n['status'])) ?> · <?= hindi_date($n['updated_at'], true) ?></small>
        <?php if ($n['status'] === 'rejected' && $n['reason']): ?><div class="small text-danger">कारण: <?= e($n['reason']) ?></div><?php endif; ?></li>
    <?php endforeach; ?></ul><?php else: ?><div class="panel-body small text-body-secondary">कोई नहीं।</div><?php endif; ?>
  </section></div>
</div>
