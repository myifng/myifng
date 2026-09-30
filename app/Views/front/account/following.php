<?php
$this->layout('layouts/front');
$chip = function (string $type, int $id, string $name) use ($f) {
    $on = in_array($id, $f[$type], true);
    return '<form method="post" action="' . e(route('account.follow')) . '" class="follow-form">' . csrf_field() . '<input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="follow-chip' . ($on ? ' on' : '') . '" data-follow="' . e($type) . ':' . $id . '" aria-pressed="' . ($on ? 'true' : 'false') . '"><i class="fa-solid ' . ($on ? 'fa-check' : 'fa-plus') . '"></i> ' . e($name) . '</button></form>';
};
?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => $unread]) ?>
  <?php if (array_filter($names)): ?>
    <div class="box"><?= block_head('आप फ़ॉलो कर रहे हैं') ?>
      <?php foreach (\App\Services\ReaderService::FOLLOW_TYPES as $type => $label): if (empty($names[$type])) continue; ?>
        <div class="follow-group"><b><?= e($label) ?></b><div class="follow-chips"><?php foreach ($names[$type] as $id => [$n]): ?><?= $chip($type, (int) $id, $n) ?><?php endforeach; ?></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="box"><?= block_head('श्रेणियाँ') ?><div class="follow-chips"><?php foreach ($cats as $c): ?><?= $chip('category', (int) $c['id'], ($c['parent_id'] ? '— ' : '') . $c['name']) ?><?php endforeach; ?></div></div>
  <div class="box"><?= block_head('शहर / लोकेशन') ?>
    <div class="loc-follow" data-loc-follow data-search="<?= e(route('api.locations')) ?>">
      <input type="search" placeholder="शहर या ज़िला खोजें, जैसे गोरखपुर" aria-label="शहर खोजें" autocomplete="off">
      <div class="follow-chips" data-loc-results></div>
    </div>
  </div>
  <?php if ($reporters): ?><div class="box"><?= block_head('रिपोर्टर') ?><div class="follow-chips"><?php foreach ($reporters as $r): ?><?= $chip('reporter', (int) $r['id'], $r['name']) ?><?php endforeach; ?></div></div><?php endif; ?>
  <?php if ($topics): ?><div class="box"><?= block_head('टॉपिक') ?><div class="follow-chips"><?php foreach ($topics as $t): ?><?= $chip('topic', (int) $t['id'], $t['name']) ?><?php endforeach; ?></div></div><?php endif; ?>
</div>
