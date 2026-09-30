<?php
$this->layout('layouts/admin');
$title = 'टैग: ' . $tag['name'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.tags.index')) ?>">टैग</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($tag['name']) ?></li></ol></nav>
    <h1><?= e($tag['name']) ?></h1>
    <p><?= num($tag['usage_count']) ?> ख़बरों में · बना <?= hindi_date($tag['created_at']) ?></p>
  </div>
</div>
<section class="panel" style="max-width:760px">
  <form class="panel-body" method="post" action="<?= e(route('admin.tags.update', ['id' => $tag['id']])) ?>" novalidate>
    <?= csrf_field() ?><?= method_field('PUT') ?>
    <?= field('text', 'name', 'टैग का नाम', $tag['name'], ['required' => true, 'attrs' => ['maxlength' => 100]]) ?>
    <?= field('text', 'slug', 'URL (स्लग)', $tag['slug'], ['prefix' => '/tag/', 'attrs' => ['maxlength' => 120]]) ?>
    <?= field('textarea', 'description', 'विवरण', $tag['description'], ['rows' => 3, 'help' => 'टैग पेज पर (SEO के लिए अच्छा)', 'attrs' => ['maxlength' => 500]]) ?>
    <?= field('text', 'meta_title', 'SEO शीर्षक', $tag['meta_title'] ?? '', ['help' => 'ख़ाली = "टैग से जुड़ी ख़बरें"', 'attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
    <?= field('textarea', 'meta_description', 'SEO विवरण', $tag['meta_description'] ?? '', ['rows' => 2, 'help' => 'ख़ाली = ऊपर का विवरण', 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
    <div class="d-flex gap-2">
      <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
      <a class="btn btn-light" href="<?= e(route('admin.tags.index')) ?>">वापस</a>
    </div>
  </form>
</section>
