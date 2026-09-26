<?php $this->layout('layouts/auth'); $title = 'लॉगिन'; ?>
<h1 class="h3 fw-bold mb-1">न्यूज़रूम में लॉगिन करें</h1>
<p class="text-body-secondary mb-4">अपने ईमेल और पासवर्ड से लॉगिन करें।</p>
<form method="post" action="<?= e(route('admin.login.submit')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('email', 'email', 'ईमेल', '', ['required' => true, 'attrs' => ['autocomplete' => 'username', 'autofocus' => true], 'prefix' => '<i class="fa-regular fa-envelope"></i>']) ?>
  <div class="mb-2">
    <?= field('password', 'password', 'पासवर्ड', '', ['required' => true, 'wrap' => '', 'attrs' => ['autocomplete' => 'current-password'], 'prefix' => '<i class="fa-solid fa-lock"></i>']) ?>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <label class="form-check small mb-0"><input class="form-check-input" type="checkbox" data-show-password="#f_password"> <span class="form-check-label">पासवर्ड दिखाएँ</span></label>
    <a class="small" href="<?= e(route('admin.password.forgot')) ?>">पासवर्ड भूल गए?</a>
  </div>
  <button class="btn btn-brand w-100 btn-lg" type="submit">लॉगिन करें <i class="fa-solid fa-arrow-right ms-1"></i></button>
</form>
