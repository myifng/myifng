<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/admin');
$isNew = $e === null;
$title = $isNew ? 'नया चुनाव' : $e['name'];
$opt = static fn(array $rows) => array_column($rows, 'name', 'id');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.elections.index')) ?>">चुनाव केंद्र</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.elections.store') : route('admin.elections.update', ['id' => $e['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8"><section class="panel"><div class="panel-body">
      <?= field('text', 'name', 'नाम', $e['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: उत्तर प्रदेश विधानसभा चुनाव 2027']) ?>
      <div class="row g-2">
        <div class="col-md-4"><?= field('select', 'type', 'प्रकार', $e['type'] ?? 'vidhan_sabha', ['options' => ES::TYPES]) ?></div>
        <div class="col-md-4"><?= field('number', 'year', 'साल', $e['year'] ?? date('Y'), ['required' => true]) ?></div>
        <div class="col-md-4"><?= field('select', 'state_id', 'राज्य', $e['state_id'] ?? '', ['options' => $opt($states), 'empty' => 'पूरा देश (लोकसभा)']) ?></div>
      </div>
      <div class="row g-2">
        <div class="col-md-8"><?= field('text', 'poll_dates', 'मतदान (तारीख़ें / चरण)', $e['poll_dates'] ?? '', ['placeholder' => 'जैसे: 7 चरण, 10 फ़रवरी – 7 मार्च']) ?></div>
        <div class="col-md-4"><?= field('date', 'counting_date', 'मतगणना', $e['counting_date'] ?? '') ?></div>
      </div>
      <?= field('textarea', 'description', 'परिचय (वेबसाइट पर)', $e['description'] ?? '', ['rows' => 3]) ?>
      <?= field('text', 'source_note', 'डेटा स्रोत (वेबसाइट पर दिखेगा)', $e['source_note'] ?? '', ['placeholder' => 'जैसे: चुनाव आयोग / हमारे संवाददाता']) ?>
    </div></section></div>
    <div class="col-xl-4"><section class="panel sticky-xl"><div class="panel-body">
      <?= field('select', 'status', 'स्थिति', $e['status'] ?? 'draft', ['options' => array_map(static fn($s) => $s[0], ES::STATUSES), 'help' => '"मतगणना जारी" पर वेबसाइट हर 30 सेकंड अपने आप ताज़ा होगी।']) ?>
      <div class="row g-2"><div class="col-6"><?= field('number', 'total_seats', 'कुल सीटें', $e['total_seats'] ?? '', ['help' => 'ख़ाली = जोड़ी गई सीटें']) ?></div>
        <div class="col-6"><?= field('number', 'majority', 'बहुमत', $e['majority'] ?? '', ['help' => 'ख़ाली = आधी + 1']) ?></div></div>
      <?= field('select', 'topic_id', 'चुनाव की ख़बरें (टॉपिक)', $e['topic_id'] ?? '', ['options' => $opt($topics), 'empty' => '—', 'help' => 'इस टॉपिक की ख़बरें चुनाव पेज पर दिखेंगी।']) ?>
      <?= field('switch', 'is_featured', 'मुख्य चुनाव (होमपेज ब्लॉक और /elections में सबसे ऊपर)', $e['is_featured'] ?? 0) ?>
      <?php if (!$isNew): ?><?= field('text', 'slug', 'पता (स्लग)', $e['slug']) ?><?php endif; ?>
      <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
    </div></section></div>
  </div>
</form>
<?php if (!$isNew && can('elections.delete')): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">इस चुनाव के उम्मीदवार और नतीजे भी हटेंगे।</p></div><?= delete_button(route('admin.elections.destroy', ['id' => $e['id']]), 'चुनाव और उसके सारे नतीजे हट जाएँगे।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
