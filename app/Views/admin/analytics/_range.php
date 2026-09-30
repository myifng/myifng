<?php /** अवधि चुनें: $r, $action (फ़ॉर्म का पता) */ ?>
<form class="range-form" method="get" action="<?= e($action) ?>" data-range-form>
  <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="अवधि">
    <?php foreach (['today' => 'आज', 'yesterday' => 'कल', '7' => '7 दिन', '30' => '30 दिन', '90' => '90 दिन'] as $k => $l): ?>
      <button type="submit" name="range" value="<?= e($k) ?>" class="btn <?= $r['key'] === (string) $k ? 'btn-primary' : 'btn-outline-secondary' ?>"<?= $r['key'] === (string) $k ? ' aria-pressed="true"' : '' ?>><?= e($l) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="range-custom">
    <label class="visually-hidden" for="rf-from">से</label><input id="rf-from" class="form-control form-control-sm" type="date" name="from" value="<?= e($r['from']) ?>" max="<?= e(date('Y-m-d')) ?>">
    <span aria-hidden="true">–</span>
    <label class="visually-hidden" for="rf-to">तक</label><input id="rf-to" class="form-control form-control-sm" type="date" name="to" value="<?= e($r['to']) ?>" max="<?= e(date('Y-m-d')) ?>">
    <button class="btn btn-sm btn-outline-primary" type="submit" name="range" value="custom">लागू करें</button>
  </div>
</form>
