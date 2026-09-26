<?php
use App\Models\Bureau;
$this->layout('layouts/admin');
$isNew = $b === null;
$title = $isNew ? 'नया ब्यूरो' : 'ब्यूरो: ' . $b['name'];
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.bureaus.index')) ?>">ब्यूरो</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($b['name']) ?></li></ol></nav>
  <h1><?= e($title) ?></h1></div></div>
<section class="panel" style="max-width:900px">
  <form class="panel-body" method="post" action="<?= e($isNew ? route('admin.bureaus.store') : route('admin.bureaus.update', ['id' => $b['id']])) ?>" novalidate>
    <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
    <div class="row">
      <div class="col-md-6"><?= field('text', 'name', 'नाम', $b['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: गोरखपुर ब्यूरो', 'attrs' => ['maxlength' => 150]]) ?></div>
      <div class="col-md-3"><?= field('select', 'type', 'प्रकार', $b['type'] ?? 'district', ['options' => Bureau::TYPES, 'required' => true]) ?></div>
      <div class="col-md-3"><?= field('select', 'status', 'स्थिति', $b['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
      <div class="col-md-6"><?= field('select', 'parent_id', 'ऊपर वाला ब्यूरो', $parentId, ['options' => $parents, 'empty' => '— (सिर्फ़ मुख्यालय के लिए ख़ाली)']) ?></div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="bLoc">लोकेशन</label>
        <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
          <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
          <input type="search" class="form-control" id="bLoc" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="खोजें…" role="combobox" aria-expanded="false">
          <ul class="loc-results list-group" role="listbox" hidden></ul>
        </div>
      </div>
      <div class="col-md-6"><?= field('select', 'chief_user_id', 'ब्यूरो प्रमुख', $b['chief_user_id'] ?? '', ['options' => $chiefs, 'empty' => '—']) ?></div>
      <div class="col-md-3"><?= field('tel', 'phone', 'फ़ोन', $b['phone'] ?? '', ['attrs' => ['maxlength' => 20]]) ?></div>
      <div class="col-md-3"><?= field('email', 'email', 'ईमेल', $b['email'] ?? '') ?></div>
      <div class="col-12"><?= field('textarea', 'address', 'पता', $b['address'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 300]]) ?></div>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><a class="btn btn-light" href="<?= e(route('admin.bureaus.index')) ?>">वापस</a></div>
  </form>
</section>
