<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap with-side">
  <div>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page"><?= e($form['title']) ?></span></nav>
    <div class="box form-page" id="form">
      <h1 class="list-title"><?php if (!empty($icon)): ?><i class="fa-solid <?= e($icon) ?>"></i> <?php endif; ?><?= e($form['title']) ?></h1>
      <?php if ($form['description']): ?><p class="list-desc"><?= nl2br(e(strip_tags((string) $form['description']))) ?></p><?php endif; ?>
      <?php if (!empty($trackLink)): ?><p class="small-link"><a href="<?= e(route('complaint.track')) ?>"><i class="fa-solid fa-magnifying-glass"></i> पहले दर्ज की शिकायत की स्थिति देखें</a></p><?php endif; ?>
      <?= $formHtml ?>
    </div>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
