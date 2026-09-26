<?php
use App\Controllers\Admin\GalleryController;
$this->layout('layouts/admin');
$isNew = $row === null;
$title = $isNew ? 'नई गैलरी' : $row['title'];
$list = is_array(old('photos')) ? array_values(old('photos')) : $photos;
$photoRow = function (array $p, string $i): string {
    $img = (string) ($p['image'] ?? '');
    $f = fn($k, $label, $max) => '<label class="gp-f"><span>' . $label . '</span><input class="form-control form-control-sm" name="photos[' . $i . '][' . $k . ']" value="' . e($p[$k] ?? '') . '" maxlength="' . $max . '"></label>';
    return '<li class="gp-item" data-gp-item>'
        . '<img src="' . (preg_match('~^media/[\w/.-]+$~', $img) ? e(media_url($img, 'thumb')) : '') . '" alt="" data-gp-thumb>'
        . '<input type="hidden" name="photos[' . $i . '][image]" value="' . e($img) . '" data-gp-image>'
        . '<div class="gp-fields">' . $f('caption', 'कैप्शन', 500)
        . '<div class="gp-more">' . $f('photographer', 'फ़ोटोग्राफ़र', 150) . $f('location', 'स्थान', 150) . $f('credit', 'क्रेडिट', 150) . $f('copyright', 'कॉपीराइट', 150) . '</div></div>'
        . '<div class="gp-tools"><button type="button" class="btn btn-sm btn-icon btn-light" data-gp-up aria-label="ऊपर"><i class="fa-solid fa-arrow-up"></i></button>'
        . '<button type="button" class="btn btn-sm btn-icon btn-light" data-gp-down aria-label="नीचे"><i class="fa-solid fa-arrow-down"></i></button>'
        . '<button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-gp-remove aria-label="हटाएँ"><i class="fa-solid fa-xmark"></i></button></div></li>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.galleries.index')) ?>">फ़ोटो गैलरी</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.galleries.store') : route('admin.galleries.update', ['id' => $row['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $row['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 255, 'data-count' => 100]]) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $row['slug'] ?? '', ['prefix' => '/photos/', 'help' => 'ख़ाली छोड़ें तो शीर्षक से बनेगा', 'attrs' => ['maxlength' => 190]]) ?>
        <?= field('textarea', 'description', 'विवरण', $row['description'] ?? '', ['rows' => 3, 'attrs' => ['maxlength' => 5000]]) ?>
      </div></section>

      <section class="panel mt-3" data-gp data-upload="<?= can('media.create') ? e(route('admin.media.store')) : '' ?>" data-max="<?= GalleryController::MAX_PHOTOS ?>">
        <div class="panel-head">
          <h2><i class="fa-regular fa-images me-2 text-body-secondary"></i>फ़ोटो <span class="badge text-bg-light" data-gp-count><?= count($list) ?></span></h2>
          <div class="d-flex gap-2 flex-wrap">
            <?php if (can('media.create')): ?><label class="btn btn-sm btn-dark mb-0"><i class="fa-solid fa-cloud-arrow-up me-1"></i> कई फ़ोटो अपलोड<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-gp-files></label><?php endif; ?>
            <?php if (can('media.view')): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-gp-pick><i class="fa-solid fa-photo-film me-1"></i> लाइब्रेरी से</button><?php endif; ?>
          </div>
        </div>
        <div class="panel-body">
          <?php if (error('photos')): ?><div class="alert alert-danger py-2"><?= e(error('photos')) ?></div><?php endif; ?>
          <p class="small text-body-secondary mb-2" data-gp-status role="status" aria-live="polite">पहली फ़ोटो कवर बनती है (अगर ऊपर अलग कवर न चुना हो)। कैप्शन और क्रेडिट लाइब्रेरी से अपने आप भरते हैं।</p>
          <ol class="gp-list" data-gp-list><?php foreach ($list as $i => $p): ?><?= $photoRow((array) $p, (string) $i) ?><?php endforeach; ?></ol>
          <div class="empty-state py-3" data-gp-empty<?= $list ? ' hidden' : '' ?>><i class="fa-regular fa-images"></i><p class="mb-0">फ़ोटो यहाँ खींचकर छोड़ें या ऊपर के बटन से जोड़ें।</p></div>
          <template data-gp-template><?= $photoRow([], '__i__') ?></template>
        </div>
      </section>
      <?= $this->insert('admin/multimedia/_seo', ['row' => $row]) ?>
    </div>
    <div class="col-xl-4">
      <?php ob_start(); ?>
        <?= media_field('cover', 'कवर (ख़ाली = पहली फ़ोटो)', $row['cover'] ?? '') ?>
        <?= field('text', 'photographer', 'फ़ोटोग्राफ़र', $row['photographer'] ?? '', ['attrs' => ['maxlength' => 150]]) ?>
        <label class="form-label" for="gLoc">लोकेशन</label>
        <div class="loc-picker mb-3" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
          <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
          <input type="search" class="form-control" id="gLoc" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="ज़िला / शहर खोजें" role="combobox" aria-expanded="false" aria-controls="gLocList">
          <ul class="loc-results list-group" id="gLocList" role="listbox" hidden></ul>
        </div>
      <?php $publishExtra = ob_get_clean(); ?>
      <?= $this->insert('admin/multimedia/_publish', get_defined_vars()) ?>
    </div>
  </div>
</form>
<?= $this->insert('admin/multimedia/_danger', ['row' => $row, 'module' => $module]) ?>
