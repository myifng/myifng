<?php
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Services\AdService;
use App\Services\AdvertiserService;
$this->layout('layouts/admin');
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$title = $c['name'];
$pct = (float) $c['budget'] > 0 && $c['pricing'] !== 'fixed' ? min(100, round($spent * 100 / (float) $c['budget'])) : null;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.show', ['id' => $adv['id']])) ?>"><?= e($adv['company']) ?></a></li><li class="breadcrumb-item active" aria-current="page">कैंपेन</li></ol></nav>
    <h1><?= e($c['name']) ?> <span class="badge text-bg-<?= ['draft' => 'secondary', 'active' => 'success', 'paused' => 'warning', 'completed' => 'dark'][$c['status']] ?> align-middle"><?= e(AdCampaign::STATUSES[$c['status']]) ?></span></h1>
    <p><?= e(AdCampaign::PRICING[$c['pricing']]) ?><?= $c['pricing'] !== 'fixed' ? ' @ ' . e(AdvertiserService::money($c['rate'])) : '' ?> · <?= $c['start_date'] ? hindi_date($c['start_date']) : '—' ?> → <?= $c['end_date'] ? hindi_date($c['end_date']) : 'बिना अंत' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('ads.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.ads.create')) ?>?campaign=<?= (int) $c['id'] ?>"><i class="fa-solid fa-plus me-1"></i> विज्ञापन जोड़ें</a><?php endif; ?>
    <?php if (can('advertisers.manage')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.create')) ?>?campaign=<?= (int) $c['id'] ?>"><i class="fa-solid fa-file-invoice me-1"></i> इनवॉइस बनाएँ</a><?php endif; ?>
    <?php if (can('advertisers.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.campaigns.edit', ['id' => $c['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span>बजट</span><b><?= e(AdvertiserService::money($c['budget'])) ?></b></div></div></div>
  <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span>ख़र्च (<?= $c['pricing'] === 'fixed' ? 'तय राशि' : 'CPM/CPC से' ?>)</span><b><?= $c['pricing'] === 'fixed' ? '—' : e(AdvertiserService::money($spent)) ?></b>
    <?php if ($pct !== null): ?><div class="progress mt-1" style="height:6px" role="progressbar" aria-label="बजट में से ख़र्च" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $pct >= 100 ? 'bg-danger' : '' ?>" style="width:<?= $pct ?>%"></div></div><?php endif; ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span>इम्प्रेशन</span><b><?= num($sum['impressions']) ?></b></div></div></div>
  <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span>क्लिक · CTR</span><b><?= num($sum['clicks']) ?> · <?= e(AdService::ctr($sum['impressions'], $sum['clicks'])) ?></b></div></div></div>
</div>
<section class="panel mb-3"><div class="panel-head"><h2>पिछले 30 दिन</h2></div><div class="panel-body"><div style="height:200px"><canvas data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => $chart['labels'],
  'datasets' => [['label' => 'इम्प्रेशन', 'data' => $chart['imp'], 'color' => 'muted'], ['label' => 'क्लिक', 'data' => $chart['clk'], 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>' aria-label="30 दिन का प्रदर्शन"></canvas></div></div></section>
<section class="panel">
  <div class="panel-head"><h2>विज्ञापन</h2></div>
  <?php if ($ads): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>विज्ञापन</th><th>कहाँ</th><th class="text-end">इम्प्रेशन</th><th class="text-end">क्लिक</th><th class="text-end">CTR</th><th>स्थिति</th></tr></thead>
    <tbody><?php foreach ($ads as $a): ?>
      <tr class="<?= $a['status'] !== 'active' ? 'is-off' : '' ?>"><td><a class="fw-semibold text-reset" href="<?= e(route('admin.ads.edit', ['id' => $a['id']])) ?>"><?= e($a['name']) ?></a><div class="small text-body-secondary"><?= e(Ad::TYPES[$a['type']]) ?></div></td>
        <td class="small"><?= e($a['slots'] ?? '—') ?></td><td class="text-end"><?= num($a['impressions']) ?></td><td class="text-end"><?= num($a['clicks']) ?></td>
        <td class="text-end"><?= e(AdService::ctr((int) $a['impressions'], (int) $a['clicks'])) ?></td><td><?= e(Ad::STATUSES[$a['status']]) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><div class="empty-state py-3"><p class="mb-0">इस कैंपेन में अभी कोई विज्ञापन नहीं।</p></div><?php endif; ?>
</section>
<?php if ($c['notes']): ?><section class="panel mt-3"><div class="panel-body small" style="white-space:pre-line"><?= e($c['notes']) ?></div></section><?php endif; ?>
<?php if (can('advertisers.delete')): ?>
  <div class="danger-zone mt-3"><div><b>कैंपेन हटाएँ</b><p class="mb-0 small">विज्ञापन हाउस विज्ञापन बन जाएँगे; इनवॉइस बने रहेंगे।</p></div>
    <?= delete_button(route('admin.campaigns.destroy', ['id' => $c['id']]), '“' . $c['name'] . '” कैंपेन हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
