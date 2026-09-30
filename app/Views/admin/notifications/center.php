<?php
use App\Services\NotificationService as NS;
$this->layout('layouts/admin');
$title = 'नोटिफ़िकेशन सेंटर';
$push = setting('push_enabled', '0') === '1';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">नोटिफ़िकेशन सेंटर</li></ol></nav>
    <h1>नोटिफ़िकेशन सेंटर</h1><p>इन-ऐप, ईमेल, वेब पुश, SMS और WhatsApp: किस इवेंट पर कौन-सा चैनल, सेटिंग से तय।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('notifications.manage')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.settings', ['tab' => 'notify'])) ?>"><i class="fa-solid fa-sliders me-1"></i> चैनल सेटिंग</a>
      <form method="post" action="<?= e(route('admin.notifications.process')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-play me-1"></i> कतार अभी चलाएँ (<?= num($stats['q'] ?? 0) ?>)</button></form><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-3">
  <?php foreach ([['कतार में', $stats['q'] ?? 0, 'fa-hourglass-half'], ['24 घंटे में भेजे', $stats['s'] ?? 0, 'fa-paper-plane'], ['24 घंटे में विफल', $stats['f'] ?? 0, 'fa-triangle-exclamation'], ['पुश सब्सक्राइबर', $pushSubs, 'fa-bell'], ['पाठक खाते', $readers, 'fa-user-group']] as [$l, $v, $ic]): ?>
    <div class="col-6 col-lg"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= num($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-3">
  <div class="col-xl-5">
    <?php if (can('notifications.create')): ?>
    <section class="panel"><div class="panel-head"><h2>सूचना भेजें</h2></div><div class="panel-body">
      <?php if (!$push): ?><div class="alert alert-light border small">वेब पुश बंद है। <?= $pushReady ? 'सेटिंग → नोटिफ़िकेशन में चालू करें (HTTPS ज़रूरी)।' : 'सर्वर पर openssl (EC) उपलब्ध नहीं।' ?></div><?php endif; ?>
      <form method="post" action="<?= e(route('admin.notifications.send')) ?>" novalidate data-confirm="सूचना भेजी जाएगी।"><?= csrf_field() ?>
        <?= field('select', 'audience', 'किसे', 'push', ['options' => ['push' => 'वेब पुश: सभी सब्सक्राइबर (' . num($pushSubs) . ')', 'readers' => 'पाठक खाते की घंटी (' . num($readers) . ')', 'staff' => 'सारे स्टाफ़ की घंटी']]) ?>
        <?= field('text', 'title', 'शीर्षक', '', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?>
        <?= field('text', 'body', 'संदेश', '', ['attrs' => ['maxlength' => 300]]) ?>
        <?= field('url', 'url', 'खुलने वाला लिंक', '', ['placeholder' => url('news/...')]) ?>
        <button class="btn btn-brand" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> भेजें</button>
      </form>
      <p class="form-text mt-2">ब्रेकिंग न्यूज़ का पुश "ब्रेकिंग कंट्रोल रूम" से अपने आप जाता है।</p>
    </div></section>
    <?php endif; ?>
  </div>
  <div class="col-xl-7"><section class="panel"><div class="panel-head"><h2>इवेंट और चैनल</h2></div>
    <div class="table-responsive"><table class="table align-middle mb-0 small">
      <thead><tr><th>इवेंट</th><th>किसे</th><th>चालू चैनल</th></tr></thead>
      <tbody><?php foreach (NS::EVENTS as $k => [$l, $who]): ?><tr><td><?= e($l) ?></td><td class="text-body-secondary"><?= e($who) ?></td>
        <td><?php foreach ($channels[$k] as $c): ?><span class="badge text-bg-light me-1"><?= e(NS::CHANNELS[$c]) ?></span><?php endforeach; ?><?= !$channels[$k] ? '<span class="text-body-secondary">बंद</span>' : '' ?></td></tr><?php endforeach; ?></tbody>
    </table></div></section></div>
</div>
<section class="panel">
  <div class="panel-head"><h2>भेजने का लॉग (outbox)</h2>
    <?php if (can('notifications.manage') && ($stats['f'] ?? 0)): ?><form method="post" action="<?= e(route('admin.notifications.retry')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">विफल दोबारा</button></form><?php endif; ?></div>
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="channel" aria-label="चैनल"><option value="">हर चैनल</option><?php foreach (NS::CHANNELS as $k => $l): if ($k === 'inapp') continue; ?><option value="<?= $k ?>"<?= selected($k, $f['channel'] ?? '') ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">हर स्थिति</option><?php foreach (['queued' => 'कतार', 'sent' => 'भेजा', 'failed' => 'विफल'] as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $f['status'] ?? '') ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="event" aria-label="इवेंट"><option value="">हर इवेंट</option><?php foreach (NS::EVENTS as $k => [$l]): ?><option value="<?= $k ?>"<?= selected($k, $f['event'] ?? '') ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark" type="submit">दिखाएँ</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table align-middle mb-0 data-table small">
    <thead><tr><th>कब</th><th>चैनल</th><th>इवेंट</th><th>किसे</th><th>शीर्षक</th><th>स्थिति</th></tr></thead>
    <tbody><?php foreach ($items->items as $o): $pl = json_decode($o['payload'], true) ?: []; ?><tr>
      <td class="text-nowrap"><?= e(hindi_date($o['created_at'], true)) ?></td>
      <td><?= e(NS::CHANNELS[$o['channel']] ?? $o['channel']) ?></td>
      <td><?= e(NS::EVENTS[$o['event']][0] ?? $o['event']) ?></td>
      <td class="text-break"><?= $o['channel'] === 'push' ? 'सब्सक्रिप्शन #' . e($o['recipient']) : e($o['recipient']) ?></td>
      <td><?= e(\App\Helpers\Str::limit((string) ($pl['title'] ?? ''), 60)) ?></td>
      <td><span class="badge text-bg-<?= ['queued' => 'warning', 'sent' => 'success', 'failed' => 'danger'][$o['status']] ?>"><?= ['queued' => 'कतार', 'sent' => 'भेजा', 'failed' => 'विफल'][$o['status']] ?></span><?= $o['error'] ? '<div class="text-danger">' . e($o['error']) . '</div>' : '' ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-tower-broadcast"></i><p>अभी कुछ नहीं भेजा गया।</p></div><?php endif; ?>
</section>
