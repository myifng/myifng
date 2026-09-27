<?php
use App\Models\EpaperEdition;
$this->layout('layouts/admin');
$title = 'ई-पेपर संस्करण';
$e = $edit;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.epaper.index')) ?>">ई-पेपर</a></li><li class="breadcrumb-item active" aria-current="page">संस्करण</li></ol></nav>
    <h1>संस्करण</h1>
    <p>मुख्य, राज्य, ज़िला और शहर संस्करण। पाठक रीडर में संस्करण बदल सकते हैं।</p>
  </div>
</div>
<div class="row g-3">
  <div class="<?= $e ? 'col-xl-7' : 'col-xl-4 order-xl-2' ?>">
    <section class="panel">
      <div class="panel-head"><h2><?= $e ? 'बदलें: ' . e($e['name']) : 'नया संस्करण' ?></h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e($e ? route('admin.epaper.editions.update', ['id' => $e['id']]) : route('admin.epaper.editions.store')) ?>" novalidate>
          <?= csrf_field() ?><?= $e ? method_field('PUT') : '' ?>
          <?= field('text', 'name', 'नाम', $e['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: लखनऊ संस्करण', 'attrs' => ['maxlength' => 150]]) ?>
          <?= field('text', 'slug', 'URL (स्लग)', $e['slug'] ?? '', ['prefix' => '/epaper/', 'attrs' => ['maxlength' => 170]]) ?>
          <?= field('select', 'type', 'प्रकार', $e['type'] ?? 'district', ['options' => EpaperEdition::TYPES]) ?>
          <label class="form-label" for="edLoc">लोकेशन</label>
          <div class="loc-picker mb-3" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
            <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
            <input type="search" class="form-control" id="edLoc" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="राज्य / ज़िला / शहर खोजें" role="combobox" aria-expanded="false" aria-controls="edLocList">
            <ul class="loc-results list-group" id="edLocList" role="listbox" hidden></ul>
          </div>
          <?= field('textarea', 'description', 'विवरण', $e['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 500]]) ?>
          <div class="row g-2">
            <div class="col-6"><?= field('select', 'status', 'स्थिति', $e['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
            <div class="col-6"><?= field('number', 'sort_order', 'क्रम', $e['sort_order'] ?? '', ['attrs' => ['min' => 0, 'max' => 100000]]) ?></div>
          </div>
          <?= field('switch', 'is_default', 'डिफ़ॉल्ट संस्करण (/epaper खोलने पर यही)', $e['is_default'] ?? 0) ?>
          <div class="d-flex gap-2 mt-2"><button class="btn btn-brand" type="submit"><?= $e ? 'सेव करें' : 'बनाएँ' ?></button><?php if ($e): ?><a class="btn btn-light" href="<?= e(route('admin.epaper.editions')) ?>">वापस</a><?php endif; ?></div>
        </form>
      </div>
    </section>
  </div>
  <?php if (!$e): ?>
  <div class="col-xl-8">
    <section class="panel">
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>संस्करण</th><th>प्रकार</th><th>अंक</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
        <tbody><?php foreach ($items as $r): ?>
          <tr class="<?= $r['status'] !== 'active' ? 'is-off' : '' ?>">
            <td><b><?= e($r['name']) ?></b><?= $r['is_default'] ? ' <span class="badge text-bg-dark">डिफ़ॉल्ट</span>' : '' ?><div class="small text-body-secondary"><?= e($r['location'] ?? '') ?> <span class="font-monospace">/epaper/<?= e($r['slug']) ?></span></div></td>
            <td class="small"><?= e(EpaperEdition::TYPES[$r['type']]) ?></td>
            <td><?= num($r['issues']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.epaper.editions.edit', ['id' => $r['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a>
              <?php if (can('epaper.delete')): ?><?= delete_button(route('admin.epaper.editions.destroy', ['id' => $r['id']]), '“' . $r['name'] . '” हट जाएगा।') ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </section>
  </div>
  <?php endif; ?>
</div>
