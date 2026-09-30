<?php
use App\Models\Poll;
$this->layout('layouts/admin');
$title = 'पोल';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">पोल</li></ol></nav>
    <h1>पोल</h1>
    <p>ख़बर/पेज में <code>[poll:ID]</code> लिखें या होमपेज बिल्डर का "पोल" ब्लॉक लगाएँ।</p>
  </div>
  <?php if (can('polls.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.polls.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया पोल</a><?php endif; ?>
</div>
<section class="panel">
  <form class="filter-bar" method="get"><select class="form-select w-auto" name="status" aria-label="स्थिति" onchange="this.form.submit()"><option value="">सभी</option><?php foreach (Poll::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>सवाल</th><th>शॉर्टकोड</th><th>मतदाता</th><th>समय</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items->items as $p): ?><tr>
      <td><a class="fw-semibold text-reset" href="<?= e(route('admin.polls.edit', ['id' => $p['id']])) ?>"><?= e($p['question']) ?></a><div class="small text-body-secondary"><?= (int) $p['opts'] ?> विकल्प<?= $p['multiple'] ? ' · कई चुन सकते' : '' ?><?= $p['require_login'] ? ' · लॉगिन ज़रूरी' : '' ?></div></td>
      <td><code>[poll:<?= (int) $p['id'] ?>]</code></td>
      <td><?= num($p['voters']) ?></td>
      <td class="small"><?= $p['start_at'] ? e(hindi_date($p['start_at'], true)) : 'अभी से' ?><br><?= $p['end_at'] ? '→ ' . e(hindi_date($p['end_at'], true)) : 'बिना अंत' ?></td>
      <td><span class="badge text-bg-<?= ['draft' => 'secondary', 'active' => 'success', 'closed' => 'dark'][$p['status']] ?>"><?= e(Poll::STATUSES[$p['status']]) ?></span></td>
      <td class="text-end text-nowrap">
        <?php if ($p['status'] !== 'draft'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('poll', ['id' => $p['id']])) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.polls.export', ['id' => $p['id']])) ?>" aria-label="नतीजे CSV"><i class="fa-solid fa-file-csv"></i></a>
        <?php if (can('polls.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.polls.edit', ['id' => $p['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
      </td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-square-poll-vertical"></i><p>अभी कोई पोल नहीं।</p></div><?php endif; ?>
</section>
