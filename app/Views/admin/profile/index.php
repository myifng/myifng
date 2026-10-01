<?php $this->layout('layouts/admin'); $title = 'मेरी प्रोफ़ाइल'; ?>
<div class="page-head">
  <div class="d-flex align-items-center gap-3">
    <?= avatar_html($me['avatar'], $me['name'], 'xl') ?>
    <div><h1><?= e($me['name']) ?></h1><p><?= e($me['role_name']) ?> · <?= e($me['email']) ?></p></div>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <form class="panel" method="post" action="<?= e(route('admin.profile.update')) ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2>प्रोफ़ाइल</h2></div>
      <div class="panel-body">
        <div class="row">
          <div class="col-md-6"><?= field('text', 'name', 'पूरा नाम', $me['name'], ['required' => true]) ?></div>
          <div class="col-md-6"><?= field('email', 'email', 'ईमेल', $me['email'], ['required' => true]) ?></div>
          <div class="col-md-6"><?= field('tel', 'mobile', 'मोबाइल', $me['mobile'], ['attrs' => ['inputmode' => 'numeric']]) ?></div>
          <div class="col-md-6"><?= field('file', 'avatar', 'फ़ोटो बदलें', null, ['attrs' => ['accept' => 'image/*']]) ?></div>
          <div class="col-12"><?= field('textarea', 'bio', 'परिचय', $me['bio'], ['rows' => 2]) ?></div>
        </div>
      </div>
      <div class="panel-foot"><button class="btn btn-brand" type="submit">प्रोफ़ाइल सेव करें</button></div>
    </form>
  </div>
  <div class="col-lg-5">
    <form class="panel" method="post" action="<?= e(route('admin.profile.password')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2>पासवर्ड बदलें</h2></div>
      <div class="panel-body">
        <?= field('password', 'current_password', 'मौजूदा पासवर्ड', '', ['required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
        <?= field('password', 'password', 'नया पासवर्ड', '', ['required' => true, 'help' => 'कम से कम 8 अक्षर, अक्षर और अंक दोनों', 'attrs' => ['autocomplete' => 'new-password']]) ?>
        <?= field('password', 'password_confirmation', 'नया पासवर्ड दोबारा', '', ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
      </div>
      <div class="panel-foot"><button class="btn btn-dark" type="submit">पासवर्ड बदलें</button></div>
    </form>
    <?php $tfOn = \App\Services\TwoFactorService::enabled(); $tfDev = \App\Services\TwoFactorService::devices((int) auth()->id()); ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2><i class="fa-solid fa-shield-halved me-1 text-body-secondary"></i>दो-चरण लॉगिन</h2><?= $tfOn ? '<span class="badge text-bg-success">चालू</span>' : '<span class="badge text-bg-light">बंद</span>' ?></div>
      <div class="panel-body small">
        <p class="mb-2"><?= $tfOn ? 'लॉगिन के समय पासवर्ड के बाद आपके ईमेल <b>' . e((string) user('email')) . '</b> पर OTP आता है।' : 'एडमिन ने दो-चरण लॉगिन (ईमेल OTP) अभी चालू नहीं किया है।' ?></p>
        <?php if ($tfDev): ?>
          <form method="post" action="<?= e(route('admin.profile.devices')) ?>" data-confirm="सभी याद रखे गए डिवाइस पर अगली बार फिर OTP माँगा जाएगा।" class="d-flex flex-wrap align-items-center gap-2">
            <?= csrf_field() ?><span>याद रखे गए डिवाइस: <b><?= (int) $tfDev ?></b></span>
            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-xmark me-1"></i>सभी भूलें</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
    <section class="panel mt-3">
      <div class="panel-head"><h2>लॉगिन हिस्ट्री</h2></div>
      <ul class="activity compact">
        <?php foreach ($history as $h): ?><li><div><?= status_badge($h['status']) ?> <?= e(device_name($h['user_agent'])) ?><small><?= hindi_date($h['created_at'], true) ?> · <?= e($h['ip']) ?></small></div></li><?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
