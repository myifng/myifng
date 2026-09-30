<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => 0]) ?>
  <div class="box">
    <?php if ($rows): ?>
      <ul class="notes"><?php foreach ($rows as $n): ?>
        <li class="<?= $n['read_at'] ? '' : 'unread' ?>"><a href="<?= e($n['url'] ?: route('account')) ?>"><b><?= e($n['title']) ?></b><?= $n['body'] ? '<span>' . e($n['body']) . '</span>' : '' ?></a><time><?= e(hindi_date($n['created_at'], true)) ?></time></li>
      <?php endforeach; ?></ul>
    <?php else: ?><div class="acct-empty"><i class="fa-regular fa-bell"></i><h2>कोई सूचना नहीं</h2><p>फ़ॉलो की गई चीज़ों की नई ख़बर और ब्रेकिंग न्यूज़ यहाँ दिखेंगी। पसंद <a href="<?= e(route('account.settings')) ?>">सेटिंग</a> में बदलें।</p></div><?php endif; ?>
  </div>
</div>
