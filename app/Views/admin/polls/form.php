<?php
use App\Models\Poll;
$this->layout('layouts/admin');
$isNew = $poll === null;
$title = $isNew ? 'नया पोल' : 'पोल बदलें';
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
$oldOpts = old('option');
$rows = is_array($oldOpts) ? array_map(static fn($l, $i) => ['id' => $i, 'label' => $l, 'votes' => 0], $oldOpts, (array) old('option_id', array_fill(0, count($oldOpts), 0))) : $options;
while (count($rows) < 2) { $rows[] = ['id' => 0, 'label' => '', 'votes' => 0]; }
$total = max(1, array_sum(array_map(static fn($o) => (int) $o['votes'], $options)));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.polls.index')) ?>">पोल</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($isNew ? 'नया पोल' : $poll['question']) ?></h1>
    <?php if (!$isNew): ?><p>शॉर्टकोड <code>[poll:<?= (int) $poll['id'] ?>]</code> · <?= num($poll['voters']) ?> मतदाता</p><?php endif; ?>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.polls.store') : route('admin.polls.update', ['id' => $poll['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'question', 'सवाल', $poll['question'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 300], 'placeholder' => 'जैसे: क्या शहर में नया फ़्लाईओवर ज़रूरी है?']) ?>
        <?= field('textarea', 'description', 'विवरण (वैकल्पिक)', $poll['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 500]]) ?>
        <span class="form-label d-block">विकल्प (2-10)</span>
        <?php if (error('option')): ?><div class="text-danger small mb-2"><?= e(error('option')) ?></div><?php endif; ?>
        <div class="poll-opts" data-poll-opts>
          <?php foreach ($rows as $o): ?>
            <div class="input-group mb-2 poll-opt-row"><input type="hidden" name="option_id[]" value="<?= (int) $o['id'] ?>">
              <input class="form-control" name="option[]" value="<?= e($o['label']) ?>" maxlength="200" aria-label="विकल्प">
              <?php if ((int) $o['votes']): ?><span class="input-group-text small"><?= num($o['votes']) ?> वोट</span><?php endif; ?>
              <button class="btn btn-outline-secondary" type="button" data-opt-remove aria-label="विकल्प हटाएँ"><i class="fa-solid fa-xmark"></i></button></div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-opt-add><i class="fa-solid fa-plus me-1"></i> विकल्प जोड़ें</button>
        <?php if (!$isNew && $poll['voters']): ?><p class="form-text text-warning-emphasis mt-2">वोट पड़ चुके हैं: विकल्प का नाम बदलने पर उसके वोट वही रहेंगे; विकल्प हटाने पर उसके वोट हट जाएँगे।</p><?php endif; ?>
      </div></section>
      <?php if (!$isNew && $options): ?>
      <section class="panel mt-3"><div class="panel-head"><h2>नतीजे</h2><a class="small" href="<?= e(route('admin.polls.export', ['id' => $poll['id']])) ?>">CSV</a></div><div class="panel-body">
        <?php foreach ($options as $o): $pc = round(100 * (int) $o['votes'] / $total); ?>
          <div class="mb-2"><div class="d-flex justify-content-between small"><span><?= e($o['label']) ?></span><b><?= num($o['votes']) ?> · <?= $pc ?>%</b></div><div class="progress" style="height:8px"><div class="progress-bar bg-danger" style="width:<?= $pc ?>%"></div></div></div>
        <?php endforeach; ?>
      </div></section>
      <?php endif; ?>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl"><div class="panel-body">
        <?= field('select', 'status', 'स्थिति', $poll['status'] ?? 'draft', ['options' => Poll::STATUSES]) ?>
        <?= field('datetime-local', 'start_at', 'शुरू (ख़ाली = अभी से)', $dt($poll['start_at'] ?? null)) ?>
        <?= field('datetime-local', 'end_at', 'ख़त्म (ख़ाली = बिना अंत)', $dt($poll['end_at'] ?? null)) ?>
        <?= field('select', 'show_results', 'नतीजे कब दिखें', $poll['show_results'] ?? 'after_vote', ['options' => Poll::RESULTS]) ?>
        <?= field('switch', 'multiple', 'एक से ज़्यादा विकल्प चुन सकते हैं', (int) ($poll['multiple'] ?? 0)) ?>
        <?= field('switch', 'require_login', 'वोट के लिए पाठक लॉगिन ज़रूरी (सबसे भरोसेमंद)', (int) ($poll['require_login'] ?? 0)) ?>
        <p class="form-text">एक ब्राउज़र/नेटवर्क से एक वोट। लॉगिन ज़रूरी हो तो एक खाते से एक।</p>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button>
      </div></section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('polls.delete')): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">पोल, विकल्प और सारे वोट हट जाएँगे; ख़बरों में लगा शॉर्टकोड कुछ नहीं दिखाएगा।</p></div>
  <?= delete_button(route('admin.polls.destroy', ['id' => $poll['id']]), 'पोल हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
