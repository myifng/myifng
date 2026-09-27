<?php use App\Services\AdvertiserService as AS_; ?>
<div class="row g-3 mb-3">
  <?php foreach ([['इस महीने की आमदनी', AS_::money($kpi['month_paid']), 'fa-indian-rupee-sign', ''], ['इस साल', AS_::money($kpi['year_paid']), 'fa-chart-line', ''],
      ['बकाया', AS_::money($kpi['due']), 'fa-hourglass-half', $kpi['due'] > 0 ? 'text-warning-emphasis' : ''], ['देय तारीख़ निकल चुकी', AS_::money($kpi['overdue']), 'fa-triangle-exclamation', $kpi['overdue'] > 0 ? 'text-danger' : ''],
      ['चालू कैंपेन', num($kpi['live_campaigns']), 'fa-bullhorn', '']] as [$l, $v, $ic, $cls]): ?>
    <div class="col-6 col-lg"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b class="<?= $cls ?>"><?= e($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
