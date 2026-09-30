<nav class="sub-tabs mb-3" aria-label="न्यूज़लेटर">
  <?php foreach ([['admin.newsletter.index', 'campaigns', 'fa-paper-plane', 'कैंपेन'], ['admin.newsletter.subscribers', 'subscribers', 'fa-users', 'सब्सक्राइबर'], ['admin.newsletter.lists', 'lists', 'fa-list', 'सूचियाँ'], ['admin.newsletter.templates', 'templates', 'fa-palette', 'टेम्पलेट']] as [$r, $k, $ic, $l]): ?>
    <a class="<?= $active === $k ? 'active' : '' ?>" href="<?= e(route($r)) ?>"><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></a>
  <?php endforeach; ?>
</nav>
