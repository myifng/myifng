<?php /** SEO कमांड सेंटर के अंदर के टैब; $active */ ?>
<nav class="sub-tabs mb-3" aria-label="SEO के हिस्से">
  <?php foreach ([['admin.seo.index', 'fa-gauge', 'डैशबोर्ड', 'index', 'seo.view'], ['admin.redirects.index', 'fa-diamond-turn-right', 'रीडायरेक्ट', 'redirects', 'redirects.view'],
      ['admin.seo.404', 'fa-link-slash', '404 मॉनिटर', '404', 'seo.view'], ['admin.seo.links', 'fa-chain-broken', 'टूटे लिंक', 'links', 'seo.view'],
      ['admin.seo.canonical', 'fa-code-compare', 'Canonical / noindex', 'canonical', 'seo.view'], ['admin.seo.settings', 'fa-sliders', 'SEO सेटिंग', 'settings', 'seo.view']] as [$r, $ic, $l, $k, $perm]): ?>
    <?php if (can($perm)): ?><a class="<?= $active === $k ? 'active' : '' ?>"<?= $active === $k ? ' aria-current="page"' : '' ?> href="<?= e($k === 'settings' ? route($r, ['tab' => 'seo']) : route($r)) ?>"><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></a><?php endif; ?>
  <?php endforeach; ?>
</nav>
