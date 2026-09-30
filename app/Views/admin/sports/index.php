<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$title = 'खेल केंद्र';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">खेल केंद्र</li></ol></nav>
    <h1>खेल केंद्र</h1>
    <p>मैच, लाइव स्कोर और कमेंट्री, पॉइंट्स टेबल।</p>
  </div>
  <?php if (can('sports.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.sports.matches.create') . ($tour ? '?tournament=' . $tour : '')) ?>"><i class="fa-solid fa-plus me-1"></i> नया मैच</a><?php endif; ?>
</div>
<?= $this->insert('admin/sports/_nav', ['active' => 'matches']) ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
  <div class="btn-group btn-group-sm" role="group" aria-label="मैच">
    <?php foreach (['live' => 'लाइव', 'upcoming' => 'आने वाले', 'recent' => 'पूरे', 'all' => 'सभी'] as $k => $l): ?>
      <a class="btn <?= $tab === $k ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?tab=<?= $k ?><?= $tour ? '&tournament=' . $tour : '' ?>"><?= e($l) ?> <span class="badge text-bg-light"><?= num($counts[$k === 'all' ? 'total' : $k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="<?= e($tab) ?>">
    <select class="form-select form-select-sm" name="tournament" aria-label="टूर्नामेंट" onchange="this.form.submit()"><option value="">सभी टूर्नामेंट</option><?php foreach ($tournaments as $t): ?><option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $tour) ?>><?= e($t['name']) ?></option><?php endforeach; ?></select>
  </form>
</div>
<section class="panel">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>मैच</th><th>स्कोर</th><th class="d-none d-md-table-cell">समय</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody>
    <?php foreach ($items as $m): [$sl, $sc] = SS::MATCH_STATUSES[$m['status']]; ?>
      <tr>
        <td><a class="fw-semibold text-reset" href="<?= e(route('admin.sports.console', ['id' => $m['id']])) ?>"><?= e(($m['team1_short'] ?? '?') . ' बनाम ' . ($m['team2_short'] ?? '?')) ?></a>
          <div class="small text-body-secondary"><i class="fa-solid <?= e(SS::SPORTS[$m['sport']][1] ?? 'fa-trophy') ?>"></i> <?= e(trim(($m['tournament'] ?? '') . ($m['title'] ? ' · ' . $m['title'] : ''), ' ·')) ?></div></td>
        <td class="small"><?= e((string) $m['score1']) ?><?= $m['score2'] ? '<br>' . e($m['score2']) : '' ?></td>
        <td class="d-none d-md-table-cell small text-nowrap"><?= hindi_date($m['start_at'], true) ?></td>
        <td><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.sports.console', ['id' => $m['id']])) ?>"><i class="fa-solid fa-tower-broadcast me-1"></i>कंसोल</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-trophy"></i><p>इस सूची में कोई मैच नहीं।</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
