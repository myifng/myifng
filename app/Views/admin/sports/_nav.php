<?php /** $active */ ?>
<nav class="sub-tabs mb-3" aria-label="खेल केंद्र">
  <a class="<?= $active === 'matches' ? 'active' : '' ?>" href="<?= e(route('admin.sports.index')) ?>"><i class="fa-solid fa-calendar-days me-1"></i>मैच</a>
  <a class="<?= $active === 'tournaments' ? 'active' : '' ?>" href="<?= e(route('admin.sports.tournaments')) ?>"><i class="fa-solid fa-trophy me-1"></i>टूर्नामेंट</a>
  <?php if (can('sports.manage')): ?><a class="<?= $active === 'teams' ? 'active' : '' ?>" href="<?= e(route('admin.sports.teams')) ?>"><i class="fa-solid fa-people-group me-1"></i>टीमें</a><?php endif; ?>
  <a href="<?= e(route('sports.index')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>वेबसाइट पर</a>
</nav>
