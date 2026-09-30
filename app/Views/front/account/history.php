<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => $unread]) ?>
  <div class="box">
    <div class="acct-bar"><p class="m-0"><?= (int) $reader['history_enabled'] ? 'आपने जो ख़बरें पढ़ीं (सिर्फ़ आपको दिखता है)।' : 'पढ़ने का इतिहास बंद है। सेटिंग से चालू कर सकते हैं।' ?></p>
      <?php if ($items->total): ?><form method="post" action="<?= e(route('account.history.clear')) ?>" data-confirm="पूरा इतिहास मिट जाएगा।"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit"><i class="fa-regular fa-trash-can"></i> इतिहास मिटाएँ</button></form><?php endif; ?></div>
    <?php if ($items->items): ?>
      <ul class="hist"><?php foreach ($items->items as $n): ?><li><a href="<?= e(news_url($n)) ?>"><?= e($n['title']) ?></a><time datetime="<?= e(date('c', strtotime($n['read_at']))) ?>"><?= e(hindi_date($n['read_at'], true)) ?></time></li><?php endforeach; ?></ul>
    <?php elseif ((int) $reader['history_enabled']): ?><p class="muted">अभी कोई ख़बर नहीं पढ़ी।</p><?php endif; ?>
  </div>
  <?= $this->insert('partials/front/pager', ['p' => $items]) ?>
</div>
