<?php /** $tab, $reader, $unread */ ?>
<div class="acct-head">
  <span class="acct-avatar" aria-hidden="true"><?= e(mb_substr((string) $reader['name'], 0, 1)) ?></span>
  <div><h1><?= e($reader['name']) ?></h1><small><?= e($reader['email']) ?></small></div>
  <form method="post" action="<?= e(route('account.logout')) ?>" class="ms-auto"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> लॉगआउट</button></form>
</div>
<nav class="acct-tabs" aria-label="खाते के हिस्से">
  <?php foreach ([['home', 'account', 'fa-wand-magic-sparkles', 'आपके लिए'], ['saved', 'account.saved', 'fa-bookmark', 'सेव'], ['following', 'account.following', 'fa-plus', 'फ़ॉलो'],
      ['history', 'account.history', 'fa-clock-rotate-left', 'इतिहास'], ['notifications', 'account.notifications', 'fa-bell', 'सूचनाएँ'], ['settings', 'account.settings', 'fa-gear', 'सेटिंग']] as [$k, $r, $ic, $l]): ?>
    <a href="<?= e(route($r)) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= $ic ?>"></i> <?= e($l) ?><?= $k === 'notifications' && $unread ? ' <b class="acct-badge">' . num($unread) . '</b>' : '' ?></a>
  <?php endforeach; ?>
</nav>
