<?php
$this->layout('layouts/admin');
$title = 'टैग';
$canBulk = can('tags.delete');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">टैग</li></ol></nav>
    <h1>टैग</h1>
    <p>ख़बरों के कीवर्ड। मिलते-जुलते टैग (जैसे “योगी”, “योगी आदित्यनाथ”) चुनकर एक में मर्ज करें। बिना इस्तेमाल वाले: <?= num($unused) ?></p>
  </div>
</div>

<div class="row g-3">
  <?php if (can('tags.create')): ?>
  <div class="col-xl-4 order-xl-2">
    <section class="panel sticky-xl">
      <div class="panel-head"><h2>नए टैग जोड़ें</h2></div>
      <form class="panel-body" method="post" action="<?= e(route('admin.tags.store')) ?>" novalidate>
        <?= csrf_field() ?>
        <?= field('textarea', 'names', 'टैग के नाम', '', ['rows' => 3, 'required' => true, 'placeholder' => 'चुनाव, बजट 2026, मौसम', 'help' => 'कई टैग एक साथ: कॉमा या नई लाइन से अलग करें। URL अपने आप अंग्रेज़ी में बनेगा।']) ?>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-plus me-1"></i> जोड़ें</button>
      </form>
    </section>
  </div>
  <?php endif; ?>
  <div class="col-xl-8">
    <section class="panel">
      <form class="filter-bar" method="get">
        <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="टैग खोजें" aria-label="टैग खोजें"></div>
        <select class="form-select w-auto" name="sort" aria-label="क्रम" onchange="this.form.submit()">
          <?php foreach (['usage' => 'सबसे ज़्यादा इस्तेमाल', 'name' => 'नाम (अ-ज्ञ)', 'new' => 'सबसे नए'] as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $sort) ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-dark" type="submit">खोजें</button>
      </form>
      <?php if ($tags->items): ?>
      <form method="post" action="<?= e(route('admin.tags.bulk')) ?>" data-bulk-form data-bulk-noun="टैग">
        <?= csrf_field() ?>
        <?php if ($canBulk): ?>
        <div class="bulk-bar" hidden>
          <span data-bulk-count>0 चुने गए</span>
          <select class="form-select form-select-sm w-auto" name="action" aria-label="बल्क काम" required>
            <option value="">काम चुनें…</option>
            <?php if (can('tags.edit')): ?><option value="merge">एक टैग में मर्ज करें</option><?php endif; ?>
            <option value="delete">हटाएँ</option>
          </select>
          <select class="form-select form-select-sm w-auto" name="target" aria-label="किस टैग में मिलाएँ" data-merge-target hidden><option value="">किस टैग में मिलाएँ?</option></select>
          <button class="btn btn-sm btn-dark" type="submit">लागू करें</button>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table">
            <thead><tr><?php if ($canBulk): ?><th style="width:36px"><input class="form-check-input" type="checkbox" data-check-all aria-label="सभी चुनें"></th><?php endif; ?><th>टैग</th><th class="text-end">ख़बरें</th><th class="text-end">काम</th></tr></thead>
            <tbody>
            <?php foreach ($tags->items as $t): ?>
              <tr>
                <?php if ($canBulk): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $t['id'] ?>" data-name="<?= e($t['name']) ?> (<?= (int) $t['usage_count'] ?>)" aria-label="चुनें: <?= e($t['name']) ?>"></td><?php endif; ?>
                <td><b class="d-block"><?= e($t['name']) ?></b><span class="small text-body-secondary font-monospace">/tag/<?= e($t['slug']) ?></span></td>
                <td class="text-end"><?= num($t['usage_count']) ?></td>
                <td class="text-end text-nowrap">
                  <?php if (can('tags.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.tags.edit', ['id' => $t['id']])) ?>" title="बदलें" aria-label="बदलें: <?= e($t['name']) ?>"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                  <?php if (can('tags.delete')): ?><button class="btn btn-sm btn-icon btn-outline-danger" type="submit" form="del-<?= (int) $t['id'] ?>" title="हटाएँ" aria-label="हटाएँ: <?= e($t['name']) ?>"><i class="fa-solid fa-trash-can"></i></button><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
      <?php foreach ($tags->items as $t): ?>
        <form id="del-<?= (int) $t['id'] ?>" method="post" action="<?= e(route('admin.tags.destroy', ['id' => $t['id']])) ?>" hidden data-confirm="टैग “<?= e($t['name']) ?>” हट जाएगा<?= $t['usage_count'] ? ' और ' . (int) $t['usage_count'] . ' ख़बरों से भी हटेगा' : '' ?>।"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
      <?php endforeach; ?>
      <?= $this->insert('partials/admin/pagination', ['p' => $tags]) ?>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-tags"></i><p><?= $q !== '' ? 'कोई टैग नहीं मिला।' : 'अभी कोई टैग नहीं है। ख़बर लिखते समय (Phase 4) भी टैग जुड़ेंगे।' ?></p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
