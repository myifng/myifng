<?php
$this->layout('layouts/admin');
$title = 'मेरी सूचनाएँ';
?>
<div class="page-head">
  <div><nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">सूचनाएँ</li></ol></nav><h1>मेरी सूचनाएँ</h1></div>
  <div class="d-flex gap-2"><form method="post" action="<?= e(route('admin.notifications.read')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-check-double me-1"></i> सब पढ़ी हुई</button></form>
    <?php if (can('notifications.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.notifications.center')) ?>"><i class="fa-solid fa-tower-broadcast me-1"></i> नोटिफ़िकेशन सेंटर</a><?php endif; ?></div>
</div>
<section class="panel">
  <?php if ($items->items): ?>
  <ul class="list-group list-group-flush notif-list">
    <?php foreach ($items->items as $n): ?><li class="list-group-item<?= $n['read_at'] ? '' : ' unread' ?>"><a class="text-reset d-block" href="<?= e(route('admin.notifications.open', ['id' => $n['id']])) ?>"><b><?= e($n['title']) ?></b><?= $n['body'] ? '<div class="small text-body-secondary">' . e($n['body']) . '</div>' : '' ?><div class="small text-body-secondary"><?= e(hindi_date($n['created_at'], true)) ?></div></a></li><?php endforeach; ?>
  </ul>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-regular fa-bell-slash"></i><p>अभी कोई सूचना नहीं।</p></div><?php endif; ?>
</section>
