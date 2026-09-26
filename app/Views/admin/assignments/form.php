<?php
use App\Models\Assignment;
$this->layout('layouts/admin');
$isNew = $a === null;
$title = $isNew ? 'नया असाइनमेंट' : 'असाइनमेंट: ' . $a['title'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.assignments.index')) ?>">असाइनमेंट</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($a['title']) ?></li></ol></nav>
    <h1><?= $isNew ? 'नया असाइनमेंट' : e($a['title']) ?></h1>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.assignments.store') : route('admin.assignments.update', ['id' => $a['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $a['title'] ?? '', ['required' => true, 'placeholder' => 'जैसे: ज़िला अस्पताल में दवाओं की कमी पर रिपोर्ट', 'attrs' => ['maxlength' => 190]]) ?>
        <?= field('textarea', 'description', 'विवरण', $a['description'] ?? '', ['rows' => 4, 'attrs' => ['maxlength' => 5000]]) ?>
        <?= field('textarea', 'instructions', 'निर्देश', $a['instructions'] ?? '', ['rows' => 3, 'placeholder' => 'किससे बात करें, कौन-सी तस्वीरें/वीडियो चाहिए…', 'attrs' => ['maxlength' => 5000]]) ?>
        <span class="form-label d-block">अटैचमेंट</span>
        <div class="media-list" data-media-list data-name="attachments[]" data-kind="" data-max="20">
          <?php foreach ($attachments as $m): ?><span class="ml-item" data-id="<?= (int) $m['id'] ?>"><?= $m['kind'] === 'image' ? '<img src="' . e(media_url($m, 'thumb')) . '" alt="">' : '<span class="ml-file"><i class="fa-regular fa-file"></i><small>' . e($m['original_name']) . '</small></span>' ?><input type="hidden" name="attachments[]" value="<?= (int) $m['id'] ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></span><?php endforeach; ?>
          <?php if (can('media.view')): ?><button type="button" class="ml-add" data-ml-add><i class="fa-solid fa-paperclip"></i><span>जोड़ें</span></button><?php endif; ?>
        </div>
      </div></section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl"><div class="panel-body">
        <?= field('select', 'reporter_id', 'रिपोर्टर', $a['reporter_id'] ?? '', ['options' => array_column($reporters, 'name', 'id'), 'empty' => 'चुनें…', 'required' => true]) ?>
        <?= field('select', 'category_id', 'बीट (श्रेणी)', $a['category_id'] ?? '', ['options' => $categories, 'empty' => '—']) ?>
        <div class="mb-3">
          <label class="form-label" for="aLoc">लोकेशन</label>
          <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
            <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
            <input type="search" class="form-control" id="aLoc" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="खोजें…" role="combobox" aria-expanded="false">
            <ul class="loc-results list-group" role="listbox" hidden></ul>
          </div>
        </div>
        <?= field('select', 'priority', 'प्राथमिकता', $a['priority'] ?? 'normal', ['options' => array_map(fn($p) => $p[0], Assignment::PRIORITIES)]) ?>
        <?= field('datetime-local', 'deadline', 'डेडलाइन', !empty($a['deadline']) ? date('Y-m-d\TH:i', strtotime($a['deadline'])) : '') ?>
        <?php if (!$isNew): ?><?= field('select', 'status', 'स्थिति', $a['status'], ['options' => array_map(fn($s) => $s[0], Assignment::STATUSES)]) ?><?php endif; ?>
        <div class="d-grid gap-2">
          <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'सौंपें' : 'सेव करें' ?></button>
          <a class="btn btn-light" href="<?= e(route('admin.assignments.index')) ?>">वापस</a>
        </div>
      </div></section>
    </div>
  </div>
</form>
