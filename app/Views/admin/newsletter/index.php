<?php
use App\Models\NewsletterCampaign as NC;
$this->layout('layouts/admin');
$title = 'न्यूज़लेटर';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">न्यूज़लेटर</li></ol></nav>
    <h1>न्यूज़लेटर</h1>
    <p>कैंपेन बनाएँ, टेस्ट करें और सब्सक्राइबर को भेजें। ईमेल कतार से हर मिनट <?= e(setting('newsletter_batch', '30')) ?> जाते हैं।</p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($queued && can('newsletter.manage')): ?><form method="post" action="<?= e(route('admin.newsletter.process')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-play me-1"></i> अभी भेजें (<?= num($queued) ?> कतार में)</button></form><?php endif; ?>
    <?php if (can('newsletter.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.newsletter.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया कैंपेन</a><?php endif; ?>
  </div>
</div>
<?= $this->insert('admin/newsletter/_nav', ['active' => 'campaigns']) ?>
<div class="row g-3 mb-3">
  <?php foreach ([['सब्सक्राइब्ड', $stats['s'] ?? 0, 'fa-user-check'], ['पुष्टि बाकी', $stats['p'] ?? 0, 'fa-hourglass-half'], ['30 दिन में नए', $stats['n'] ?? 0, 'fa-arrow-trend-up'], ['अनसब्सक्राइब', $stats['u'] ?? 0, 'fa-user-xmark']] as [$l, $v, $ic]): ?>
    <div class="col-6 col-lg-3"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= num($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<section class="panel">
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>विषय</th><th>स्थिति</th><th>भेजे</th><th>समय</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items->items as $c): $editable = in_array($c['status'], ['draft', 'scheduled'], true); ?><tr>
      <td><a class="fw-semibold text-reset" href="<?= e(route($editable ? 'admin.newsletter.edit' : 'admin.newsletter.show', ['id' => $c['id']])) ?>"><?= e($c['subject']) ?></a></td>
      <td><span class="badge text-bg-<?= NC::BADGE[$c['status']] ?>"><?= e(NC::STATUSES[$c['status']]) ?></span></td>
      <td class="small"><?= $c['recipients'] ? num($c['sent']) . ' / ' . num($c['recipients']) . ($c['failed'] ? ' <span class="text-danger">(' . num($c['failed']) . ' विफल)</span>' : '') : '—' ?></td>
      <td class="small"><?= $c['status'] === 'scheduled' ? 'तय: ' . e(hindi_date($c['scheduled_at'], true)) : ($c['finished_at'] ? e(hindi_date($c['finished_at'], true)) : e(hindi_date($c['created_at'], true))) ?></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.newsletter.show', ['id' => $c['id']])) ?>">देखें</a>
        <?php if ($editable && can('newsletter.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.newsletter.edit', ['id' => $c['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-paper-plane"></i><p>अभी कोई कैंपेन नहीं।</p></div><?php endif; ?>
</section>
