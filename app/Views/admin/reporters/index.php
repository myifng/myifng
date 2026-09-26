<?php
use App\Models\Reporter;
$this->layout('layouts/admin');
$title = 'रिपोर्टर';
$q = fn(array $o) => '?' . http_build_query(array_filter(array_merge($filters, $o), fn($v) => $v !== '' && $v !== 0 && $v !== false));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">रिपोर्टर</li></ol></nav>
    <h1>रिपोर्टर</h1>
    <p>रिपोर्टर ID, वैधता, ब्यूरो और प्रदर्शन। सत्यापन पेज: <a href="<?= e(route('verify')) ?>" target="_blank" rel="noopener">/verify-reporter</a></p>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('reporters.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.reporters.export') . $q([])) ?>"><i class="fa-solid fa-file-csv me-1"></i> एक्सपोर्ट</a><?php endif; ?>
    <?php if (can('reporters.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.reporters.create')) ?>"><i class="fa-solid fa-user-plus me-1"></i> मौजूदा यूज़र को रिपोर्टर बनाएँ</a><?php endif; ?>
  </div>
</div>
<section class="panel">
  <div class="panel-tabs">
    <a href="<?= e($q(['status' => '', 'expiring' => '', 'page' => ''])) ?>" class="<?= !$filters['status'] && !$filters['expiring'] ? 'active' : '' ?>">सभी <span><?= num($counts[''] ?? 0) ?></span></a>
    <?php foreach (Reporter::STATUSES as $k => [$l]): ?><a href="<?= e($q(['status' => $k, 'expiring' => '', 'page' => ''])) ?>" class="<?= $filters['status'] === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k] ?? 0) ?></span></a><?php endforeach; ?>
    <a href="<?= e($q(['expiring' => 1, 'status' => '', 'page' => ''])) ?>" class="<?= $filters['expiring'] ? 'active' : '' ?>">30 दिन में वैधता ख़त्म <span><?= num($counts['expiring']) ?></span></a>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="नाम, रिपोर्टर ID, मोबाइल" aria-label="खोजें"></div>
    <select class="form-select w-auto" name="bureau" aria-label="ब्यूरो"><option value="">सभी ब्यूरो</option><?php foreach ($bureaus as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $filters['bureau']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark" type="submit">छाँटें</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>रिपोर्टर</th><th>पद / क्षेत्र</th><th>ब्यूरो</th><th>वैधता</th><th class="text-end">प्रकाशित</th><th>स्थिति</th></tr></thead>
      <tbody>
      <?php foreach ($items->items as $r): [$sl, $sc] = Reporter::STATUSES[$r['status']]; $soon = $r['status'] === 'active' && strtotime($r['valid_until']) <= strtotime('+30 days'); ?>
        <tr>
          <td><div class="d-flex align-items-center gap-2"><?= avatar_html($r['photo'], $r['name']) ?><div><a class="fw-bold text-reset" href="<?= e(route('admin.reporters.show', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><span class="d-block small font-monospace text-body-secondary"><?= e($r['reporter_code']) ?></span></div></div></td>
          <td class="small"><?= e($r['designation']) ?><span class="d-block text-body-secondary"><?= e($r['district'] ?? '—') ?></span></td>
          <td class="small"><?= e($r['bureau'] ?? '—') ?></td>
          <td class="small<?= $soon ? ' text-danger fw-bold' : '' ?>"><?= hindi_date($r['valid_until']) ?></td>
          <td class="text-end"><?= num($r['published']) ?></td>
          <td><span class="badge-status text-bg-<?= e($sc) ?>"><i class="dot"></i><?= e($sl) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-id-card"></i><p>कोई रिपोर्टर नहीं मिला। आवेदन मंज़ूर होने पर रिपोर्टर यहाँ दिखेंगे।</p></div><?php endif; ?>
</section>
