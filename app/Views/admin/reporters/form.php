<?php
use App\Models\Reporter;
$this->layout('layouts/admin');
$isNew = $r === null;
$title = $isNew ? 'मौजूदा यूज़र को रिपोर्टर बनाएँ' : 'रिपोर्टर बदलें';
$months = (int) setting('reporter_validity_months', 12);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.reporters.index')) ?>">रिपोर्टर</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($r['reporter_code']) ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <?php if ($isNew): ?><p>नए व्यक्ति के लिए सही तरीक़ा: वेबसाइट का <a href="<?= e(route('join')) ?>" target="_blank" rel="noopener">रिपोर्टर फ़ॉर्म</a> (KYC के साथ)। यहाँ सिर्फ़ पहले से बने यूज़र (जैसे पुराना स्टाफ़)।</p><?php endif; ?>
  </div>
</div>
<section class="panel" style="max-width:900px">
  <form class="panel-body" method="post" action="<?= e($isNew ? route('admin.reporters.store') : route('admin.reporters.update', ['id' => $r['id']])) ?>" novalidate data-unsaved>
    <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
    <div class="row">
      <?php if ($isNew): ?><div class="col-md-12"><?= field('select', 'user_id', 'यूज़र', '', ['required' => true, 'options' => array_combine(array_column($users, 'id'), array_map(fn($u) => $u['name'] . ' (' . $u['email'] . ')', $users)) ?: []]) ?></div><?php endif; ?>
      <div class="col-md-6"><?= field('text', 'designation', 'पद', $r['designation'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 100]]) ?></div>
      <div class="col-md-6"><?= field('select', 'reporter_type', 'प्रकार', $r['reporter_type'] ?? 'district', ['options' => Reporter::TYPES, 'required' => true]) ?></div>
      <div class="col-md-6"><?= field('select', 'beat_category_id', 'बीट', $r['beat_category_id'] ?? '', ['options' => $categories, 'empty' => '—']) ?></div>
      <div class="col-md-6"><?= field('select', 'bureau_id', 'ब्यूरो', $r['bureau_id'] ?? '', ['options' => $bureaus, 'empty' => '—']) ?></div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="aLoc">रिपोर्टिंग क्षेत्र</label>
        <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
          <input type="hidden" name="area_location_id" value="<?= e(old('area_location_id', $area['id'] ?? '')) ?>">
          <input type="search" class="form-control" id="aLoc" autocomplete="off" value="<?= e($area['name'] ?? '') ?>" placeholder="ज़िला/शहर खोजें…" role="combobox" aria-expanded="false">
          <ul class="loc-results list-group" role="listbox" hidden></ul>
        </div>
        <div class="form-text">ज़िला और राज्य इसी से अपने आप</div>
      </div>
      <div class="col-md-3"><?= field('tel', 'mobile', 'मोबाइल', $r['mobile'] ?? '', ['required' => !$isNew, 'help' => $isNew ? 'ख़ाली = यूज़र का मोबाइल' : 'सत्यापन इसी से']) ?></div>
      <div class="col-md-3"><?= field('select', 'blood_group', 'ब्लड ग्रुप', $r['blood_group'] ?? '', ['options' => array_combine($bg = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], $bg), 'empty' => '—']) ?></div>
      <?php if ($isNew): ?>
        <div class="col-md-6"><?= field('date', 'joining_date', 'जॉइनिंग', date('Y-m-d'), ['required' => true]) ?></div>
        <div class="col-md-6"><?= field('date', 'valid_until', 'वैधता', date('Y-m-d', strtotime("+$months months")), ['required' => true]) ?></div>
      <?php endif; ?>
      <div class="col-12"><?= field('textarea', 'address', 'पता', $r['address'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 400]]) ?></div>
      <div class="col-12"><?= field('textarea', 'notes', 'आंतरिक नोट', $r['notes'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 2000]]) ?></div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
      <a class="btn btn-light" href="<?= e($isNew ? route('admin.reporters.index') : route('admin.reporters.show', ['id' => $r['id']])) ?>">वापस</a>
    </div>
  </form>
</section>
