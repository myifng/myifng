<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box">
    <h1 class="list-title"><i class="fa-solid fa-key"></i> पासवर्ड भूल गए</h1>
    <p class="list-desc">अपना ईमेल लिखें। नया पासवर्ड बनाने का लिंक भेजा जाएगा।</p>
    <form method="post" action="<?= e(route('account.forgot.post')) ?>" class="fgrid one"><?= csrf_field() ?>
      <?= ff('email', 'email', 'ईमेल', ['required' => true, 'attrs' => ['autocomplete' => 'email', 'maxlength' => 190]]) ?>
      <div class="ff-wide auth-actions"><button class="btn" type="submit">लिंक भेजें</button><a href="<?= e(route('account.login')) ?>">लॉगिन पर वापस</a></div>
    </form>
  </div>
</div>
