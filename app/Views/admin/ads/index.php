<?php
use App\Models\Ad;
use App\Services\AdService;
$this->layout('layouts/admin');
$this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop();
$title = 'विज्ञापन';
$now = time();
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">विज्ञापन</li></ol></nav>
    <h1>विज्ञापन</h1>
    <p>बैनर, HTML, AdSense, वीडियो और टेक्स्ट विज्ञापन: कहाँ, कब, किसे दिखें; इम्प्रेशन, क्लिक और CTR।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.ads.slots')) ?>"><i class="fa-solid fa-table-cells me-1"></i> स्लॉट</a>
    <?php if (can('advertisers.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.advertisers.index')) ?>"><i class="fa-solid fa-handshake me-1"></i> विज्ञापनदाता</a><?php endif; ?>
    <?php if (can('ads.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.ads.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया विज्ञापन</a><?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="panel h-100"><div class="panel-body">
    <div class="d-flex justify-content-between"><div><span class="small text-body-secondary">आज</span><div class="fs-4 fw-bold"><?= num($today['i']) ?> <small class="fs-6 fw-normal text-body-secondary">इम्प्रेशन</small></div></div>
    <div class="text-end"><span class="small text-body-secondary">क्लिक · CTR</span><div class="fs-4 fw-bold"><?= num($today['c']) ?> <small class="fs-6 fw-normal text-body-secondary"><?= e(AdService::ctr((int) $today['i'], (int) $today['c'])) ?></small></div></div></div>
    <hr class="my-2"><div class="small text-body-secondary">पिछले 7 दिन: <?= num($week['i']) ?> इम्प्रेशन · <?= num($week['c']) ?> क्लिक · CTR <?= e(AdService::ctr((int) $week['i'], (int) $week['c'])) ?></div>
  </div></div></div>
  <div class="col-md-8"><div class="panel h-100"><div class="panel-body"><div style="height:150px"><canvas data-chart='<?= e(json_encode(['type' => 'line', 'labels' => $chart['labels'], 'legend' => true,
      'datasets' => [['label' => 'इम्प्रेशन', 'data' => $chart['imp'], 'color' => 'muted'], ['label' => 'क्लिक', 'data' => $chart['clk'], 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>' aria-label="30 दिन के इम्प्रेशन और क्लिक"></canvas></div></div></div></div>
</div>

<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><?php foreach (['' => 'सभी स्थिति', 'running' => 'अभी चल रहे', 'active' => 'चालू', 'paused' => 'रुके', 'draft' => 'ड्राफ़्ट', 'ended' => 'समय ख़त्म'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="slot" aria-label="स्लॉट"><option value="">सभी स्लॉट</option><?php foreach ($slots as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $f['slot']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="campaign" aria-label="कैंपेन"><option value="">सभी कैंपेन</option><?php foreach ($campaigns as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $f['campaign']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="type" aria-label="प्रकार"><option value="">सभी प्रकार</option><?php foreach (Ad::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['type']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="नाम से खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
    <?php if (can('ads.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.ads.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV (इस महीने)</a><?php endif; ?>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>विज्ञापन</th><th>कहाँ</th><th>समय</th><th class="text-end">इम्प्रेशन</th><th class="text-end">क्लिक</th><th class="text-end">CTR</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($items->items as $a):
        $ended = $a['end_at'] && strtotime($a['end_at']) <= $now; $future = $a['start_at'] && strtotime($a['start_at']) > $now;
        $code = in_array($a['type'], Ad::CODE_TYPES, true); ?>
        <tr class="<?= $a['status'] !== 'active' || $ended ? 'is-off' : '' ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($a['image']): ?><img class="thumb-sm" src="<?= e(upload_url($a['image'])) ?>" alt=""><?php else: ?><span class="cat-dot" style="--c:#9aa0a6"><i class="fa-solid <?= ['html' => 'fa-code', 'adsense' => 'fa-rectangle-ad', 'video' => 'fa-video', 'link' => 'fa-link', 'image' => 'fa-image'][$a['type']] ?>"></i></span><?php endif; ?>
              <div class="min-w-0"><b class="d-block"><a class="text-reset" href="<?= e(route('admin.ads.edit', ['id' => $a['id']])) ?>"><?= e($a['name']) ?></a></b>
                <span class="small text-body-secondary"><?= e(Ad::TYPES[$a['type']]) ?><?= $a['advertiser'] ? ' · ' . e($a['advertiser']) : ' · हाउस विज्ञापन' ?><?= $a['priority'] != 5 ? ' · प्राथमिकता ' . (int) $a['priority'] : '' ?></span></div>
            </div>
          </td>
          <td class="small"><?= $a['slots'] ? e($a['slots']) : '<span class="text-warning-emphasis">कोई स्लॉट नहीं</span>' ?><?= $a['devices'] !== 'all' ? '<div class="text-body-secondary">' . e(Ad::DEVICES[$a['devices']]) . '</div>' : '' ?></td>
          <td class="small text-nowrap"><?= $a['start_at'] ? hindi_date($a['start_at']) : 'अभी से' ?> → <?= $a['end_at'] ? hindi_date($a['end_at']) : 'बिना अंत' ?><?= $future ? '<div class="text-info">शुरू नहीं हुआ</div>' : ($ended ? '<div class="text-body-secondary">समय ख़त्म</div>' : '') ?></td>
          <td class="text-end"><?= num($a['impressions']) ?><?= $a['max_impressions'] ? '<div class="small text-body-secondary">/ ' . num($a['max_impressions']) . '</div>' : '' ?></td>
          <td class="text-end"><?= num($a['clicks']) ?></td>
          <td class="text-end"><?= e(AdService::ctr((int) $a['impressions'], (int) $a['clicks'])) ?></td>
          <td><?= ['active' => status_badge('active'), 'paused' => '<span class="badge-status text-bg-warning"><i class="dot"></i>रुका</span>', 'draft' => '<span class="badge-status text-bg-secondary"><i class="dot"></i>ड्राफ़्ट</span>'][$a['status']] ?></td>
          <td class="text-end text-nowrap">
            <?php if (can('ads.edit') && (!$code || can('ads.manage'))): ?>
              <form method="post" action="<?= e(route('admin.ads.toggle', ['id' => $a['id']])) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" title="<?= $a['status'] === 'active' ? 'रोकें' : 'चालू करें' ?>" aria-label="<?= $a['status'] === 'active' ? 'रोकें' : 'चालू करें' ?>" data-no-lock><i class="fa-solid fa-<?= $a['status'] === 'active' ? 'pause' : 'play' ?>"></i></button></form>
              <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.ads.edit', ['id' => $a['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a>
            <?php endif; ?>
            <?php if (can('ads.delete') && (!$code || can('ads.manage'))): ?><?= delete_button(route('admin.ads.destroy', ['id' => $a['id']]), '“' . $a['name'] . '” और उसके आँकड़े हट जाएँगे।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-rectangle-ad"></i><p>कोई विज्ञापन नहीं मिला।</p></div>
  <?php endif; ?>
</section>
