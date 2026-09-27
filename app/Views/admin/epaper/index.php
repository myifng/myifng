<?php
use App\Models\EpaperIssue;
$this->layout('layouts/admin');
$title = 'ई-पेपर';
$state = fn($i) => $i['status'] !== 'published' ? ['secondary', 'ड्राफ़्ट'] : (strtotime((string) $i['publish_at']) > time() ? ['info', 'शेड्यूल'] : ['success', 'प्रकाशित']);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">ई-पेपर</li></ol></nav>
    <h1>ई-पेपर</h1>
    <p>हर संस्करण का रोज़ का अंक: PDF अपलोड करें, पेज अपने आप बनते हैं। पाठक <a href="<?= e(route('epaper')) ?>" target="_blank" rel="noopener">/epaper</a> पर पढ़ते हैं।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('epaper.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.epaper.editions')) ?>"><i class="fa-solid fa-layer-group me-1"></i> संस्करण</a><?php endif; ?>
    <?php if (can('epaper.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.epaper.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया अंक</a><?php endif; ?>
  </div>
</div>

<?php if ($today): ?>
<section class="panel mb-3">
  <div class="panel-head"><h2><i class="fa-regular fa-calendar-check me-2 text-body-secondary"></i>आज (<?= hindi_date(date('Y-m-d')) ?>)</h2></div>
  <div class="ep-today">
    <?php foreach ($today as $t): ?>
      <div class="ep-today-item">
        <b><?= e($t['name']) ?></b>
        <?php if (!$t['issue_id']): ?>
          <span class="badge text-bg-warning">अंक नहीं बना</span>
          <?php if (can('epaper.create')): ?><a class="btn btn-sm btn-brand" href="<?= e(route('admin.epaper.create')) ?>?edition=<?= (int) $t['id'] ?>&date=<?= e(date('Y-m-d')) ?>">बनाएँ</a><?php endif; ?>
        <?php else: [$c, $l] = $state($t); ?>
          <span class="badge-status text-bg-<?= e($c) ?>"><i class="dot"></i><?= e($l) ?></span><span class="small text-body-secondary"><?= num($t['page_count']) ?> पेज</span>
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.epaper.edit', ['id' => $t['issue_id']])) ?>">खोलें</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="edition" aria-label="संस्करण"><option value="">सभी संस्करण</option><?php foreach ($editions as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $edition) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <input class="form-control w-auto" type="month" name="month" value="<?= e($month) ?>" aria-label="महीना">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><?php foreach (['' => 'सभी स्थिति', 'published' => 'प्रकाशित', 'scheduled' => 'शेड्यूल', 'draft' => 'ड्राफ़्ट'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark" type="submit">दिखाएँ</button>
  </form>
  <?php if ($items->items): ?>
  <div class="ep-grid">
    <?php foreach ($items->items as $i): [$c, $l] = $state($i); ?>
      <a class="ep-card" href="<?= e(route('admin.epaper.edit', ['id' => $i['id']])) ?>">
        <span class="ep-cover"><?= $i['cover'] ? '<img src="' . e(upload_url($i['cover'])) . '" alt="" loading="lazy">' : '<i class="fa-regular fa-newspaper"></i>' ?></span>
        <span class="ep-meta"><b><?= hindi_date($i['issue_date'], false, true) ?></b><span><?= e($i['edition']) ?></span>
          <span><span class="badge-status text-bg-<?= e($c) ?>"><i class="dot"></i><?= e($l) ?></span> <?= num($i['page_count']) ?> पेज<?= $i['access'] === 'premium' ? ' · <i class="fa-solid fa-crown text-warning"></i>' : '' ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-regular fa-newspaper"></i><p>कोई अंक नहीं मिला।</p></div>
  <?php endif; ?>
</section>
