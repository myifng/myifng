<?php $this->layout('layouts/admin'); $title = 'ऑडिट लॉग'; ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">ऑडिट लॉग</li></ol></nav>
    <h1>ऑडिट लॉग</h1>
    <p>हर अहम बदलाव: किसने, कब, क्या और कहाँ से किया</p>
  </div>
  <?php if (can('audit.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.audit.export') . '?' . http_build_query(array_filter($filters))) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV</a><?php endif; ?>
</div>
<section class="panel">
  <form class="filter-bar" method="get">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="विवरण, यूज़र या IP" aria-label="खोजें"></div>
    <select class="form-select w-auto" name="user" aria-label="यूज़र"><option value="">सभी यूज़र</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $filters['user']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="module" aria-label="मॉड्यूल"><option value="">सभी मॉड्यूल</option><?php foreach ($modules as $m): ?><option<?= selected($m, $filters['module']) ?>><?= e($m) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="action" aria-label="Action"><option value="">सभी action</option><?php foreach ($actions as $a): ?><option<?= selected($a, $filters['action']) ?>><?= e($a) ?></option><?php endforeach; ?></select>
    <input class="form-control w-auto" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="से">
    <input class="form-control w-auto" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="तक">
    <button class="btn btn-dark" type="submit">फ़िल्टर</button>
    <?php if (array_filter($filters)): ?><a class="btn btn-link" href="<?= e(route('admin.audit.index')) ?>">साफ़ करें</a><?php endif; ?>
  </form>
  <?php if ($logs->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>समय</th><th>यूज़र</th><th>Action</th><th>विवरण</th><th>IP / डिवाइस</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($logs->items as $l): ?>
        <tr>
          <td class="text-nowrap small"><?= hindi_date($l['created_at'], true) ?></td>
          <td><b class="d-block"><?= e($l['user_name'] ?? 'सिस्टम') ?></b><span class="small text-body-secondary"><?= e($l['role']) ?></span></td>
          <td><span class="action-chip act-<?= e($l['action']) ?>"><?= e($l['action']) ?></span><span class="small text-body-secondary d-block"><?= e($l['module']) ?><?= $l['record_id'] ? ' #' . e($l['record_id']) : '' ?></span></td>
          <td class="small"><?= e($l['description']) ?></td>
          <td class="small text-nowrap"><?= e($l['ip']) ?><span class="d-block text-body-secondary"><?= e(device_name($l['user_agent'])) ?></span></td>
          <td class="text-end"><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.audit.show', ['id' => $l['id']])) ?>" title="विवरण" aria-label="विवरण"><i class="fa-solid fa-eye"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $logs]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-clipboard-list"></i><p>इन फ़िल्टर से कोई प्रविष्टि नहीं मिली।</p></div><?php endif; ?>
</section>
