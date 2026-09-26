<?php
use App\Models\Location;
$this->layout('layouts/admin');
$isNew = $loc === null;
$title = $isNew ? 'नई लोकेशन' : $loc['name'] . ' · लोकेशन';
$typeOptions = array_intersect_key(Location::TYPES, array_flip($types));
$parentPath = $parent['path'] ?? null;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= e(route('admin.locations.index')) ?>">लोकेशन</a></li>
      <?php foreach ($ancestors as $a): ?><li class="breadcrumb-item"><a href="<?= e(route('admin.locations.index')) ?>?parent=<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a></li><?php endforeach; ?>
      <li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : e($loc['name']) ?></li>
    </ol></nav>
    <h1><?= $isNew ? ($parent ? e($parent['name']) . ' में नई लोकेशन' : 'नई लोकेशन') : e($loc['name']) ?></h1>
    <?php if (!$isNew): ?><p><?= e(Location::TYPES[$loc['type']]) ?> · नीचे <?= num($children) ?> · <?= num($usage) ?> ख़बरें<?= $loc['path'] ? ' · <a href="' . e(url($loc['path'])) . '" target="_blank" rel="noopener" class="font-monospace">/' . e($loc['path']) . '/</a>' : '' ?></p><?php endif; ?>
  </div>
  <?php if (!$isNew): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.locations.index')) ?>?parent=<?= (int) $loc['id'] ?>"><i class="fa-solid fa-sitemap me-1"></i> नीचे की लोकेशन (<?= num($children) ?>)</a><?php endif; ?>
</div>

<form method="post" action="<?= e($isNew ? route('admin.locations.store') : route('admin.locations.update', ['id' => $loc['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <div class="row">
          <div class="col-md-6"><?= field('text', 'name', 'नाम (हिंदी)', $loc['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: महराजगंज', 'attrs' => ['maxlength' => 120]]) ?></div>
          <div class="col-md-6"><?= field('text', 'name_en', 'नाम (अंग्रेज़ी)', $loc['name_en'] ?? '', ['placeholder' => 'Maharajganj', 'help' => 'इसी से URL बनता है (सबसे सही)', 'attrs' => ['maxlength' => 120, 'data-slug-source' => '#f_slug']]) ?></div>
        </div>
        <div class="row">
          <div class="col-md-6"><?= field('select', 'type', 'स्तर', $loc['type'] ?? ($types[0] ?? ''), ['options' => $typeOptions, 'required' => true, 'help' => 'देश और मंडल URL में नहीं आते']) ?></div>
          <div class="col-md-6"><?= field('text', 'code', 'कोड (वैकल्पिक)', $loc['code'] ?? '', ['placeholder' => 'UP / 273303', 'help' => 'राज्य कोड, पिन कोड आदि', 'attrs' => ['maxlength' => 20]]) ?></div>
        </div>
        <?= field('text', 'slug', 'URL (स्लग)', $loc['slug'] ?? '', ['prefix' => '/' . ($parentPath ? e($parentPath) . '/' : ''), 'help' => 'ख़ाली छोड़ें तो अंग्रेज़ी नाम से बनेगा। पूरा पता ऊपर वाली लोकेशन से अपने आप जुड़ता है।', 'attrs' => ['maxlength' => 100]]) ?>

        <div class="mb-3">
          <label class="form-label" for="parentSearch">ऊपर वाली लोकेशन</label>
          <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
            <input type="hidden" name="parent_id" value="<?= e(old('parent_id', $parent['id'] ?? '')) ?>">
            <input type="search" class="form-control<?= error('parent_id') ? ' is-invalid' : '' ?>" id="parentSearch" autocomplete="off" value="<?= e($parent ? $parent['name'] . ' (' . Location::SHORT[$parent['type']] . ')' : '') ?>" placeholder="खोजें और चुनें…" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="parentList">
            <ul class="loc-results list-group" id="parentList" role="listbox" hidden></ul>
          </div>
          <?php if (error('parent_id')): ?><div class="invalid-feedback d-block"><?= e(error('parent_id')) ?></div><?php else: ?><div class="form-text">दूसरी जगह ले जाने के लिए बदलें (जैसे ज़िले को किसी मंडल के नीचे)। नीचे की सभी लोकेशन साथ जाएँगी।</div><?php endif; ?>
        </div>
      </div></section>
      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2></div>
        <div class="panel-body">
          <?= field('text', 'meta_title', 'SEO शीर्षक', $loc['meta_title'] ?? '', ['help' => 'ख़ाली हो तो “नाम समाचार”', 'attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $loc['meta_description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
        </div>
      </section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>सेटिंग</h2></div>
        <div class="panel-body">
          <?= field('select', 'status', 'स्थिति', $loc['status'] ?? 'active', ['options' => Location::STATUSES, 'help' => 'बंद लोकेशन वेबसाइट और “मेरा शहर” में नहीं दिखती']) ?>
          <?= field('switch', 'is_popular', 'लोकप्रिय (मेरा शहर में सबसे ऊपर)', $loc['is_popular'] ?? 0) ?>
          <?= field('switch', 'show_in_menu', 'मेनू में दिखाने लायक', $loc['show_in_menu'] ?? 0) ?>
          <?php if ($isNew): ?><?= field('switch', 'add_another', 'सेव के बाद इसी जगह एक और जोड़ें', 1) ?><?php endif; ?>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'जोड़ें' : 'सेव करें' ?></button>
            <a class="btn btn-light" href="<?= e(route('admin.locations.index')) ?><?= $parent ? '?parent=' . (int) $parent['id'] : '' ?>">वापस</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('locations.delete')): ?>
  <div class="danger-zone mt-3">
    <div><b>लोकेशन हटाएँ</b><p class="mb-0 small">नीचे लोकेशन हों या ख़बरें जुड़ी हों तो नहीं हटेगी।</p></div>
    <?= delete_button(route('admin.locations.destroy', ['id' => $loc['id']]), '“' . $loc['name'] . '” हमेशा के लिए हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?>
  </div>
<?php endif; ?>
