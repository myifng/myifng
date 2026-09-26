<?php
$this->layout('layouts/admin');
$isNew = $user === null;
$title = $isNew ? 'नया यूज़र' : 'यूज़र बदलें';
$self = !$isNew && (int) $user['id'] === auth()->id();
$roleOpts = [];
foreach ($roles as $r) {
    $roleOpts[$r['id']] = $r['name'];
}
if (!$isNew && !isset($roleOpts[$user['role_id']])) {
    $roleOpts[$user['role_id']] = $user['role_name'];
}
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.users.index')) ?>">यूज़र</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($title) ?></li></ol></nav>
    <h1><?= e($isNew ? 'नया यूज़र' : $user['name']) ?></h1>
    <?php if (!$isNew): ?><p><?= e($user['email']) ?> · बना: <?= hindi_date($user['created_at']) ?></p><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e($isNew ? route('admin.users.store') : route('admin.users.update', ['id' => $user['id']])) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <section class="panel">
        <div class="panel-head"><h2>बुनियादी जानकारी</h2></div>
        <div class="panel-body">
          <div class="row">
            <div class="col-md-6"><?= field('text', 'name', 'पूरा नाम', $user['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?></div>
            <div class="col-md-6"><?= field('email', 'email', 'ईमेल (लॉगिन के लिए)', $user['email'] ?? '', ['required' => true, 'attrs' => ['autocomplete' => 'off']]) ?></div>
            <div class="col-md-6"><?= field('tel', 'mobile', 'मोबाइल', $user['mobile'] ?? '', ['placeholder' => '10 अंकों का नंबर', 'attrs' => ['inputmode' => 'numeric', 'maxlength' => 14]]) ?></div>
            <div class="col-md-6"><?= field('file', 'avatar', 'फ़ोटो', null, ['help' => 'JPG, PNG या WebP, 10 MB तक', 'attrs' => ['accept' => 'image/*']]) ?></div>
            <div class="col-12"><?= field('textarea', 'bio', 'परिचय', $user['bio'] ?? '', ['rows' => 2, 'help' => 'रिपोर्टर प्रोफ़ाइल और लेखक बॉक्स में दिखेगा', 'attrs' => ['maxlength' => 500]]) ?></div>
          </div>
        </div>
      </section>
      <section class="panel mt-3">
        <div class="panel-head"><h2><?= $isNew ? 'पासवर्ड' : 'पासवर्ड बदलें' ?></h2></div>
        <div class="panel-body">
          <?php if (!$isNew): ?><p class="text-body-secondary small">पासवर्ड नहीं बदलना हो तो दोनों खाने ख़ाली छोड़ दें।</p><?php endif; ?>
          <div class="row">
            <div class="col-md-6"><?= field('password', 'password', 'पासवर्ड', '', ['required' => $isNew, 'help' => 'कम से कम 8 अक्षर, अक्षर और अंक दोनों', 'attrs' => ['autocomplete' => 'new-password']]) ?></div>
            <div class="col-md-6"><?= field('password', 'password_confirmation', 'पासवर्ड दोबारा', '', ['required' => $isNew, 'attrs' => ['autocomplete' => 'new-password']]) ?></div>
          </div>
        </div>
      </section>
    </div>
    <div class="col-lg-4">
      <section class="panel">
        <div class="panel-head"><h2>रोल और स्थिति</h2></div>
        <div class="panel-body">
          <?php if (!$isNew && $user['avatar']): ?><div class="text-center mb-3"><?= avatar_html($user['avatar'], $user['name'], 'xl') ?></div><?php endif; ?>
          <?= field('select', 'role_id', 'रोल', $user['role_id'] ?? '', ['required' => true, 'options' => $roleOpts, 'empty' => 'रोल चुनें', 'attrs' => $self ? ['disabled' => true] : []]) ?>
          <?php if ($self): ?><input type="hidden" name="role_id" value="<?= (int) $user['role_id'] ?>"><?php endif; ?>
          <?= field('select', 'status', 'स्थिति', $user['status'] ?? 'active', ['required' => true, 'options' => App\Models\User::STATUSES, 'attrs' => $self ? ['disabled' => true] : []]) ?>
          <?php if ($self): ?><input type="hidden" name="status" value="active"><p class="small text-body-secondary mb-0">अपना रोल और स्थिति आप ख़ुद नहीं बदल सकते।</p><?php endif; ?>
        </div>
      </section>
      <?php if (!$isNew && !empty($history)): ?>
      <section class="panel mt-3">
        <div class="panel-head"><h2>हाल के लॉगिन</h2></div>
        <ul class="activity compact">
          <?php foreach ($history as $h): ?><li><div><?= status_badge($h['status']) ?> <?= e(device_name($h['user_agent'])) ?><small><?= hindi_date($h['created_at'], true) ?> · <?= e($h['ip']) ?></small></div></li><?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn btn-light" href="<?= e(route('admin.users.index')) ?>">रद्द करें</a>
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i><?= $isNew ? 'यूज़र बनाएँ' : 'बदलाव सेव करें' ?></button>
  </div>
</form>
