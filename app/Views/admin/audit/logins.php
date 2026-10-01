<?php
$this->layout('layouts/admin');
$title = 'लॉगिन इतिहास';
$labels = ['success' => ['सफल', 'success'], 'failed' => ['असफल', 'danger'], 'blocked' => ['रोका गया', 'warning'], 'logout' => ['लॉगआउट', 'secondary'], 'otp_sent' => ['OTP भेजा', 'info'], 'otp_failed' => ['ग़लत OTP', 'danger']];
$qs = http_build_query(array_filter($f));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.audit.index')) ?>">ऑडिट लॉग</a></li><li class="breadcrumb-item active" aria-current="page">लॉगिन इतिहास</li></ol></nav>
    <h1>लॉगिन इतिहास</h1>
    <p>पिछले 24 घंटे: <?php foreach ($labels as $k => [$l, $c]): ?><span class="badge text-bg-<?= $c ?> me-1"><?= e($l) ?> <?= num($sum[$k] ?? 0) ?></span><?php endforeach; ?></p>
  </div>
  <?php if (can('audit.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.audit.logins')) ?>?<?= e($qs . ($qs ? '&' : '') . 'export=csv') ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV</a><?php endif; ?>
</div>
<?= $this->insert('admin/system/_nav', ['active' => 'logins']) ?>
<form class="filter-bar mb-3" method="get"><div class="d-flex flex-wrap gap-2">
  <select class="form-select w-auto" name="user" aria-label="यूज़र"><option value="">सभी यूज़र</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $f['user']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
  <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी स्थितियाँ</option><?php foreach ($labels as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <input class="form-control w-auto" name="ip" value="<?= e($f['ip']) ?>" placeholder="IP" aria-label="IP" style="max-width:160px">
  <input class="form-control w-auto" type="date" name="from" value="<?= e($f['from']) ?>" aria-label="से">
  <input class="form-control w-auto" type="date" name="to" value="<?= e($f['to']) ?>" aria-label="तक">
  <button class="btn btn-outline-secondary" type="submit">छाँटें</button>
</div></form>
<section class="panel"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>समय</th><th>यूज़र</th><th>स्थिति</th><th class="d-none d-md-table-cell">IP</th><th class="d-none d-md-table-cell">डिवाइस</th></tr></thead>
  <tbody><?php foreach ($items->items as $h): [$l, $c] = $labels[$h['status']] ?? [$h['status'], 'light']; ?>
    <tr><td class="small text-nowrap"><?= hindi_date($h['created_at'], true) ?></td>
      <td><?= e((string) ($h['name'] ?? '#' . $h['user_id'])) ?><div class="small text-body-secondary"><?= e((string) $h['role']) ?></div></td>
      <td><span class="badge text-bg-<?= $c ?>"><?= e($l) ?></span></td>
      <td class="d-none d-md-table-cell small font-monospace"><a href="?ip=<?= e(rawurlencode((string) $h['ip'])) ?>"><?= e((string) $h['ip']) ?></a></td>
      <td class="d-none d-md-table-cell small"><?= e(device_name($h['user_agent'])) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$items->items): ?><tr><td colspan="5" class="text-body-secondary p-4">कोई प्रविष्टि नहीं।</td></tr><?php endif; ?></tbody>
</table></div></section>
<?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
