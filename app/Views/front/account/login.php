<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box">
    <h1 class="list-title"><i class="fa-regular fa-user"></i> लॉगिन</h1>
    <p class="list-desc">ख़बरें सेव करें, पसंद की श्रेणी और शहर फ़ॉलो करें, टिप्पणी करें।</p>
    <form method="post" action="<?= e(route('account.login.post')) ?>" class="fgrid one">
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e(old('next', $next)) ?>">
      <?= ff('email', 'email', 'ईमेल', ['required' => true, 'attrs' => ['autocomplete' => 'email', 'maxlength' => 190]]) ?>
      <?= ff('password', 'password', 'पासवर्ड', ['required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
      <div class="ff-wide auth-actions"><button class="btn" type="submit">लॉगिन</button><a href="<?= e(route('account.forgot')) ?>">पासवर्ड भूल गए?</a></div>
    </form>
    <p class="auth-alt">खाता नहीं है? <a href="<?= e(route('account.register')) ?>"><b>नया खाता बनाएँ</b></a></p>
    <details class="auth-resend"><summary>सत्यापन ईमेल नहीं मिला?</summary>
      <form method="post" action="<?= e(route('account.resend')) ?>" class="inline-form"><?= csrf_field() ?>
        <input type="email" name="email" required maxlength="190" placeholder="आपका ईमेल" aria-label="ईमेल"><button class="btn btn-light" type="submit">लिंक दोबारा भेजें</button></form>
    </details>
  </div>
</div>
