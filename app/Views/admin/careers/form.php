<?php
use App\Controllers\Admin\JobController as JC;
$this->layout('layouts/admin');
$isNew = $job === null;
$title = $isNew ? 'नई वैकेंसी' : $job['title'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.careers.index')) ?>">करियर</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
  <?php if (!$isNew): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.inbox', ['type' => 'career'])) ?>?status=all&amp;job=<?= (int) $job['id'] ?>"><i class="fa-solid fa-inbox me-1"></i> इसके आवेदन</a><?php endif; ?>
</div>
<form method="post" action="<?= e($isNew ? route('admin.careers.store') : route('admin.careers.update', ['id' => $job['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8"><section class="panel"><div class="panel-body">
      <?= field('text', 'title', 'पद का नाम', $job['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 190], 'placeholder' => 'जैसे: डिजिटल सब-एडिटर (हिंदी)']) ?>
      <span class="form-label d-block">काम का विवरण</span>
      <div class="rte mb-3" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>"><textarea name="description" class="rte-source" aria-label="विवरण"><?= e(old('description', $job['description'] ?? '')) ?></textarea></div>
      <?= field('textarea', 'requirements', 'योग्यता (एक लाइन में एक)', $job['requirements'] ?? '', ['rows' => 4]) ?>
    </div></section></div>
    <div class="col-xl-4"><section class="panel sticky-xl"><div class="panel-body">
      <?= field('select', 'status', 'स्थिति', $job['status'] ?? 'draft', ['options' => JC::STATUSES]) ?>
      <?= field('select', 'job_type', 'प्रकार', $job['job_type'] ?? 'full_time', ['options' => JC::TYPES]) ?>
      <?= field('text', 'department', 'विभाग', $job['department'] ?? '', ['placeholder' => 'संपादकीय / वीडियो / टेक']) ?>
      <?= field('text', 'location', 'जगह', $job['location'] ?? '', ['placeholder' => 'लखनऊ / रिमोट']) ?>
      <div class="row g-2"><div class="col-6"><?= field('number', 'vacancies', 'पद संख्या', $job['vacancies'] ?? 1, ['attrs' => ['min' => 1, 'max' => 999]]) ?></div>
        <div class="col-6"><?= field('date', 'deadline', 'अंतिम तारीख़', $job['deadline'] ?? '') ?></div></div>
      <?= field('text', 'salary', 'वेतन (वैकल्पिक)', $job['salary'] ?? '', ['placeholder' => '₹25,000 - ₹35,000 / माह']) ?>
      <?php if (!$isNew): ?><?= field('text', 'slug', 'पता (स्लग)', $job['slug'], ['attrs' => ['maxlength' => 150]]) ?><?php endif; ?>
      <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
    </div></section></div>
  </div>
</form>
<?php if (!$isNew && can('careers.delete')): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">वैकेंसी हटेगी; आवेदन इनबॉक्स में रहेंगे।</p></div><?= delete_button(route('admin.careers.destroy', ['id' => $job['id']]), 'वैकेंसी हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
