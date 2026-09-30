<?php
use App\Models\Reader;
$this->layout('layouts/admin');
$title = 'पाठक खाते';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">पाठक</li></ol></nav>
    <h1>पाठक खाते</h1><p>वेबसाइट पर रजिस्टर पाठक (स्टाफ़ से अलग)।</p>
  </div>
  <?php if (can('readers.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.readers.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a><?php endif; ?>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([['कुल', $stats['t'] ?? 0, 'fa-users'], ['चालू', $stats['a'] ?? 0, 'fa-user-check'], ['30 दिन में नए', $stats['n'] ?? 0, 'fa-user-plus'], ['7 दिन में लॉगिन', $stats['w'] ?? 0, 'fa-right-to-bracket']] as [$l, $v, $ic]): ?>
    <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= num($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी</option><?php foreach (Reader::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="नाम, ईमेल या मोबाइल" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>पाठक</th><th>शहर</th><th>फ़ॉलो / सेव / टिप्पणी</th><th>स्थिति</th><th>जुड़े</th></tr></thead>
    <tbody><?php foreach ($items->items as $r): ?><tr class="<?= $r['status'] === 'blocked' ? 'is-off' : '' ?>">
      <td><a class="fw-semibold text-reset" href="<?= e(route('admin.readers.show', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><?= $r['trusted'] ? ' <i class="fa-solid fa-shield-heart text-success" title="भरोसेमंद"></i>' : '' ?><div class="small text-body-secondary text-break"><?= e($r['email']) ?></div></td>
      <td class="small"><?= e((string) $r['city']) ?></td>
      <td class="small"><?= num($r['follows']) ?> / <?= num($r['saved']) ?> / <?= num($r['comments']) ?></td>
      <td><span class="badge text-bg-<?= ['pending' => 'warning', 'active' => 'success', 'blocked' => 'dark'][$r['status']] ?>"><?= e(Reader::STATUSES[$r['status']]) ?></span></td>
      <td class="small text-nowrap"><?= e(hindi_date($r['created_at'])) ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-user-group"></i><p>कोई पाठक नहीं।</p></div><?php endif; ?>
</section>
