<?php
use App\Models\AdSlot;
$this->layout('layouts/admin');
$title = 'विज्ञापन स्लॉट';
$s = $edit;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.ads.index')) ?>">विज्ञापन</a></li><li class="breadcrumb-item active" aria-current="page">स्लॉट</li></ol></nav>
    <h1>विज्ञापन स्लॉट</h1>
    <p>वेबसाइट पर विज्ञापन की जगहें। कस्टम स्लॉट को किसी पेज/ख़बर में <code>[ad:key]</code> लिखकर या होमपेज बिल्डर के “विज्ञापन” ब्लॉक से लगाएँ।</p>
  </div>
</div>
<div class="row g-3">
  <div class="<?= $s ? 'col-xl-7' : 'col-xl-4 order-xl-2' ?>">
    <?php if (can($s ? 'ads.edit' : 'ads.create')): ?>
    <section class="panel">
      <div class="panel-head"><h2><?= $s ? 'बदलें: ' . e($s['name']) : 'नया कस्टम स्लॉट' ?></h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e($s ? route('admin.ads.slots.update', ['id' => $s['id']]) : route('admin.ads.slots.store')) ?>" novalidate>
          <?= csrf_field() ?><?= $s ? method_field('PUT') : '' ?>
          <?php if (!$s || !$s['is_system']): ?>
            <?= field('text', 'slot_key', 'Key (अंग्रेज़ी छोटे अक्षर, अंक, _ -)', $s['slot_key'] ?? '', ['required' => true, 'placeholder' => 'जैसे: election_box', 'attrs' => ['maxlength' => 60, 'pattern' => '[a-z0-9_-]{2,60}']]) ?>
          <?php else: ?><p class="small">Key: <code><?= e($s['slot_key']) ?></code> · <?= e(AdSlot::PLACEMENTS[$s['placement']] ?? $s['placement']) ?> (पहले से बना)</p><?php endif; ?>
          <?= field('text', 'name', 'नाम', $s['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?>
          <div class="row g-2">
            <div class="col-6"><?= field('text', 'size', 'सुझाया साइज़', $s['size'] ?? '', ['placeholder' => '300×250', 'attrs' => ['maxlength' => 40]]) ?></div>
            <div class="col-6"><?= field('number', 'max_ads', 'एक साथ कितने विज्ञापन', $s['max_ads'] ?? 1, ['attrs' => ['min' => 1, 'max' => 10]]) ?></div>
          </div>
          <?= field('select', 'status', 'स्थिति', $s['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद (कोई विज्ञापन नहीं दिखेगा)']]) ?>
          <?= field('textarea', 'description', 'विवरण', $s['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 300]]) ?>
          <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><?= $s ? 'सेव करें' : 'बनाएँ' ?></button><?php if ($s): ?><a class="btn btn-light" href="<?= e(route('admin.ads.slots')) ?>">वापस</a><?php endif; ?></div>
        </form>
      </div>
    </section>
    <?php endif; ?>
  </div>
  <?php if (!$s): ?>
  <div class="col-xl-8">
    <section class="panel">
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>स्लॉट</th><th>जगह</th><th>साइज़</th><th>चल रहे</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
        <tbody><?php foreach ($items as $r): ?>
          <tr class="<?= $r['status'] !== 'active' ? 'is-off' : '' ?>">
            <td><b><?= e($r['name']) ?></b><div class="small text-body-secondary font-monospace"><?= $r['placement'] === 'custom' ? '[ad:' . e($r['slot_key']) . ']' : e($r['slot_key']) ?></div></td>
            <td class="small"><?= e(AdSlot::PLACEMENTS[$r['placement']] ?? $r['placement']) ?></td>
            <td class="small"><?= e($r['size'] ?? '') ?></td>
            <td><a href="<?= e(route('admin.ads.index')) ?>?slot=<?= (int) $r['id'] ?>&status=running"><?= num($r['running']) ?></a> <span class="small text-body-secondary">/ <?= (int) $r['max_ads'] ?></span></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-end text-nowrap">
              <?php if (can('ads.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.ads.slots.edit', ['id' => $r['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
              <?php if (!$r['is_system'] && can('ads.delete')): ?><?= delete_button(route('admin.ads.slots.destroy', ['id' => $r['id']]), '“' . $r['name'] . '” हट जाएगा; उसमें लगे विज्ञापन बाकी जगह चलते रहेंगे।') ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </section>
  </div>
  <?php endif; ?>
</div>
