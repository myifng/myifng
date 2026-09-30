<?php /** $active */ ?>
<nav class="sub-tabs mb-3" aria-label="चुनाव केंद्र">
  <a class="<?= $active === 'index' ? 'active' : '' ?>" href="<?= e(route('admin.elections.index')) ?>"><i class="fa-solid fa-check-to-slot me-1"></i>चुनाव</a>
  <?php if (can('elections.manage')): ?><a class="<?= $active === 'parties' ? 'active' : '' ?>" href="<?= e(route('admin.elections.parties')) ?>"><i class="fa-solid fa-flag me-1"></i>पार्टियाँ</a><?php endif; ?>
  <a href="<?= e(route('elections.index')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>वेबसाइट पर</a>
</nav>
