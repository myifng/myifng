<?php
/** होमपेज अलर्ट बैनर ($where = 'home') या मोबाइल अलर्ट ($where = 'mobile'); पाठक बंद करे तो उस आइटम के लिए याद (localStorage) */
use App\Services\BreakingService;
if (!app('router')->has('news.show')) return;
$b = $where === 'home' ? BreakingService::banner() : BreakingService::mobileAlert();
if (!$b) return;
$type = \App\Models\BreakingNews::TYPES[$b['type']] ?? 'ब्रेकिंग';
?>
<?php if ($where === 'home'): ?>
<div class="brk-banner p<?= (int) $b['priority'] ?>" role="alert" data-dismiss-key="bb-<?= (int) $b['id'] ?>">
  <span class="bb-label"><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?= e($type) ?></span>
  <?= $b['link'] ? '<a class="bb-title" href="' . e($b['link']) . '">' . e($b['title']) . '</a>' : '<span class="bb-title">' . e($b['title']) . '</span>' ?>
  <button type="button" class="bb-close" data-dismiss aria-label="बंद करें">×</button>
</div>
<?php else: ?>
<div class="brk-mobile p<?= (int) $b['priority'] ?>" role="status" data-dismiss-key="bm-<?= (int) $b['id'] ?>" hidden>
  <span class="bm-label"><?= e($type) ?></span>
  <?= $b['link'] ? '<a href="' . e($b['link']) . '">' . e($b['title']) . '</a>' : '<span>' . e($b['title']) . '</span>' ?>
  <button type="button" class="bb-close" data-dismiss aria-label="बंद करें">×</button>
</div>
<?php endif; ?>
