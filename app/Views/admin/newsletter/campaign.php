<?php
$this->layout('layouts/admin');
$isNew = $c === null;
$title = $isNew ? 'नया न्यूज़लेटर' : 'न्यूज़लेटर बदलें';
$sel = array_map('intval', (array) old('lists', $c ? array_filter(explode(',', (string) $c['list_ids'])) : array_column(array_filter($lists, fn($l) => $l['is_default']), 'id')));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.newsletter.index')) ?>">न्यूज़लेटर</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($isNew ? 'नया न्यूज़लेटर' : $c['subject']) ?></h1>
    <?php if (!$isNew && $c['status'] === 'scheduled'): ?><p class="text-info-emphasis"><i class="fa-regular fa-clock"></i> <?= e(hindi_date($c['scheduled_at'], true)) ?> पर भेजा जाएगा (बदलने पर शेड्यूल हट जाएगा)।</p><?php endif; ?>
  </div>
  <?php if (!$isNew): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.newsletter.show', ['id' => $c['id']])) ?>"><i class="fa-regular fa-eye me-1"></i> प्रीव्यू</a><?php endif; ?>
</div>
<form method="post" action="<?= e($isNew ? route('admin.newsletter.store') : route('admin.newsletter.update', ['id' => $c['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8"><section class="panel"><div class="panel-body">
      <?= field('text', 'subject', 'विषय (Subject)', $c['subject'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 200, 'data-count' => 60], 'placeholder' => 'जैसे: आज की 10 बड़ी ख़बरें']) ?>
      <?= field('text', 'preheader', 'प्रीहेडर (इनबॉक्स में विषय के बाद दिखने वाली लाइन)', $c['preheader'] ?? '', ['attrs' => ['maxlength' => 200]]) ?>
      <span class="form-label d-block">सामग्री <?= error('content') ? '<span class="text-danger small">' . e(error('content')) . '</span>' : '' ?></span>
      <div class="rte mb-3" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>"><textarea name="content" class="rte-source" aria-label="सामग्री"><?= e(old('content', $c['content'] ?? '')) ?></textarea></div>
      <?= field('select', 'include_top', 'नीचे "आज की बड़ी ख़बरें" (पिछले 24 घंटे की सबसे ज़्यादा पढ़ी) जोड़ें', (string) ($c['include_top'] ?? 5), ['options' => ['0' => 'नहीं', '3' => '3 ख़बरें', '5' => '5 ख़बरें', '8' => '8 ख़बरें', '10' => '10 ख़बरें']]) ?>
      <p class="form-text">ख़ाली सामग्री + टॉप ख़बरें = अपने आप बनने वाला रोज़ का डाइजेस्ट।</p>
    </div></section></div>
    <div class="col-xl-4"><section class="panel sticky-xl"><div class="panel-body">
      <span class="form-label d-block">किन सूचियों को</span>
      <?php foreach ($lists as $l): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="lists[]" value="<?= (int) $l['id'] ?>"<?= in_array((int) $l['id'], $sel, true) ? ' checked' : '' ?>> <span class="form-check-label"><?= e($l['name']) ?> <small class="text-body-secondary">(<?= num($l['subs']) ?>)</small></span></label><?php endforeach; ?>
      <p class="form-text">कुछ न चुनें = सभी सब्सक्राइबर।</p>
      <?= field('select', 'template_id', 'टेम्पलेट', $c['template_id'] ?? '', ['options' => $templates]) ?>
      <button class="btn btn-dark w-100 mb-2" type="submit" name="then" value=""><i class="fa-solid fa-floppy-disk me-1"></i> ड्राफ़्ट सेव करें</button>
      <div class="input-group mb-2"><input class="form-control" type="email" name="test_email" placeholder="<?= e(auth()->user()['email']) ?>" aria-label="टेस्ट ईमेल"><button class="btn btn-outline-secondary" type="submit" name="then" value="test">टेस्ट भेजें</button></div>
      <?php if (can('newsletter.manage')): ?>
        <hr><button class="btn btn-brand w-100 mb-2" type="submit" name="then" value="send" data-confirm-then="चुनी सूचियों के सभी सब्सक्राइबर को भेजना शुरू होगा।"><i class="fa-solid fa-paper-plane me-1"></i> सेव करके अभी भेजें</button>
        <div class="input-group"><input class="form-control" type="datetime-local" name="scheduled_at" aria-label="भेजने का समय"><button class="btn btn-outline-secondary" type="submit" name="then" value="schedule">तय समय पर</button></div>
      <?php endif; ?>
    </div></section></div>
  </div>
</form>
<?php if (!$isNew && can('newsletter.delete')): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b></div><?= delete_button(route('admin.newsletter.destroy', ['id' => $c['id']]), 'कैंपेन हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
