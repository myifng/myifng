<?php $this->layout('layouts/auth'); $rp = ($portal ?? 'admin') === 'reporter' ? 'reporter.' : 'admin.'; $title = 'पासवर्ड भूल गए'; ?>
<h1 class="h3 fw-bold mb-1">पासवर्ड भूल गए?</h1>
<p class="text-body-secondary mb-4">अपना ईमेल लिखें। हम पासवर्ड बदलने का लिंक भेजेंगे, जो 60 मिनट तक चलेगा।</p>
<form method="post" action="<?= e(route($rp . 'password.email')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('email', 'email', 'ईमेल', '', ['required' => true, 'attrs' => ['autocomplete' => 'email', 'autofocus' => true], 'prefix' => '<i class="fa-regular fa-envelope"></i>']) ?>
  <button class="btn btn-brand w-100 btn-lg" type="submit">रीसेट लिंक भेजें</button>
</form>
<p class="text-center mt-4 mb-0"><a href="<?= e(route($rp . 'login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>लॉगिन पर वापस जाएँ</a></p>
