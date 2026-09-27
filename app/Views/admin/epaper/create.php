<?php
use App\Models\EpaperIssue;
$this->layout('layouts/admin');
$title = 'नया ई-पेपर अंक';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.epaper.index')) ?>">ई-पेपर</a></li><li class="breadcrumb-item active" aria-current="page">नया अंक</li></ol></nav>
    <h1>नया अंक</h1>
    <p>पहले संस्करण और तारीख़ चुनें; अगले पेज पर PDF अपलोड करें।</p>
  </div>
</div>
<div class="row"><div class="col-xl-6">
<section class="panel"><div class="panel-body">
  <?php if (!$editions): ?><p>पहले <a href="<?= e(route('admin.epaper.editions')) ?>">संस्करण</a> बनाएँ।</p><?php else: ?>
  <form method="post" action="<?= e(route('admin.epaper.store')) ?>" novalidate>
    <?= csrf_field() ?>
    <?= field('select', 'edition_id', 'संस्करण', $edition ?: array_key_first($editions), ['options' => $editions, 'required' => true]) ?>
    <?= field('date', 'issue_date', 'तारीख़', $date, ['required' => true]) ?>
    <?= field('text', 'title', 'शीर्षक (वैकल्पिक)', '', ['placeholder' => 'जैसे: दीपावली विशेषांक', 'attrs' => ['maxlength' => 190]]) ?>
    <?= field('select', 'access', 'पहुँच', 'free', ['options' => EpaperIssue::ACCESS, 'help' => 'प्रीमियम: सिर्फ़ पहले कुछ पेज सबको (सेटिंग); बाकी सदस्यों के लिए (पाठक सदस्यता आगे जुड़ेगी)']) ?>
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-arrow-right me-1"></i> आगे: पेज अपलोड</button>
  </form>
  <?php endif; ?>
</div></section>
</div></div>
