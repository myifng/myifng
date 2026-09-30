<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box">
    <h1 class="list-title"><i class="fa-solid fa-key"></i> नया पासवर्ड</h1>
    <form method="post" action="<?= e(route('account.reset.post', ['token' => $token])) ?>" class="fgrid one"><?= csrf_field() ?>
      <?= ff('password', 'password', 'नया पासवर्ड', ['required' => true, 'help' => 'कम से कम 8 अक्षर, अक्षर और अंक दोनों', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 8]]) ?>
      <?= ff('password', 'password_confirmation', 'नया पासवर्ड दोबारा', ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
      <div class="ff-wide"><button class="btn" type="submit">पासवर्ड सेव करें</button></div>
    </form>
  </div>
</div>
