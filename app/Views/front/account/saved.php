<?php $this->layout('layouts/front'); ?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => $unread]) ?>
  <?php if ($items->items): ?>
    <div class="box"><div class="listing"><?php foreach ($items->items as $n): ?>
      <div class="saved-row"><?= news_card($n, 'wide') ?><form method="post" action="<?= e(route('account.bookmark', ['id' => $n['id']])) ?>"><?= csrf_field() ?><button class="btn btn-light btn-sm" type="submit" aria-label="सेव से हटाएँ"><i class="fa-solid fa-bookmark"></i> हटाएँ</button></form></div>
    <?php endforeach; ?></div></div>
    <?= $this->insert('partials/front/pager', ['p' => $items]) ?>
  <?php else: ?>
    <div class="box acct-empty"><i class="fa-regular fa-bookmark"></i><h2>अभी कुछ सेव नहीं</h2><p>किसी भी ख़बर पर <i class="fa-regular fa-bookmark"></i> "सेव" दबाएँ, बाद में यहाँ पढ़ें।</p></div>
  <?php endif; ?>
</div>
