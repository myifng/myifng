<?php
/** रिपोर्टर प्रोफ़ाइल का साझा हिस्सा (एडमिन और पोर्टल) */
use App\Models\Reporter;
use App\Models\ReporterApplication;
use App\Models\ReporterDocument;
use App\Services\NewsWorkflow;
[$sl, $sc] = Reporter::STATUSES[$r['status']];
$daysLeft = (int) floor((strtotime((string) $r['valid_until']) - strtotime('today')) / 86400);
?>
<div class="rp-head panel">
  <div class="rp-photo"><?= $r['photo'] ? '<img src="' . e(upload_url($r['photo'])) . '" alt="">' : avatar_html(null, $user['name'], 'avatar-lg') ?></div>
  <div class="rp-main">
    <h2><?= e($user['name']) ?> <span class="badge-status text-bg-<?= e($sc) ?> fs-6"><i class="dot"></i><?= e($sl) ?></span></h2>
    <p class="font-monospace mb-1"><?= e($r['reporter_code']) ?></p>
    <p class="mb-0 text-body-secondary"><?php $typeLabel = Reporter::TYPES[$r['reporter_type']] ?? $r['reporter_type']; ?><?= e($r['designation']) ?><?= $typeLabel !== $r['designation'] ? ' · ' . e($typeLabel) : '' ?><?= $area ? ' · ' . e($area) : '' ?><?= $bureau ? ' · ' . e($bureau['name']) : '' ?></p>
    <?php if ($r['status_reason']): ?><p class="small text-danger mb-0">कारण: <?= e($r['status_reason']) ?></p><?php endif; ?>
  </div>
  <dl class="rp-dates">
    <dt>जॉइनिंग</dt><dd><?= hindi_date($r['joining_date']) ?></dd>
    <dt>वैधता</dt><dd class="<?= $daysLeft < 30 ? 'text-danger fw-bold' : '' ?>"><?= hindi_date($r['valid_until']) ?><?= $daysLeft >= 0 && $daysLeft < 30 ? " ($daysLeft दिन बाकी)" : ($daysLeft < 0 ? ' (ख़त्म)' : '') ?></dd>
  </dl>
</div>
<div class="stat-row mt-3">
  <?php foreach ([['प्रकाशित', $stats['published'], 'fa-globe'], ['इस महीने', $stats['this_month'], 'fa-calendar'], ['कुल व्यूज़', $stats['views'], 'fa-eye'], ['डेस्क पर', $stats['pending'], 'fa-hourglass-half'], ['अस्वीकार', $stats['rejected'], 'fa-circle-xmark'], ['असाइनमेंट पूरे', $stats['assignments_done'] . ' / ' . $stats['assignments'], 'fa-list-check']] as [$l, $v, $ic]): ?>
    <div class="mini-stat"><i class="fa-solid <?= e($ic) ?>"></i><b><?= is_int($v) ? num($v) : e($v) ?></b><span><?= e($l) ?></span></div>
  <?php endforeach; ?>
</div>
