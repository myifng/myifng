<?php
use App\Services\BackupService as BS;
use App\Services\SystemService as SYS;
$this->layout('layouts/admin');
$title = 'सिस्टम';
$bad = 0;
foreach ($health as $rows) { foreach ($rows as $r) { $bad += $r[2] ? 0 : 1; } }
$canManage = can('system.manage');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">सिस्टम</li></ol></nav>
    <h1>सिस्टम: सेहत और कैश</h1>
    <p><?= $bad ? '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> ' . num($bad) . ' चीज़ें ध्यान माँगती हैं</span>' : '<span class="text-success"><i class="fa-solid fa-circle-check"></i> सब ठीक</span>' ?></p>
  </div>
</div>
<?= $this->insert('admin/system/_nav', ['active' => 'system']) ?>

<div class="row g-3 mb-3">
  <?php foreach ($health as $group => $rows): ?>
    <div class="col-md-6"><section class="panel h-100"><div class="panel-head"><h2><?= e($group) ?></h2></div>
      <ul class="health-list">
        <?php foreach ($rows as [$name, $val, $ok, $hint]): ?>
          <li class="<?= $ok ? 'ok' : 'bad' ?>"><i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i><span><?= e($name) ?><?php if (!$ok && $hint): ?><small><?= e($hint) ?></small><?php endif; ?></span><b><?= e($val) ?></b></li>
        <?php endforeach; ?>
      </ul>
    </section></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-7"><section class="panel h-100">
    <div class="panel-head"><h2><i class="fa-solid fa-bolt me-2 text-body-secondary"></i>कैश</h2>
      <?php if ($canManage): ?><form method="post" action="<?= e(route('admin.system.cache')) ?>" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><?php if ($opcache): ?><label class="form-check small mb-0"><input class="form-check-input" type="checkbox" name="opcache" value="1"> OPcache भी</label><?php endif; ?><button class="btn btn-sm btn-brand" type="submit"><i class="fa-solid fa-broom me-1"></i>सारा कैश साफ़ करें</button></form><?php endif; ?></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th>हिस्सा</th><th class="text-end">फ़ाइलें</th><th class="text-end">आकार</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($cache as $c): ?><tr><td><?= e($c['label']) ?> <small class="text-body-secondary"><?= e($c['group']) ?></small></td><td class="text-end"><?= num($c['files']) ?></td><td class="text-end"><?= e(BS::size($c['size'])) ?></td>
        <?php if ($canManage): ?><td class="text-end"><form method="post" action="<?= e(route('admin.system.cache')) ?>"><?= csrf_field() ?><input type="hidden" name="group" value="<?= e($c['group']) ?>"><button class="btn btn-sm btn-link p-0" type="submit">साफ़ करें</button></form></td><?php endif; ?></tr><?php endforeach; ?>
      <?php if (!$cache): ?><tr><td colspan="4" class="text-body-secondary">कैश ख़ाली है।</td></tr><?php endif; ?></tbody>
    </table></div>
    <div class="panel-body small text-body-secondary border-top">कैश अपने-आप साफ़ होता है जब ख़बर, मेनू, सेटिंग या होमपेज बदलता है। हाथ से साफ़ करने की ज़रूरत सिर्फ़ तब, जब फ़ाइलें सीधे सर्वर पर बदली हों।
      <?php if ($opcache): ?><br>OPcache: <?= $opcache['enabled'] ? 'चालू' : 'बंद' ?> · hit rate <?= e((string) $opcache['hit']) ?>% · <?= num($opcache['scripts']) ?> फ़ाइलें · <?= e(BS::size($opcache['mem'])) ?><?php endif; ?></div>
  </section></div>
  <div class="col-xl-5"><section class="panel h-100">
    <div class="panel-head"><h2><i class="fa-solid fa-broom me-2 text-body-secondary"></i>सफ़ाई</h2></div>
    <div class="panel-body">
      <?php if ($canManage): ?>
      <form method="post" action="<?= e(route('admin.system.cleanup')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <?php foreach (SYS::TASKS as $k => $l): ?><button class="btn btn-outline-secondary btn-sm text-start" name="task" value="<?= e($k) ?>" type="submit"<?= $k === 'optimize' ? ' data-confirm-then="सारी टेबल optimize होंगी; बड़े डेटाबेस पर कुछ सेकंड साइट धीमी हो सकती है।"' : '' ?>><i class="fa-solid fa-angle-right me-1"></i><?= e($l) ?></button><?php endforeach; ?>
        <button class="btn btn-brand btn-sm" name="task" value="all" type="submit"><i class="fa-solid fa-broom me-1"></i>ऊपर की सारी सफ़ाई (optimize छोड़कर)</button>
      </form>
      <?php endif; ?>
      <p class="small text-body-secondary mt-2 mb-0">रोज़ एक बार अपने-आप भी होती है (optimize छोड़कर)। ऑडिट लॉग <?= (int) setting('audit_retention_days', '365') ?: '∞' ?> दिन, लॉग फ़ाइलें <?= (int) setting('log_retention_days', '30') ?> दिन रखी जाती हैं।</p>
    </div>
  </section></div>
</div>

<section class="panel">
  <div class="panel-head"><h2><i class="fa-solid fa-gauge-high me-2 text-body-secondary"></i>धीमे अनुरोध</h2><span class="small text-body-secondary"><?= (int) setting('slow_request_ms', '1500') ?> ms से ज़्यादा · पिछले 7 दिन</span></div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>समय</th><th>पेज</th><th class="text-end">समय (ms)</th><th class="text-end">Query</th><th class="text-end d-none d-md-table-cell">DB (ms)</th><th class="text-end d-none d-md-table-cell">मेमोरी</th></tr></thead>
    <tbody><?php foreach ($slow as $s): ?><tr><td class="small text-nowrap"><?= e(date('d-m H:i', strtotime($s['t']))) ?></td><td class="small text-break"><?= e($s['m'] . ' ' . $s['p']) ?></td><td class="text-end fw-semibold"><?= num((int) $s['ms']) ?></td><td class="text-end"><?= num((int) $s['q']) ?></td><td class="text-end d-none d-md-table-cell"><?= num((int) $s['qms']) ?></td><td class="text-end d-none d-md-table-cell"><?= (int) $s['mem'] ?> MB</td></tr><?php endforeach; ?>
    <?php if (!$slow): ?><tr><td colspan="6" class="text-body-secondary">कोई धीमा अनुरोध नहीं। 👍</td></tr><?php endif; ?></tbody>
  </table></div>
</section>
