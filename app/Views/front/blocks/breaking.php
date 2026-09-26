<?php
use App\Models\BreakingNews;
if ($banner): ?>
<?= $this->insert('partials/front/breaking-alerts', ['where' => 'home']) ?>
<?php else: ?>
<div class="box brk-box">
  <?= block_head($title ?: 'ब्रेकिंग न्यूज़') ?>
  <ul class="brk-feed">
    <?php foreach ($items as $b): ?>
      <li class="p<?= (int) $b['priority'] ?>"><time datetime="<?= e(date('c', strtotime($b['starts_at']))) ?>"><?= e(date('H:i', strtotime($b['starts_at']))) ?></time>
        <span class="bf-type t-<?= e($b['type']) ?>"><?= e(BreakingNews::TYPES[$b['type']] ?? '') ?></span>
        <?= $b['link'] ? '<a href="' . e($b['link']) . '">' . e($b['title']) . '</a>' : '<span>' . e($b['title']) . '</span>' ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
