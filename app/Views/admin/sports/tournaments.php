<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$title = 'टूर्नामेंट';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.index')) ?>">खेल केंद्र</a></li><li class="breadcrumb-item active" aria-current="page">टूर्नामेंट</li></ol></nav>
    <h1>टूर्नामेंट और सीरीज़</h1>
  </div>
  <?php if (can('sports.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.sports.tournaments.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया टूर्नामेंट</a><?php endif; ?>
</div>
<?= $this->insert('admin/sports/_nav', ['active' => 'tournaments']) ?>
<section class="panel"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>टूर्नामेंट</th><th>खेल</th><th class="d-none d-md-table-cell">तारीख़ें</th><th>स्थिति</th><th class="text-end">मैच</th><th class="text-end">काम</th></tr></thead>
  <tbody><?php foreach ($items as $t): ?>
    <tr><td><a class="fw-semibold text-reset" href="<?= e(route('admin.sports.tournaments.edit', ['id' => $t['id']])) ?>"><?= e($t['name']) ?></a><?= $t['season'] ? ' <small class="text-body-secondary">' . e($t['season']) . '</small>' : '' ?><?= $t['is_featured'] ? ' <i class="fa-solid fa-star text-warning" title="मुख्य"></i>' : '' ?></td>
      <td><i class="fa-solid <?= e(SS::SPORTS[$t['sport']][1]) ?>"></i> <?= e(SS::SPORTS[$t['sport']][0]) ?></td>
      <td class="d-none d-md-table-cell small"><?= $t['start_date'] ? hindi_date($t['start_date']) : '—' ?><?= $t['end_date'] ? ' – ' . hindi_date($t['end_date']) : '' ?></td>
      <td><span class="badge text-bg-light border"><?= e(SS::TOUR_STATUSES[$t['status']]) ?></span></td>
      <td class="text-end"><a href="<?= e(route('admin.sports.index')) ?>?tab=all&amp;tournament=<?= (int) $t['id'] ?>"><?= num((int) $t['matches']) ?></a></td>
      <td class="text-end"><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.sports.tournaments.edit', ['id' => $t['id']])) ?>" title="बदलें / पॉइंट्स टेबल" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a></td></tr>
  <?php endforeach; ?>
  <?php if (!$items): ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-trophy"></i><p>कोई टूर्नामेंट नहीं।</p></div></td></tr><?php endif; ?></tbody>
</table></div></section>
