<?php
use App\Models\Category;
$this->layout('layouts/admin');
$isNew = $cat === null;
$title = $isNew ? 'नई श्रेणी' : 'श्रेणी: ' . $cat['name'];
$parentOptions = [];
foreach ($parents as $p) {
    $parentOptions[$p['id']] = $p['name'];
}
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.categories.index')) ?>">श्रेणियाँ</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : e($cat['name']) ?></li></ol></nav>
    <h1><?= $isNew ? 'नई श्रेणी' : e($cat['name']) ?></h1>
    <?php if (!$isNew): ?><p><?= num($childCount) ?> उप-श्रेणी · <?= num($usage) ?> ख़बरें · आख़िरी बदलाव <?= time_ago($cat['updated_at']) ?></p><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e($isNew ? route('admin.categories.store') : route('admin.categories.update', ['id' => $cat['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <div class="row">
          <div class="col-md-7"><?= field('text', 'name', 'नाम', $cat['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 120, 'data-seo-title' => true], 'placeholder' => 'जैसे: खेल']) ?></div>
          <div class="col-md-5"><?= field('select', 'parent_id', 'मुख्य श्रेणी', $parentId, ['options' => $parentOptions, 'empty' => '— यह ख़ुद मुख्य श्रेणी है —', 'help' => !$isNew && $childCount ? 'इसकी उप-श्रेणियाँ हैं, इसलिए यह मुख्य ही रहेगी' : 'चुनें तो यह उप-श्रेणी बनेगी', 'attrs' => !$isNew && $childCount ? ['disabled' => true] : []]) ?>
            <?php if (!$isNew && $childCount): ?><input type="hidden" name="parent_id" value=""><?php endif; ?></div>
        </div>
        <?= field('text', 'slug', 'URL (स्लग)', $cat['slug'] ?? '', ['prefix' => '/category/', 'help' => 'ख़ाली छोड़ें तो नाम से अंग्रेज़ी में बनेगा (खेल → khel)। अंग्रेज़ी में ख़ुद लिखें तो बेहतर: sports', 'attrs' => ['maxlength' => 140]]) ?>
        <?= field('textarea', 'description', 'विवरण', $cat['description'] ?? '', ['rows' => 2, 'help' => 'श्रेणी पेज पर ऊपर दिखता है', 'attrs' => ['maxlength' => 500]]) ?>
        <div class="row">
          <div class="col-md-6"><?= field('text', 'icon', 'आइकन (Font Awesome)', $cat['icon'] ?? '', ['placeholder' => 'fa-futbol', 'help' => 'fontawesome.com/icons से नाम, जैसे fa-futbol, fa-landmark', 'attrs' => ['maxlength' => 60, 'data-icon-preview' => '#iconPrev']]) ?></div>
          <div class="col-md-2 d-flex align-items-center"><span class="cat-dot lg" id="iconPrev" style="--c:<?= e($cat['color'] ?? '#d71920') ?>"><i class="fa-solid <?= e(preg_replace('/^fa-(solid|regular|brands)\s+/', '', (string) (($cat['icon'] ?? '') ?: 'fa-folder'))) ?>"></i></span></div>
          <div class="col-md-4"><?= field('color', 'color', 'रंग', $cat['color'] ?? '#d71920', ['help' => 'श्रेणी का लेबल/बैज']) ?></div>
        </div>
        <?= media_field('image', 'श्रेणी की इमेज', $cat['image'] ?? '', ['help' => 'श्रेणी पेज और सोशल शेयर में (1200×630 अच्छा)']) ?>
      </div></section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2></div>
        <div class="panel-body">
          <?= field('text', 'meta_title', 'SEO शीर्षक', $cat['meta_title'] ?? '', ['help' => 'ख़ाली हो तो “नाम समाचार”', 'attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $cat['meta_description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
        </div>
      </section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>प्रकाशन</h2></div>
        <div class="panel-body">
          <?= field('select', 'status', 'स्थिति', $cat['status'] ?? 'active', ['options' => Category::STATUSES, 'help' => 'बंद श्रेणी वेबसाइट और मेनू में नहीं दिखती']) ?>
          <?= field('switch', 'show_in_menu', 'मेनू/नेविगेशन में दिखाएँ', $cat['show_in_menu'] ?? 1) ?>
          <?= field('switch', 'show_on_home', 'होमपेज पर इसका सेक्शन', $cat['show_on_home'] ?? 0) ?>
          <?php if ($isNew): ?><?= field('switch', 'add_another', 'सेव के बाद एक और जोड़ें', 0) ?><?php endif; ?>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'श्रेणी बनाएँ' : 'सेव करें' ?></button>
            <a class="btn btn-light" href="<?= e(route('admin.categories.index')) ?>">वापस</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('categories.delete')): ?>
  <div class="danger-zone mt-3">
    <div><b>श्रेणी हटाएँ</b><p class="mb-0 small">उप-श्रेणियाँ या ख़बरें हों तो नहीं हटेगी। मेनू से इसके लिंक भी हट जाएँगे।</p></div>
    <?= delete_button(route('admin.categories.destroy', ['id' => $cat['id']]), '“' . $cat['name'] . '” हमेशा के लिए हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?>
  </div>
<?php endif; ?>
