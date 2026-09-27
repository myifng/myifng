<?php
use App\Models\Advertiser;
$this->layout('layouts/admin');
$isNew = $adv === null;
$title = $isNew ? 'नया विज्ञापनदाता' : $adv['company'];
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
  <h1><?= e($title) ?></h1></div></div>
<div class="row"><div class="col-xl-8"><section class="panel"><div class="panel-body">
<form method="post" action="<?= e($isNew ? route('admin.advertisers.store') : route('admin.advertisers.update', ['id' => $adv['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-2">
    <div class="col-md-8"><?= field('text', 'company', 'कंपनी / संस्था', $adv['company'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 190]]) ?></div>
    <div class="col-md-4"><?= field('select', 'status', 'स्थिति', $adv['status'] ?? 'active', ['options' => Advertiser::STATUSES]) ?></div>
    <div class="col-md-6"><?= field('text', 'contact_name', 'संपर्क व्यक्ति', $adv['contact_name'] ?? '', ['attrs' => ['maxlength' => 150]]) ?></div>
    <div class="col-md-6"><?= field('tel', 'phone', 'मोबाइल', $adv['phone'] ?? '', ['attrs' => ['maxlength' => 15, 'inputmode' => 'numeric']]) ?></div>
    <div class="col-md-6"><?= field('email', 'email', 'ईमेल', $adv['email'] ?? '', ['attrs' => ['maxlength' => 190]]) ?></div>
    <div class="col-md-6"><?= field('text', 'gstin', 'GSTIN', $adv['gstin'] ?? '', ['placeholder' => '09ABCDE1234F1Z5', 'attrs' => ['maxlength' => 15, 'style' => 'text-transform:uppercase']]) ?></div>
    <div class="col-md-8"><?= field('text', 'address', 'पता', $adv['address'] ?? '', ['attrs' => ['maxlength' => 400]]) ?></div>
    <div class="col-md-4"><?= field('text', 'city', 'शहर', $adv['city'] ?? '', ['attrs' => ['maxlength' => 120]]) ?></div>
  </div>
  <?= field('textarea', 'notes', 'नोट्स (बातचीत, पसंद, छूट…)', $adv['notes'] ?? '', ['rows' => 4, 'attrs' => ['maxlength' => 5000]]) ?>
  <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><?= $isNew ? 'जोड़ें' : 'सेव करें' ?></button><a class="btn btn-light" href="<?= e($isNew ? route('admin.advertisers.index') : route('admin.advertisers.show', ['id' => $adv['id']])) ?>">वापस</a></div>
</form>
</div></section></div></div>
