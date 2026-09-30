<?php
use App\Services\FactCheckService as FC;
$this->layout('layouts/admin');
$title = 'फ़ैक्ट चेक';
$qs = static fn(array $o) => '?' . http_build_query(array_filter($o + ['status' => $status, 'verdict' => $verdict, 'q' => $q]));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">फ़ैक्ट चेक</li></ol></nav>
    <h1>फ़ैक्ट चेक</h1>
    <p>वायरल दावों की जाँच: दावा, सबूत, स्रोत, व्याख्या और फ़ैसला।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(route('factcheck.index')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर</a>
    <?php if (can('fact_checks.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.fact_checks.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नई फ़ैक्ट चेक</a><?php endif; ?>
  </div>
</div>
<nav class="sub-tabs mb-3" aria-label="स्थिति">
  <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(route('admin.fact_checks.index') . $qs(['status' => null])) ?>">सभी <span class="badge text-bg-light border"><?= num(array_sum($counts)) ?></span></a>
  <?php foreach (FC::STATUSES as $k => [$l]): ?><a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(route('admin.fact_checks.index') . $qs(['status' => $k])) ?>"><?= e($l) ?> <span class="badge text-bg-light border"><?= num($counts[$k] ?? 0) ?></span></a><?php endforeach; ?>
</nav>
<form class="filter-bar mb-3" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <div class="row g-2">
    <div class="col-md-6"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="शीर्षक या दावा खोजें" aria-label="खोजें"></div>
    <div class="col-8 col-md-4"><select class="form-select" name="verdict" aria-label="फ़ैसला"><option value="">सभी फ़ैसले</option><?php foreach (FC::VERDICTS as $k => [$l]): ?><option value="<?= e($k) ?>"<?= selected($k, $verdict) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-4 col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">खोजें</button></div>
  </div>
</form>
<section class="panel">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>दावा / शीर्षक</th><th>फ़ैसला</th><th class="d-none d-md-table-cell">स्थिति</th><th class="d-none d-lg-table-cell">लेखक</th><th class="d-none d-md-table-cell">अपडेट</th><th class="text-end">काम</th></tr></thead>
    <tbody>
    <?php foreach ($items->items as $f): [$sl, $sc] = FC::STATUSES[$f['status']]; ?>
      <tr>
        <td><a class="fw-semibold text-reset" href="<?= e(route('admin.fact_checks.edit', ['id' => $f['id']])) ?>"><?= e($f['title']) ?></a>
          <div class="small text-body-secondary text-truncate" style="max-width:460px">दावा: <?= e(mb_substr($f['claim'], 0, 140)) ?></div></td>
        <td><?= FC::badge($f['verdict'], 'sm') ?></td>
        <td class="d-none d-md-table-cell"><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="d-none d-lg-table-cell small"><?= e((string) $f['author']) ?></td>
        <td class="d-none d-md-table-cell small text-nowrap"><?= time_ago($f['updated_at']) ?><?= $f['status'] === 'published' ? '<span class="d-block text-body-secondary">' . num((int) $f['views']) . ' व्यूज़</span>' : '' ?></td>
        <td class="text-end text-nowrap">
          <?php if ($f['status'] === 'published'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(FC::url($f)) ?>" target="_blank" rel="noopener" title="देखें" aria-label="देखें"><i class="fa-solid fa-eye"></i></a><?php endif; ?>
          <?php if (can('fact_checks.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.fact_checks.edit', ['id' => $f['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items->items): ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>कोई फ़ैक्ट चेक नहीं।</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
