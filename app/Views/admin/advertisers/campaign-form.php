<?php
use App\Models\AdCampaign;
$this->layout('layouts/admin');
$isNew = $c === null;
$title = $isNew ? 'नया कैंपेन' : $c['name'];
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया कैंपेन' : 'बदलें' ?></li></ol></nav>
  <h1><?= e($title) ?></h1></div></div>
<div class="row"><div class="col-xl-8"><section class="panel"><div class="panel-body">
<?php if (!$advertisers): ?><p>पहले <a href="<?= e(route('admin.advertisers.create')) ?>">विज्ञापनदाता</a> जोड़ें।</p><?php else: ?>
<form method="post" action="<?= e($isNew ? route('admin.campaigns.store') : route('admin.campaigns.update', ['id' => $c['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-2">
    <div class="col-md-6"><?= field('select', 'advertiser_id', 'विज्ञापनदाता', $advertiser ?: '', ['options' => $advertisers, 'empty' => 'चुनें…', 'required' => true]) ?></div>
    <div class="col-md-6"><?= field('text', 'name', 'कैंपेन का नाम', $c['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: दीवाली ऑफ़र 2026', 'attrs' => ['maxlength' => 190]]) ?></div>
    <div class="col-md-4"><?= field('select', 'pricing', 'भाव का तरीका', $c['pricing'] ?? 'fixed', ['options' => AdCampaign::PRICING]) ?></div>
    <div class="col-md-4" data-when="pricing:cpm,cpc"><?= field('number', 'rate', 'भाव (₹)', $c['rate'] ?? '', ['attrs' => ['min' => 0, 'step' => '0.01'], 'help' => 'CPM: 1000 इम्प्रेशन का; CPC: एक क्लिक का']) ?></div>
    <div class="col-md-4"><?= field('number', 'budget', 'बजट / तय राशि (₹)', $c['budget'] ?? '', ['attrs' => ['min' => 0, 'step' => '0.01'], 'help' => 'CPM/CPC में ख़र्च इस तक पहुँचते ही विज्ञापन रुकेंगे; 0 = बिना सीमा']) ?></div>
    <div class="col-md-4"><?= field('date', 'start_date', 'शुरू', $c['start_date'] ?? date('Y-m-d')) ?></div>
    <div class="col-md-4"><?= field('date', 'end_date', 'ख़त्म', $c['end_date'] ?? '') ?></div>
    <div class="col-md-4"><?= field('select', 'status', 'स्थिति', $c['status'] ?? 'active', ['options' => AdCampaign::STATUSES]) ?></div>
  </div>
  <?= field('textarea', 'notes', 'नोट्स (डील की शर्तें)', $c['notes'] ?? '', ['rows' => 3, 'attrs' => ['maxlength' => 5000]]) ?>
  <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button><a class="btn btn-light" href="<?= e($isNew ? route('admin.advertisers.index') : route('admin.campaigns.show', ['id' => $c['id']])) ?>">वापस</a></div>
</form>
<?php endif; ?>
</div></section></div></div>
