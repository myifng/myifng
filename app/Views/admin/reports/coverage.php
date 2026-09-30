<?php
$this->layout('layouts/admin');
$title = 'लोकल कवरेज';
$q = static fn(array $o) => '?' . http_build_query(array_filter($o + ['state' => $state, 'show' => $only ? 'gaps' : null]));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.reports.index')) ?>">न्यूज़रूम रिपोर्ट</a></li><li class="breadcrumb-item active" aria-current="page">लोकल कवरेज</li></ol></nav>
    <h1>लोकल कवरेज</h1>
    <p>किन ज़िलों से ख़बरें नहीं आ रहीं और कहाँ रिपोर्टर नहीं हैं। गैप = 30 दिन में कोई ख़बर नहीं, या आख़िरी ख़बर 7 दिन से पुरानी।</p>
  </div>
  <?php if (can('reports.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.reports.coverage') . $q(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV</a><?php endif; ?>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([['fa-map', 'ज़िले', $summary['districts'], ''], ['fa-triangle-exclamation', 'गैप वाले ज़िले', $summary['gaps'], 'text-danger'], ['fa-user-slash', 'बिना रिपोर्टर', $summary['noReporter'], 'text-warning'], ['fa-newspaper', 'ख़बरें (7 दिन)', $summary['d7'], '']] as [$ic, $l, $v, $c]): ?>
    <div class="col-6 col-xl-3"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b class="<?= $c ?>"><?= num($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
  <select class="form-select" style="max-width:260px" name="state" aria-label="राज्य"><option value="">सभी राज्य</option><?php foreach ($states as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], $state) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <label class="form-check align-self-center mb-0"><input class="form-check-input" type="checkbox" name="show" value="gaps"<?= checked($only) ?>> <span class="form-check-label">सिर्फ़ गैप</span></label>
  <button class="btn btn-outline-secondary" type="submit">दिखाएँ</button>
</form>
<section class="panel"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>ज़िला</th><th class="text-end">7 दिन</th><th class="text-end">30 दिन</th><th class="d-none d-md-table-cell">आख़िरी ख़बर</th><th class="text-end">रिपोर्टर</th><th class="text-end d-none d-md-table-cell">समीक्षा में</th></tr></thead>
  <tbody><?php foreach ($rows as $r): ?>
    <tr class="<?= $r['gap'] ? 'table-warning' : '' ?>">
      <td><b><?= e($r['name']) ?></b> <?php if ($r['gap']): ?><span class="badge text-bg-danger">गैप</span><?php endif; ?><div class="small text-body-secondary"><?= e($r['state']) ?><?= $r['path'] ? ' · <a href="' . e(url($r['path'])) . '" target="_blank" rel="noopener">पेज</a>' : '' ?></div></td>
      <td class="text-end fw-semibold"><?= num($r['d7']) ?></td><td class="text-end"><?= num($r['d30']) ?></td>
      <td class="d-none d-md-table-cell small"><?= $r['last'] ? time_ago($r['last']) : '<span class="text-danger">कभी नहीं</span>' ?></td>
      <td class="text-end<?= $r['reporters'] ? '' : ' text-danger' ?>"><?= num($r['reporters']) ?></td>
      <td class="text-end d-none d-md-table-cell"><?= num($r['pending']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-body-secondary p-4">कोई ज़िला नहीं।</td></tr><?php endif; ?></tbody>
</table></div></section>
<p class="small text-body-secondary mt-2">गैप वाले ज़िलों के लिए <?php if (can('assignments.create')): ?><a href="<?= e(route('admin.assignments.create')) ?>">असाइनमेंट बनाएँ</a> या <?php endif; ?><?php if (can('applications.view')): ?><a href="<?= e(route('admin.applications.index')) ?>">रिपोर्टर आवेदन</a> देखें<?php endif; ?>।</p>
