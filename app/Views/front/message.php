<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box msg-box">
    <i class="fa-solid <?= e($icon ?? 'fa-circle-info') ?> msg-icon" aria-hidden="true"></i>
    <h1 class="list-title"><?= e($title) ?></h1>
    <p><?= e($text) ?></p>
    <a class="btn" href="<?= e(url()) ?>">होम पेज पर जाएँ</a>
  </div>
</div>
