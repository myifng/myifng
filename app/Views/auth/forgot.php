<?php $this->layout('layouts/auth'); $rp = ($portal ?? 'admin') === 'reporter' ? 'reporter.' : 'admin.'; $title = 'पासवर्ड भूल गए'; ?>
<div class="auth-head">
  <span class="auth-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
  <div><span class="auth-badge staff">खाता वापस पाएँ</span><h1 class="auth-title">पासवर्ड भूल गए?</h1></div>
</div>
<p class="auth-sub">अपना ईमेल लिखें। हम पासवर्ड बदलने का लिंक भेजेंगे, जो <b>60 मिनट</b> तक चलेगा।</p>
<form method="post" action="<?= e(route($rp . 'password.email')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('email', 'email', 'ईमेल', '', ['required' => true, 'placeholder' => 'aapka@email.com', 'attrs' => ['autocomplete' => 'email', 'autofocus' => true, 'inputmode' => 'email'], 'prefix' => '<i class="fa-regular fa-envelope"></i>']) ?>
  <button class="btn btn-brand w-100 btn-lg auth-submit" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> रीसेट लिंक भेजें</button>
</form>
<ol class="auth-steps-mini">
  <li>ईमेल में आया लिंक खोलें</li>
  <li>नया पासवर्ड बनाएँ</li>
  <li>नए पासवर्ड से लॉगिन करें</li>
</ol>
<div class="auth-alt"><a href="<?= e(route($rp . 'login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>लॉगिन पर वापस जाएँ</a></div>
