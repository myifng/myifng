<?php
use App\Services\AdvertiserService;
$this->layout('layouts/admin');
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$title = 'राजस्व';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item active" aria-current="page">राजस्व</li></ol></nav>
    <h1>विज्ञापन राजस्व</h1>
    <p>आमदनी = दर्ज भुगतान; बिल = भेजे गए इनवॉइस।</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i> इनवॉइस CSV</a>
</div>
<?= $this->insert('admin/advertisers/_kpi', ['kpi' => $kpi]) ?>
<section class="panel mb-3"><div class="panel-head"><h2>पिछले 12 महीने</h2></div><div class="panel-body"><div style="height:260px"><canvas data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => $chart['labels'],
  'datasets' => [['label' => 'बिल (₹)', 'data' => $chart['billed'], 'color' => 'muted'], ['label' => 'आमदनी (₹)', 'data' => $chart['paid'], 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>' aria-label="12 महीने का राजस्व"></canvas></div></div></section>
<div class="row g-3">
  <div class="col-lg-6"><section class="panel h-100"><div class="panel-head"><h2>सबसे बड़े विज्ञापनदाता (12 महीने)</h2></div>
    <?php if ($top): ?><ol class="list-group list-group-flush list-group-numbered"><?php foreach ($top as $t): ?><li class="list-group-item d-flex justify-content-between"><a class="text-reset ms-2 me-auto" href="<?= e(route('admin.advertisers.show', ['id' => $t['id']])) ?>"><?= e($t['company']) ?></a><b><?= e(AdvertiserService::money($t['s'])) ?></b></li><?php endforeach; ?></ol>
    <?php else: ?><div class="empty-state py-3"><p class="mb-0">अभी कोई भुगतान नहीं।</p></div><?php endif; ?></section></div>
  <div class="col-lg-6"><section class="panel h-100"><div class="panel-head"><h2>देय तारीख़ निकल चुकी</h2></div>
    <?php if ($overdue): ?><ul class="list-group list-group-flush"><?php foreach ($overdue as $i): ?><li class="list-group-item d-flex justify-content-between"><span><a class="font-monospace" href="<?= e(route('admin.invoices.show', ['id' => $i['id']])) ?>"><?= e($i['invoice_no']) ?></a> · <?= e($i['company']) ?><br><small class="text-danger">देय: <?= hindi_date($i['due_date']) ?></small></span><b><?= e(AdvertiserService::money($i['total'] - $i['paid'])) ?></b></li><?php endforeach; ?></ul>
    <?php else: ?><div class="empty-state py-3"><p class="mb-0">कोई बकाया देर से नहीं।</p></div><?php endif; ?></section></div>
</div>
