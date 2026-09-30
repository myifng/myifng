<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap with-side">
  <div>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">पोल</span></nav>
    <div class="box poll-page"><?= $widget ?></div>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
