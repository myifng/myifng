<?php
use App\Models\Page;
$this->layout('layouts/admin');
$isNew = $page === null;
$title = $isNew ? 'नया पेज' : 'पेज: ' . $page['title'];
$status = old('status', $page['status'] ?? 'draft');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.pages.index')) ?>">पेज</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($page['title']) ?></li></ol></nav>
    <h1><?= $isNew ? 'नया पेज' : e($page['title']) ?></h1>
    <?php if (!$isNew): ?><p>आख़िरी बदलाव: <?= hindi_date($page['updated_at'], true) ?></p><?php endif; ?>
  </div>
  <?php if (!$isNew): ?>
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.pages.preview', ['id' => $page['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i> प्रीव्यू</a>
  <?php endif; ?>
</div>

<form method="post" action="<?= e($isNew ? route('admin.pages.store') : route('admin.pages.update', ['id' => $page['id']])) ?>" enctype="multipart/form-data" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel">
        <div class="panel-body">
          <?= field('text', 'title', 'शीर्षक', $page['title'] ?? '', ['required' => true, 'class' => 'form-control-lg fw-bold', 'attrs' => ['maxlength' => 190, 'data-slug-source' => '#f_slug', 'data-seo-title' => true]]) ?>
          <?= field('text', 'slug', 'URL (स्लग)', $page['slug'] ?? '', ['prefix' => '/page/', 'help' => 'ख़ाली छोड़ें तो शीर्षक से अंग्रेज़ी में अपने आप बनेगा', 'attrs' => ['maxlength' => 190]]) ?>
          <div class="mb-3">
            <span class="form-label d-block">सामग्री</span>
            <div class="rte" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>">
              <textarea name="content" class="rte-source" aria-label="पेज की सामग्री"><?= e(old('content', $page['content'] ?? '')) ?></textarea>
            </div>
            <div class="form-text">Shortcode: <code>[site_name]</code> <code>[contact_email]</code> <code>[contact_phone]</code> <code>[address]</code> <code>[year]</code>, जो सेटिंग से अपने आप बदलते हैं।</div>
          </div>
          <?= field('textarea', 'excerpt', 'सार (छोटा विवरण)', $page['excerpt'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 500]]) ?>
        </div>
      </section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-brands fa-google me-2 text-body-secondary"></i>SEO</h2></div>
        <div class="panel-body">
          <div class="serp" aria-hidden="true">
            <span class="serp-url"><?= e(parse_url((string) config('app.url'), PHP_URL_HOST)) ?> › page › <span data-serp-slug><?= e($page['slug'] ?? '') ?></span></span>
            <span class="serp-title" data-serp-title><?= e(($page['meta_title'] ?? '') ?: ($page['title'] ?? 'पेज का शीर्षक')) ?></span>
            <span class="serp-desc" data-serp-desc><?= e(($page['meta_description'] ?? '') ?: 'पेज का विवरण यहाँ दिखेगा।') ?></span>
          </div>
          <?= field('text', 'meta_title', 'SEO शीर्षक', $page['meta_title'] ?? '', ['help' => 'ख़ाली हो तो पेज का शीर्षक। 60 अक्षर तक सबसे अच्छा।', 'attrs' => ['maxlength' => 190, 'data-count' => 60]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $page['meta_description'] ?? '', ['rows' => 2, 'help' => '150-160 अक्षर सबसे अच्छा।', 'attrs' => ['maxlength' => 320, 'data-count' => 160]]) ?>
          <div class="row">
            <div class="col-md-7"><?= field('text', 'meta_keywords', 'कीवर्ड', $page['meta_keywords'] ?? '', ['placeholder' => 'कॉमा से अलग करें']) ?></div>
            <div class="col-md-5"><?= field('select', 'robots', 'सर्च इंजन', $page['robots'] ?? 'index,follow', ['options' => Page::ROBOTS]) ?></div>
          </div>
          <?= field('file', 'og_image', 'सोशल शेयर इमेज (OG)', null, ['help' => '1200×630 सबसे अच्छा। ख़ाली हो तो मुख्य तस्वीर इस्तेमाल होगी।', 'attrs' => ['accept' => 'image/*']]) ?>
          <?php if (!empty($page['og_image'])): ?><div class="img-inline"><img src="<?= e(upload_url($page['og_image'])) ?>" alt=""><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_og_image" value="1"> <span class="form-check-label">हटाएँ</span></label></div><?php endif; ?>
        </div>
      </section>
    </div>

    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>प्रकाशन</h2></div>
        <div class="panel-body">
          <?php if (can('pages.publish')): ?>
            <?= field('select', 'status', 'स्थिति', $status, ['options' => Page::STATUSES]) ?>
          <?php else: ?>
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <p class="small"><b>स्थिति:</b> <?= e(Page::STATUSES[$status] ?? $status) ?><br><span class="text-body-secondary">प्रकाशित करने की अनुमति आपके पास नहीं है।</span></p>
          <?php endif; ?>
          <?= field('select', 'template', 'टेम्पलेट', $page['template'] ?? 'default', ['options' => Page::TEMPLATES]) ?>
          <?= field('switch', 'show_header', 'वेबसाइट का हेडर दिखाएँ', $page['show_header'] ?? 1) ?>
          <?= field('switch', 'show_footer', 'वेबसाइट का फ़ुटर दिखाएँ', $page['show_footer'] ?? 1) ?>
          <?php if (!$isNew && $page['published_at']): ?><p class="small text-body-secondary mb-0 mt-2">पहली बार प्रकाशित: <?= hindi_date($page['published_at'], true) ?></p><?php endif; ?>
        </div>
        <div class="panel-foot">
          <?php if (!$isNew && $page['status'] === 'published'): ?><a class="btn btn-light me-auto" href="<?= e(url('page/' . $page['slug'])) ?>" target="_blank" rel="noopener">साइट पर देखें</a><?php endif; ?>
          <button class="btn btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i><?= $isNew ? 'पेज बनाएँ' : 'सेव करें' ?></button>
        </div>
      </section>
      <section class="panel mt-3">
        <div class="panel-head"><h2>मुख्य तस्वीर</h2></div>
        <div class="panel-body">
          <?php if (!empty($page['featured_image'])): ?><div class="img-inline"><img src="<?= e(upload_url($page['featured_image'])) ?>" alt=""><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_featured_image" value="1"> <span class="form-check-label">हटाएँ</span></label></div><?php endif; ?>
          <?= field('file', 'featured_image', 'तस्वीर चुनें', null, ['help' => 'JPG/PNG/WebP, 10 MB तक', 'wrap' => 'mb-0', 'attrs' => ['accept' => 'image/*']]) ?>
        </div>
      </section>
    </div>
  </div>
</form>
