<?php $this->layout('layouts/admin'); $title = 'डैशबोर्ड'; ?>
<?php $this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop() ?>

<?php if ($pending): ?>
  <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span><i class="fa-solid fa-circle-arrow-up me-1"></i> सिस्टम अपडेट उपलब्ध है: <?= count($pending) ?> नई migration (<?= e(implode(', ', $pending)) ?>)। अपडेट से पहले डेटाबेस का बैकअप ले लें।</span>
    <form method="post" action="<?= e(route('admin.system.migrate')) ?>" data-confirm="डेटाबेस अपडेट चलेगा। क्या आपने बैकअप ले लिया है?"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">अभी अपडेट करें</button></form>
  </div>
<?php endif; ?>
<?php if (can('system.manage') && ($demoCount = \App\Services\DemoService::count(db())) > 0): ?>
  <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <span><i class="fa-solid fa-flask me-1"></i> साइट पर <b>डेमो (नमूना) डेटा</b> है (<?= num($demoCount) ?> चीज़ें)। लाइव करने से पहले इसे हटा दें।</span>
    <a class="btn btn-sm btn-outline-dark" href="<?= e(route('admin.system.index')) ?>">सिस्टम पेज पर हटाएँ</a>
  </div>
<?php endif; ?>

<div class="page-head">
  <div>
    <h1>नमस्ते, <?= e(user('name')) ?></h1>
    <p><?= hindi_date(time(), false, true) ?> · <?= e(user('role_name')) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('news.create') && app('router')->has('admin.news.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.news.create')) ?>"><i class="fa-solid fa-pen-nib me-1"></i> नई ख़बर</a><?php endif; ?>
    <?php if (can('pages.create')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.pages.create')) ?>"><i class="fa-solid fa-file-circle-plus me-1"></i> नया पेज</a><?php endif; ?>
    <?php if (can('homepage.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.homepage')) ?>"><i class="fa-solid fa-table-cells-large me-1"></i> होमपेज</a><?php endif; ?>
  </div>
</div>

<?php if ($cards): ?>
<div class="stat-grid">
  <?php foreach ($cards as $c): ?>
    <div class="stat-card<?= !empty($c['alert']) ? ' is-alert' : '' ?>">
      <div class="stat-icon"><i class="fa-solid <?= e($c['icon']) ?>"></i></div>
      <div class="stat-body"><span class="stat-label"><?= e($c['label']) ?></span><span class="stat-value"><?= num($c['value']) ?></span><span class="stat-sub"><?= e($c['sub'] ?? '') ?></span></div>
      <?php if ($c['link']): ?><a class="stretched-link" href="<?= e($c['link']) ?>" aria-label="<?= e($c['label']) ?> देखें"></a><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2>पिछले 14 दिनों के लॉगिन</h2></div>
      <div class="panel-body"><div class="chart-box"><canvas role="img" aria-label="पिछले 14 दिनों के सफल और असफल लॉगिन" data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => $loginChart['labels'], 'stacked' => true, 'datasets' => [['label' => 'सफल', 'data' => $loginChart['a'], 'color' => 'brand'], ['label' => 'असफल', 'data' => $loginChart['b'], 'color' => 'muted']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div>
    </section>
  </div>
  <div class="col-xl-4">
    <?php if ($checklist):
        $done = count(array_filter($checklist, fn($c) => $c[1])); ?>
    <section class="panel h-100">
      <div class="panel-head"><h2>सेटअप चेकलिस्ट</h2><span class="badge <?= $done === count($checklist) ? 'text-bg-success' : 'text-bg-light' ?>"><?= $done ?>/<?= count($checklist) ?></span></div>
      <div class="px-3 pt-3"><div class="progress" role="progressbar" aria-label="सेटअप पूरा" aria-valuenow="<?= $done ?>" aria-valuemin="0" aria-valuemax="<?= count($checklist) ?>" style="height:6px"><div class="progress-bar bg-success" style="width:<?= round($done / count($checklist) * 100) ?>%"></div></div></div>
      <ul class="checklist">
        <?php foreach ($checklist as [$label, $ok, $link, $hint]): ?>
          <li class="<?= $ok ? 'ok' : '' ?>"><i class="fa-<?= $ok ? 'solid fa-circle-check' : 'regular fa-circle' ?>"></i>
            <div><?php if (!$ok && $link): ?><a href="<?= e($link) ?>"><?= e($label) ?></a><?php else: ?><span><?= e($label) ?></span><?php endif; ?><small><?= e($hint) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </div>

  <?php if ($activityChart): ?>
  <div class="col-xl-4">
    <section class="panel h-100">
      <div class="panel-head"><h2>सिस्टम में गतिविधि (14 दिन)</h2></div>
      <div class="panel-body"><div class="chart-box sm"><canvas role="img" aria-label="पिछले 14 दिनों में ऑडिट लॉग की प्रविष्टियाँ" data-chart='<?= e(json_encode(['type' => 'line', 'labels' => $activityChart['labels'], 'legend' => false, 'datasets' => [['label' => 'गतिविधियाँ', 'data' => $activityChart['a'], 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>'></canvas></div></div>
    </section>
  </div>
  <?php endif; ?>

  <?php if ($activity): ?>
  <div class="col-xl-8">
    <section class="panel h-100">
      <div class="panel-head"><h2>हाल की गतिविधि</h2><a class="btn btn-sm btn-light" href="<?= e(route('admin.audit.index')) ?>">सभी देखें</a></div>
      <ul class="activity">
        <?php foreach ($activity as $a): ?>
          <li><?= avatar_html(null, $a['user_name'] ?? 'सिस्टम', 'sm') ?>
            <div><b><?= e($a['user_name'] ?? 'सिस्टम') ?></b> <?= e($a['description']) ?><small><?= e($a['module']) ?> · <?= time_ago($a['created_at']) ?> · <?= e($a['ip']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
  <?php endif; ?>

  <?php if ($recentPages): ?>
  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>हाल में बदले पेज</h2><a class="btn btn-sm btn-light" href="<?= e(route('admin.pages.index')) ?>">सभी पेज</a></div>
      <ul class="activity">
        <?php foreach ($recentPages as $p): ?>
          <li><span class="avatar sm icon"><i class="fa-regular fa-file-lines"></i></span>
            <div><?= can('pages.edit') ? '<a href="' . e(route('admin.pages.edit', ['id' => $p['id']])) . '">' . e($p['title']) . '</a>' : e($p['title']) ?> <?= $p['status'] === 'published' ? status_badge('active') : '<span class="badge-status text-bg-secondary"><i class="dot"></i>ड्राफ़्ट</span>' ?><small><?= time_ago($p['updated_at']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
  <?php endif; ?>

  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>मेरे पिछले लॉगिन</h2><a class="btn btn-sm btn-light" href="<?= e(route('admin.profile')) ?>">प्रोफ़ाइल</a></div>
      <ul class="activity compact">
        <?php foreach ($myLogins as $l): ?><li><div><?= status_badge($l['status']) ?> <?= e(device_name($l['user_agent'])) ?><small><?= hindi_date($l['created_at'], true) ?> · <?= e($l['ip']) ?></small></div></li><?php endforeach; ?>
      </ul>
    </section>
  </div>

  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>सिस्टम जानकारी</h2></div>
      <dl class="kv"><?php foreach ($system as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
    </section>
  </div>
  <div class="col-xl-6">
    <section class="panel h-100">
      <div class="panel-head"><h2>आने वाले मॉड्यूल</h2><span class="badge text-bg-light">Phase <?= (int) config('app.phase') ?> पूरा</span></div>
      <div class="roadmap">
        <?php foreach (array_slice($roadmap, 0, 4, true) as $ph => $mods): ?>
          <div class="rm-row"><span class="rm-phase">Phase <?= (int) $ph ?></span><div class="rm-mods"><?php foreach ($mods as $m): ?><span><i class="fa-solid <?= e($m['icon']) ?>"></i> <?= e($m['label']) ?></span><?php endforeach; ?></div></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
