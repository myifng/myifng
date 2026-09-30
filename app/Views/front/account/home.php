<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => $unread]) ?>
  <div class="acct-grid">
    <div>
      <?php if ($items && $items->items): ?>
        <div class="box"><?= block_head('आपकी फ़ॉलो की गई ख़बरें') ?><div class="listing"><?php foreach ($items->items as $n): ?><?= news_card($n, 'wide') ?><?php endforeach; ?></div></div>
        <?= $this->insert('partials/front/pager', ['p' => $items]) ?>
      <?php elseif ($items): ?>
        <div class="box empty"><p>आपकी फ़ॉलो की गई चीज़ों में अभी कोई ख़बर नहीं।</p><a class="more" href="<?= e(route('account.following')) ?>">और फ़ॉलो करें <i class="fa-solid fa-angle-right"></i></a></div>
      <?php else: ?>
        <div class="box acct-empty"><i class="fa-solid fa-wand-magic-sparkles"></i><h2>अपना पेज बनाएँ</h2><p>श्रेणी, शहर, रिपोर्टर या टॉपिक फ़ॉलो करें। उनकी ताज़ा ख़बरें यहाँ एक जगह दिखेंगी। कोई छुपा हुआ एल्गोरिदम नहीं; सिर्फ़ वही जो आप चुनें।</p>
          <a class="btn" href="<?= e(route('account.following')) ?>">फ़ॉलो करना शुरू करें</a></div>
        <?php if ($latest): ?><div class="box"><?= block_head('ताज़ा ख़बरें', route('latest')) ?><div class="listing"><?php foreach ($latest as $n): ?><?= news_card($n, 'wide') ?><?php endforeach; ?></div></div><?php endif; ?>
      <?php endif; ?>
    </div>
    <aside>
      <?php if ($cityPopular): ?>
        <div class="box"><?= block_head($cityName . ' में लोकप्रिय') ?><ol class="ranked"><?php foreach ($cityPopular as $n): ?><li><a href="<?= e(news_url($n)) ?>"><?= e($n['title']) ?></a></li><?php endforeach; ?></ol></div>
      <?php elseif (!$cityName): ?>
        <div class="box small-note"><p>अपना शहर चुनें, तो यहाँ उसकी लोकप्रिय ख़बरें दिखेंगी।</p><a class="more" href="<?= e(route('account.settings')) ?>">शहर चुनें <i class="fa-solid fa-angle-right"></i></a></div>
      <?php endif; ?>
    </aside>
  </div>
</div>
