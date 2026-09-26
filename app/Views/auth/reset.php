<?php $this->layout('layouts/auth'); $title = 'नया पासवर्ड'; ?>
<h1 class="h3 fw-bold mb-1">नया पासवर्ड बनाएँ</h1>
<p class="text-body-secondary mb-4">कम से कम 8 अक्षर, जिनमें अक्षर और अंक दोनों हों।</p>
<form method="post" action="<?= e(route('admin.password.update')) ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <?= field('password', 'password', 'नया पासवर्ड', '', ['required' => true, 'attrs' => ['autocomplete' => 'new-password', 'autofocus' => true]]) ?>
  <?= field('password', 'password_confirmation', 'नया पासवर्ड दोबारा', '', ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
  <button class="btn btn-brand w-100 btn-lg" type="submit">पासवर्ड बदलें</button>
</form>
