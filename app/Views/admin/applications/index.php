<?php
use App\Models\Reporter;
use App\Models\ReporterApplication;
$this->layout('layouts/admin');
$title = 'रिपोर्टर आवेदन';
$q = fn(array $o) => '?' . http_build_query(array_filter(array_merge($filters, $o), fn($v) => $v !== '' && $v !== 0 && $v !== false));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">रिपोर्टर आवेदन</li></ol></nav>
    <h1>रिपोर्टर आवेदन</h1>
    <p>वेबसाइट के <a href="<?= e(route('join')) ?>" target="_blank" rel="noopener">/join-as-reporter</a> फ़ॉर्म से आए आवेदन।</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e($q(['mine' => $filters['mine'] ? '' : 1, 'page' => ''])) ?>"><?= $filters['mine'] ? 'सभी' : 'मुझे सौंपे गए' ?></a>
    <?php if (can('applications.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.applications.export') . $q([])) ?>"><i class="fa-solid fa-file-csv me-1"></i> एक्सपोर्ट</a><?php endif; ?>
  </div>
</div>
<section class="panel">
  <div class="panel-tabs">
    <?php foreach (['open' => 'खुले', '' => 'सभी'] + array_map(fn($s) => $s[0], ReporterApplication::STATUSES) as $k => $l): ?>
      <a href="<?= e($q(['status' => $k, 'page' => ''])) ?>" class="<?= $filters['status'] === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="आवेदन संख्या, नाम, मोबाइल, ईमेल" aria-label="खोजें"></div>
    <select class="form-select w-auto" name="district" aria-label="ज़िला"><option value="">सभी ज़िले</option><?php foreach ($districts as $d): ?><option value="<?= (int) $d['id'] ?>"<?= selected($d['id'], $filters['district']) ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="type" aria-label="प्रकार"><option value="">सभी प्रकार</option><?php foreach (Reporter::TYPES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $filters['type']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark" type="submit">छाँटें</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>आवेदन</th><th>आवेदक</th><th>क्षेत्र</th><th>प्रकार</th><th>स्थिति</th><th>जाँच</th><th class="text-end"></th></tr></thead>
      <tbody>
      <?php foreach ($items->items as $a): [$sl, $sc] = ReporterApplication::STATUSES[$a['status']]; ?>
        <tr>
          <td><a class="fw-bold font-monospace" href="<?= e(route('admin.applications.show', ['id' => $a['id']])) ?>"><?= e($a['app_no']) ?></a><span class="d-block small text-body-secondary"><?= time_ago($a['created_at']) ?></span></td>
          <td><?= e($a['full_name']) ?><span class="d-block small text-body-secondary"><?= e($a['mobile']) ?></span></td>
          <td class="small"><?= e($a['district'] ?? '—') ?><span class="d-block text-body-secondary"><?= e($a['state'] ?? '') ?></span></td>
          <td class="small"><?= e(Reporter::TYPES[$a['reporter_type']] ?? $a['reporter_type']) ?><span class="d-block text-body-secondary"><?= (int) $a['experience_years'] ?> साल</span></td>
          <td><span class="badge-status text-bg-<?= e($sc) ?>"><i class="dot"></i><?= e($sl) ?></span></td>
          <td class="small"><?= e($a['assignee'] ?? '—') ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.applications.show', ['id' => $a['id']])) ?>">देखें</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-file-signature"></i><p>इस सूची में कोई आवेदन नहीं है।</p></div>
  <?php endif; ?>
</section>
