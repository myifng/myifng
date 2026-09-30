<?php
use App\Services\FormService;
$this->layout('layouts/admin');
$isNew = $form === null;
$title = $isNew ? 'नया फ़ॉर्म' : 'फ़ॉर्म: ' . $form['title'];
$system = !$isNew && $form['is_system'];
$oldFields = old('fields');
$rows = is_array($oldFields) ? array_values($oldFields) : $fields;
$types = array_diff_key(FormService::TYPES, $isNew ? ['career' => 1] : []);
$row = static function (array $f, string $i) {
    $opt = static fn(array $list, $cur) => implode('', array_map(static fn($k, $l) => '<option value="' . e((string) $k) . '"' . ((string) $k === (string) $cur ? ' selected' : '') . '>' . e($l) . '</option>', array_keys($list), $list));
    $n = 'fields[' . $i . ']';
    return '<div class="fb-row" data-fb-row>'
        . '<input type="hidden" name="' . $n . '[id]" value="' . (int) ($f['id'] ?? 0) . '">'
        . '<div class="fb-grip"><button type="button" class="btn btn-sm btn-light" data-fb-up aria-label="ऊपर"><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="btn btn-sm btn-light" data-fb-down aria-label="नीचे"><i class="fa-solid fa-arrow-down"></i></button></div>'
        . '<div class="fb-main row g-2">'
        . '<div class="col-md-5"><label class="form-label small">लेबल</label><input class="form-control form-control-sm" name="' . $n . '[label]" value="' . e((string) ($f['label'] ?? '')) . '" maxlength="190" required></div>'
        . '<div class="col-md-4"><label class="form-label small">प्रकार</label><select class="form-select form-select-sm" name="' . $n . '[type]" data-fb-type>' . $opt(FormService::FIELD_TYPES, $f['type'] ?? 'text') . '</select></div>'
        . '<div class="col-md-3"><label class="form-label small">key (अंग्रेज़ी)</label><input class="form-control form-control-sm font-monospace" name="' . $n . '[field_key]" value="' . e((string) ($f['field_key'] ?? '')) . '" maxlength="60" pattern="[a-z][a-z0-9_]*" placeholder="अपने आप"></div>'
        . '<div class="col-12" data-fb-when="select,radio,checkbox"><label class="form-label small">विकल्प (एक लाइन में एक)</label><textarea class="form-control form-control-sm" rows="3" name="' . $n . '[options]">' . e((string) ($f['options'] ?? '')) . '</textarea></div>'
        . '<div class="col-md-6" data-fb-when="file"><label class="form-label small">कैसी फ़ाइल</label><select class="form-select form-select-sm" name="' . $n . '[accept]">' . $opt(FormService::FILE_KINDS, $f['accept'] ?? 'image,document') . '</select></div>'
        . '<div class="col-md-3" data-fb-when="file"><label class="form-label small">अधिकतम MB</label><input class="form-control form-control-sm" type="number" min="1" max="100" name="' . $n . '[max_mb]" value="' . (int) ($f['max_mb'] ?? 5) . '"></div>'
        . '<div class="col-md-6"><label class="form-label small">मदद टेक्स्ट</label><input class="form-control form-control-sm" name="' . $n . '[help]" value="' . e((string) ($f['help'] ?? '')) . '" maxlength="300"></div>'
        . '<div class="col-md-3"><label class="form-label small">चौड़ाई</label><select class="form-select form-select-sm" name="' . $n . '[width]">' . $opt([12 => 'पूरी', 6 => 'आधी'], $f['width'] ?? 12) . '</select></div>'
        . '<div class="col-md-3 d-flex align-items-end"><label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="' . $n . '[required]" value="1"' . (!empty($f['required']) ? ' checked' : '') . '> ज़रूरी</label></div>'
        . '</div><button type="button" class="btn btn-sm btn-outline-danger fb-del" data-fb-del aria-label="खाना हटाएँ"><i class="fa-solid fa-xmark"></i></button></div>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.forms.index')) ?>">फ़ॉर्म</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($isNew ? 'नया फ़ॉर्म' : $form['title']) ?></h1>
    <?php if (!$isNew): ?><p>पता: <a href="<?= e(route('form.show', ['slug' => $form['slug']])) ?>" target="_blank" rel="noopener">/form/<?= e($form['slug']) ?></a> · शॉर्टकोड <code>[form:<?= e($form['slug']) ?>]</code><?= $system ? ' · सिस्टम फ़ॉर्म (प्रकार/पता तय)' : '' ?></p><?php endif; ?>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.forms.store') : route('admin.forms.update', ['id' => $form['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-head"><h2>खाने</h2><span class="small text-body-secondary">ऊपर/नीचे तीर से क्रम बदलें</span></div><div class="panel-body">
        <?php if (error('fields')): ?><div class="alert alert-danger small py-2"><?= e(error('fields')) ?></div><?php endif; ?>
        <div class="fb-list" data-fb-list><?php foreach ($rows as $i => $f): ?><?= $row($f, (string) $i) ?><?php endforeach; ?></div>
        <template data-fb-template><?= $row(['type' => 'text', 'width' => 12], '__i__') ?></template>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-fb-add><i class="fa-solid fa-plus me-1"></i> खाना जोड़ें</button>
        <p class="form-text mt-2">key "name", "email", "mobile" वाले खाने सूची में भेजने वाले के नाम/संपर्क की तरह दिखते हैं; पावती ईमेल "email" पर जाती है।</p>
      </div></section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $form['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 190]]) ?>
        <?php if (!$system): ?>
          <?= field('text', 'slug', 'पता (स्लग, अंग्रेज़ी)', $form['slug'] ?? '', ['placeholder' => 'अपने आप', 'attrs' => ['maxlength' => 100]]) ?>
          <?= field('select', 'type', 'प्रकार (किस इनबॉक्स में जाए)', $form['type'] ?? 'custom', ['options' => array_map(static fn($t) => $t[0], $types)]) ?>
        <?php else: ?><input type="hidden" name="type" value="<?= e($form['type']) ?>"><?php endif; ?>
        <?= field('textarea', 'description', 'ऊपर का विवरण', strip_tags((string) ($form['description'] ?? '')), ['rows' => 3]) ?>
        <?= field('text', 'success_message', 'भेजने के बाद संदेश', $form['success_message'] ?? '', ['attrs' => ['maxlength' => 500]]) ?>
        <?= field('text', 'submit_label', 'बटन पर', $form['submit_label'] ?? 'भेजें', ['attrs' => ['maxlength' => 60]]) ?>
        <?= field('text', 'notify_emails', 'इन ईमेल पर भी सूचना (कॉमा से)', $form['notify_emails'] ?? '', ['placeholder' => 'desk@yoursite.com']) ?>
        <?= field('select', 'status', 'स्थिति', $form['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button>
      </div></section>
    </div>
  </div>
</form>
<?php if (!$isNew && !$system && can('forms.delete')): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">फ़ॉर्म और उसके सारे जमा फ़ॉर्म हट जाएँगे।</p></div><?= delete_button(route('admin.forms.destroy', ['id' => $form['id']]), 'फ़ॉर्म और जमा डेटा हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
