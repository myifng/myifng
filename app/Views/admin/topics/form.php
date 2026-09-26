<?php
use App\Models\Topic;
$this->layout('layouts/admin');
$isNew = $topic === null;
$title = $isNew ? 'नया ' . Topic::TYPES[$type] : $topic['name'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.topics.index')) ?>">टॉपिक</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($topic['name']) ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <?php if (!$isNew): ?><p><?= e(Topic::TYPES[$topic['type']]) ?> · <?= num($usage) ?> ख़बरें · आख़िरी बदलाव <?= time_ago($topic['updated_at']) ?></p><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e($isNew ? route('admin.topics.store') : route('admin.topics.update', ['id' => $topic['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'name', 'नाम', $topic['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 150], 'placeholder' => 'जैसे: लोकसभा चुनाव 2029']) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $topic['slug'] ?? '', ['prefix' => '/topic/', 'help' => 'ख़ाली छोड़ें तो नाम से अंग्रेज़ी में बनेगा', 'attrs' => ['maxlength' => 170]]) ?>
        <?= field('textarea', 'description', 'विवरण', $topic['description'] ?? '', ['rows' => 3, 'help' => 'टॉपिक पेज पर ऊपर दिखता है', 'attrs' => ['maxlength' => 1000]]) ?>
        <div class="row">
          <div class="col-md-6"><?= media_field('image', 'इमेज (कार्ड/शेयर)', $topic['image'] ?? '') ?></div>
          <div class="col-md-6"><?= media_field('banner', 'बैनर (विशेष सेक्शन के ऊपर, चौड़ा)', $topic['banner'] ?? '', ['help' => '1600×400 जैसा चौड़ा']) ?></div>
        </div>
      </div></section>
      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2></div>
        <div class="panel-body">
          <?= field('text', 'meta_title', 'SEO शीर्षक', $topic['meta_title'] ?? '', ['attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $topic['meta_description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
        </div>
      </section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>सेटिंग</h2></div>
        <div class="panel-body">
          <?= field('select', 'type', 'प्रकार', $topic['type'] ?? $type, ['options' => Topic::TYPES]) ?>
          <?= field('select', 'status', 'स्थिति', $topic['status'] ?? 'active', ['options' => Topic::STATUSES]) ?>
          <?= field('color', 'color', 'रंग (विशेष सेक्शन की थीम)', $topic['color'] ?? '#d71920') ?>
          <?= field('number', 'sort_order', 'क्रम', $topic['sort_order'] ?? '', ['help' => 'छोटी संख्या पहले', 'attrs' => ['min' => 0, 'max' => 100000]]) ?>
          <?= field('switch', 'is_featured', 'फ़ीचर्ड / ट्रेंडिंग (हेडर की ट्रेंडिंग पट्टी में)', $topic['is_featured'] ?? 0) ?>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button>
            <a class="btn btn-light" href="<?= e(route('admin.topics.index')) ?>">वापस</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('topics.delete')): ?>
  <div class="danger-zone mt-3">
    <div><b>हटाएँ</b><p class="mb-0 small">ख़बरों से जुड़ा हो तो नहीं हटेगा; तब इसे “बंद” करें।</p></div>
    <?= delete_button(route('admin.topics.destroy', ['id' => $topic['id']]), '“' . $topic['name'] . '” हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?>
  </div>
<?php endif; ?>
