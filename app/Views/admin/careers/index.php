<?php
use App\Controllers\Admin\JobController as JC;
$this->layout('layouts/admin');
$title = 'करियर';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">करियर</li></ol></nav>
    <h1>करियर / इंटर्नशिप</h1><p>खुली वैकेंसी वेबसाइट पर <a href="<?= e(route('careers')) ?>" target="_blank" rel="noopener">/careers</a> में दिखती हैं; अंतिम तारीख़ के बाद आवेदन अपने आप बंद।</p>
  </div>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(route('admin.inbox', ['type' => 'career'])) ?>"><i class="fa-solid fa-inbox me-1"></i> सभी आवेदन</a>
    <?php if (can('careers.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.careers.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नई वैकेंसी</a><?php endif; ?></div>
</div>
<section class="panel">
  <?php if ($items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>पद</th><th>प्रकार</th><th>अंतिम तारीख़</th><th>आवेदन</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items as $j): $expired = $j['deadline'] && $j['deadline'] < date('Y-m-d'); ?><tr class="<?= $j['status'] !== 'open' || $expired ? 'is-off' : '' ?>">
      <td><a class="fw-semibold text-reset" href="<?= e(route('admin.careers.edit', ['id' => $j['id']])) ?>"><?= e($j['title']) ?></a><div class="small text-body-secondary"><?= e(implode(' · ', array_filter([$j['department'], $j['location']]))) ?></div></td>
      <td class="small"><?= e(JC::TYPES[$j['job_type']]) ?></td>
      <td class="small"><?= $j['deadline'] ? e(hindi_date($j['deadline'])) . ($expired ? ' <span class="badge text-bg-secondary">निकल गई</span>' : '') : '—' ?></td>
      <td><a href="<?= e(route('admin.inbox', ['type' => 'career'])) ?>?status=all&amp;job=<?= (int) $j['id'] ?>"><?= num($j['apps']) ?></a><?= $j['fresh'] ? ' <span class="badge text-bg-warning">' . num($j['fresh']) . ' नए</span>' : '' ?></td>
      <td><span class="badge text-bg-<?= ['draft' => 'secondary', 'open' => 'success', 'closed' => 'dark'][$j['status']] ?>"><?= e(JC::STATUSES[$j['status']]) ?></span></td>
      <td class="text-end text-nowrap"><?php if ($j['status'] !== 'draft'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('careers.show', ['slug' => $j['slug']])) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        <?php if (can('careers.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.careers.edit', ['id' => $j['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-briefcase"></i><p>अभी कोई वैकेंसी नहीं।</p></div><?php endif; ?>
</section>
