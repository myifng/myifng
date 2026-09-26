<?php $this->layout('layouts/admin'); $title = 'डैशबोर्ड'; ?>
<?php $this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop() ?>

<div class="page-head">
  <div>
    <h1>नमस्ते, <?= e(user('name')) ?></h1>
    <p><?= hindi_date(time(), false, true) ?> · <?= e(user('role_name')) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('users.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.users.create')) ?>"><i class="fa-solid fa-user-plus me-1"></i> नया यूज़र</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.profile')) ?>"><i class="fa-regular fa-user me-1"></i> मेरी प्रोफ़ाइल</a>
  </div>
</div>

<div class="stat-grid">
  <?php foreach ([
      ['कुल यूज़र', $stats['users'], 'fa-users', 'चालू: ' . num($stats['active']), 'admin.users.index', 'users.view'],
      ['रोल', $stats['roles'], 'fa-user-shield', 'अनुमति मैट्रिक्स के साथ', 'admin.roles.index', 'roles.view'],
      ['आज के लॉगिन', $stats['logins_today'], 'fa-right-to-bracket', 'सफल लॉगिन', null, null],
      ['असफल प्रयास (24 घंटे)', $stats['failed_24h'], 'fa-shield-halved', $stats['failed_24h'] > 10 ? 'ध्यान दें: ज़्यादा प्रयास' : 'सामान्य', 'admin.audit.index', 'audit.view'],
  ] as [$label, $value, $icon, $sub, $r, $perm]): ?>
    <div class="stat-card<?= $label === 'असफल प्रयास (24 घंटे)' && $value > 10 ? ' is-alert' : '' ?>">
      <div class="stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
      <div class="stat-body">
        <span class="stat-label"><?= e($label) ?></span>
        <span class="stat-value"><?= num($value) ?></span>
        <span class="stat-sub"><?= e($sub) ?></span>
      </div>
      <?php if ($r && can($perm)): ?><a class="stretched-link" href="<?= e(route($r)) ?>" aria-label="<?= e($label) ?> देखें"></a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2>पिछले 14 दिनों के लॉगिन</h2></div>
      <div class="panel-body">
        <div class="chart-box"><canvas aria-label="पिछले 14 दिनों के सफल और असफल लॉगिन" role="img" data-chart='<?= e(json_encode([
            'type' => 'bar',
            'labels' => $chart['labels'],
            'datasets' => [['label' => 'सफल', 'data' => $chart['ok'], 'color' => 'brand'], ['label' => 'असफल', 'data' => $chart['bad'], 'color' => 'muted']],
            'stacked' => true,
        ], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <section class="panel h-100">
      <div class="panel-head"><h2>रोल के हिसाब से यूज़र</h2></div>
      <div class="panel-body">
        <?php $max = max(1, ...array_map(fn($r) => (int) $r['c'], $roleSplit)); ?>
        <ul class="bar-list">
          <?php foreach ($roleSplit as $r): ?>
            <li><span class="bl-label"><?= e($r['name']) ?></span><span class="bl-track"><span style="width:<?= round($r['c'] / $max * 100) ?>%"></span></span><b><?= num($r['c']) ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>

  <?php if (can('audit.view')): ?>
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2>हाल की गतिविधि</h2><a class="btn btn-sm btn-light" href="<?= e(route('admin.audit.index')) ?>">सभी देखें</a></div>
      <?php if ($activity): ?>
      <ul class="activity">
        <?php foreach ($activity as $a): ?>
          <li>
            <?= avatar_html(null, $a['user_name'] ?? 'सिस्टम', 'sm') ?>
            <div><b><?= e($a['user_name'] ?? 'सिस्टम') ?></b> <?= e($a['description']) ?><small><?= e($a['module']) ?> · <?= time_ago($a['created_at']) ?> · <?= e($a['ip']) ?></small></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><div class="empty-state">अभी कोई गतिविधि दर्ज नहीं है।</div><?php endif; ?>
    </section>
  </div>
  <?php endif; ?>

  <div class="col-xl-4">
    <section class="panel h-100">
      <div class="panel-head"><h2>मेरे पिछले लॉगिन</h2></div>
      <ul class="activity">
        <?php foreach ($myLogins as $l): ?>
          <li><span class="avatar sm icon"><i class="fa-solid <?= $l['status'] === 'success' ? 'fa-right-to-bracket' : 'fa-shield-halved' ?>"></i></span>
            <div><?= status_badge($l['status']) ?> <?= e(device_name($l['user_agent'])) ?><small><?= hindi_date($l['created_at'], true) ?> · <?= e($l['ip']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>

  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>सिस्टम जानकारी</h2></div>
      <dl class="kv">
        <?php foreach ($system as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
      </dl>
    </section>
  </div>
  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>आने वाले मॉड्यूल</h2><span class="badge text-bg-light">Phase <?= (int) config('app.phase') ?> पूरा</span></div>
      <div class="roadmap">
        <?php foreach (array_slice($roadmap, 0, 4, true) as $ph => $mods): ?>
          <div class="rm-row"><span class="rm-phase">Phase <?= (int) $ph ?></span>
            <div class="rm-mods"><?php foreach ($mods as $m): ?><span><i class="fa-solid <?= e($m['icon']) ?>"></i> <?= e($m['label']) ?></span><?php endforeach; ?></div></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
