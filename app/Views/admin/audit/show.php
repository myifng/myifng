<?php $this->layout('layouts/admin'); $title = 'ऑडिट विवरण #' . $log['id']; $keys = array_unique(array_merge(array_keys($old), array_keys($new))); ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.audit.index')) ?>">ऑडिट लॉग</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $log['id'] ?></li></ol></nav>
    <h1><?= e($log['description'] ?: $log['action']) ?></h1>
    <p><?= hindi_date($log['created_at'], true) ?></p>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <section class="panel"><div class="panel-head"><h2>जानकारी</h2></div>
      <dl class="kv">
        <dt>यूज़र</dt><dd><?= e($log['user_name'] ?? 'सिस्टम') ?> (<?= e($log['role'] ?? '—') ?>)</dd>
        <dt>Action</dt><dd><span class="action-chip act-<?= e($log['action']) ?>"><?= e($log['action']) ?></span></dd>
        <dt>मॉड्यूल</dt><dd><?= e($log['module']) ?></dd>
        <dt>रिकॉर्ड</dt><dd><?= e($log['record_id'] ?? '—') ?></dd>
        <dt>IP</dt><dd><?= e($log['ip']) ?></dd>
        <dt>डिवाइस</dt><dd><?= e(device_name($log['user_agent'])) ?><small class="d-block text-body-secondary text-break"><?= e($log['user_agent']) ?></small></dd>
        <dt>पता</dt><dd class="text-break small"><?= e($log['url']) ?></dd>
      </dl>
    </section>
  </div>
  <div class="col-lg-7">
    <section class="panel"><div class="panel-head"><h2>बदलाव</h2></div>
      <?php if ($keys): ?>
      <div class="table-responsive"><table class="table mb-0 diff">
        <thead><tr><th>खाना</th><th>पहले</th><th>बाद में</th></tr></thead>
        <tbody><?php foreach ($keys as $k): ?><tr><th scope="row"><?= e($k) ?></th><td class="old"><?= e(is_scalar($old[$k] ?? null) ? $old[$k] : json_encode($old[$k] ?? null, JSON_UNESCAPED_UNICODE)) ?></td><td class="new"><?= e(is_scalar($new[$k] ?? null) ? $new[$k] : json_encode($new[$k] ?? null, JSON_UNESCAPED_UNICODE)) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><div class="empty-state">इस प्रविष्टि में कोई मान-बदलाव दर्ज नहीं है।</div><?php endif; ?>
    </section>
  </div>
</div>
