<?php /** सिस्टम के टैब: $active */ ?>
<nav class="sub-tabs mb-3" aria-label="सिस्टम">
  <?php foreach ([['admin.system.index', 'fa-server', 'सेहत और कैश', 'system', 'system.view'], ['admin.system.security', 'fa-shield-halved', 'सुरक्षा', 'security', 'system.view'],
      ['admin.backups.index', 'fa-database', 'बैकअप', 'backups', 'backups.view'], ['admin.audit.index', 'fa-clipboard-list', 'ऑडिट लॉग', 'audit', 'audit.view'],
      ['admin.audit.logins', 'fa-right-to-bracket', 'लॉगिन इतिहास', 'logins', 'audit.view']] as [$r, $ic, $l, $k, $perm]): ?>
    <?php if (can($perm) && app('router')->has($r)): ?><a class="<?= $active === $k ? 'active' : '' ?>"<?= $active === $k ? ' aria-current="page"' : '' ?> href="<?= e(route($r)) ?>"><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></a><?php endif; ?>
  <?php endforeach; ?>
</nav>
