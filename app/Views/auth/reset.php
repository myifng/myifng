<?php $this->layout('layouts/auth'); $rp = ($portal ?? 'admin') === 'reporter' ? 'reporter.' : 'admin.'; $title = 'नया पासवर्ड'; ?>
<div class="auth-head">
  <span class="auth-icon"><i class="fa-solid fa-lock-open" aria-hidden="true"></i></span>
  <div><span class="auth-badge staff">आख़िरी कदम</span><h1 class="auth-title">नया पासवर्ड बनाएँ</h1></div>
</div>
<p class="auth-sub">कम से कम 8 अक्षर, जिनमें अक्षर और अंक दोनों हों।</p>
<form method="post" action="<?= e(route($rp . 'password.update')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <?= field('password', 'password', 'नया पासवर्ड', '', ['required' => true, 'wrap' => 'mb-1', 'attrs' => ['autocomplete' => 'new-password', 'autofocus' => true, 'minlength' => 8], 'prefix' => '<i class="fa-solid fa-lock"></i>', 'suffix' => password_eye('f_password')]) ?>
  <div class="pw-meter-row mb-3"><div class="pw-meter" data-pw-meter="#f_password" aria-hidden="true"><span></span></div><small class="pw-meter-txt" aria-live="polite"></small></div>
  <?= field('password', 'password_confirmation', 'नया पासवर्ड दोबारा', '', ['required' => true, 'attrs' => ['autocomplete' => 'new-password'], 'prefix' => '<i class="fa-solid fa-check-double"></i>', 'suffix' => password_eye('f_password_confirmation')]) ?>
  <button class="btn btn-brand w-100 btn-lg auth-submit" type="submit"><i class="fa-solid fa-check me-1"></i> पासवर्ड बदलें</button>
</form>
<div class="auth-alt"><a href="<?= e(route($rp . 'login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>लॉगिन पर वापस जाएँ</a></div>
