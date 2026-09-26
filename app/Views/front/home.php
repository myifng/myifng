<?php $this->layout('layouts/front'); ?>
<h1 class="visually-hidden"><?= e(setting('site_name')) ?>: <?= e(setting('tagline') ?: 'ताज़ा हिंदी समाचार') ?></h1>
<div class="wrap home">
  <?php if (trim($sections) !== ''): ?>
    <?= $sections /* HomeRenderer: हर ब्लॉक का टेम्पलेट ख़ुद escape करता है */ ?>
  <?php else: ?>
    <div class="box empty-home">
      <i class="fa-regular fa-newspaper"></i>
      <p>जल्द ही यहाँ ताज़ा ख़बरें दिखेंगी।</p>
    </div>
  <?php endif; ?>
</div>
