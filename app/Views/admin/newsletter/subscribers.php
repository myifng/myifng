<?php
use App\Models\NewsletterSubscriber as NS;
$this->layout('layouts/admin');
$title = 'न्यूज़लेटर सब्सक्राइबर';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.newsletter.index')) ?>">न्यूज़लेटर</a></li><li class="breadcrumb-item active" aria-current="page">सब्सक्राइबर</li></ol></nav>
    <h1>सब्सक्राइबर</h1>
  </div>
  <?php if (can('newsletter.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.newsletter.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a><?php endif; ?>
</div>
<?= $this->insert('admin/newsletter/_nav', ['active' => 'subscribers']) ?>
<div class="row g-3">
  <div class="col-xl-8 order-2 order-xl-1"><section class="panel">
    <form class="filter-bar" method="get">
      <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी</option><?php foreach (NS::STATUSES as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <select class="form-select w-auto" name="list" aria-label="सूची"><option value="">हर सूची</option><?php foreach ($lists as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected((string) $id, (string) $list) ?>><?= e($n) ?></option><?php endforeach; ?></select>
      <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="ईमेल या नाम" aria-label="खोजें"></div>
      <button class="btn btn-dark" type="submit">खोजें</button>
    </form>
    <?php if ($items->items): ?>
    <form method="post" action="<?= e(route('admin.newsletter.subscribers.action')) ?>" id="nsBulk"><?= csrf_field() ?></form>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all="ns" aria-label="सभी चुनें"></th><th>ईमेल</th><th>सूचियाँ</th><th>स्थिति</th><th>कब से</th></tr></thead>
      <tbody><?php foreach ($items->items as $s): ?><tr>
        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" form="nsBulk" data-check="ns" aria-label="चुनें"></td>
        <td class="text-break"><b><?= e($s['email']) ?></b><?= $s['name'] ? '<div class="small text-body-secondary">' . e($s['name']) . '</div>' : '' ?></td>
        <td class="small"><?= e((string) $s['lists']) ?></td>
        <td><span class="badge text-bg-<?= NS::BADGE[$s['status']] ?>"><?= e(NS::STATUSES[$s['status']]) ?></span><div class="small text-body-secondary"><?= e((string) $s['source']) ?></div></td>
        <td class="small text-nowrap"><?= e(hindi_date($s['confirmed_at'] ?: $s['created_at'])) ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="bulk-bar"><span class="small text-body-secondary">चुने हुए:</span>
      <?php if (can('newsletter.edit')): ?>
        <select class="form-select form-select-sm w-auto" name="list_id" form="nsBulk" aria-label="सूची"><?php foreach ($lists as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-outline-secondary" type="submit" form="nsBulk" name="action" value="list">सूची में जोड़ें</button>
        <button class="btn btn-sm btn-outline-secondary" type="submit" form="nsBulk" name="action" value="unsubscribe">अनसब्सक्राइब</button>
      <?php endif; ?>
      <?php if (can('newsletter.delete')): ?><button class="btn btn-sm btn-outline-danger" type="submit" form="nsBulk" name="action" value="delete">हटाएँ</button><?php endif; ?>
    </div>
    <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
    <?php else: ?><div class="empty-state"><i class="fa-solid fa-users"></i><p>कोई सब्सक्राइबर नहीं।</p></div><?php endif; ?>
  </section></div>
  <?php if (can('newsletter.create')): ?>
  <div class="col-xl-4 order-1 order-xl-2"><section class="panel"><div class="panel-body">
    <h2 class="h6">पते जोड़ें</h2>
    <form method="post" action="<?= e(route('admin.newsletter.subscribers.add')) ?>"><?= csrf_field() ?>
      <textarea class="form-control mb-2" name="emails" rows="4" required placeholder="एक लाइन में एक ईमेल (1000 तक)" aria-label="ईमेल"></textarea>
      <?php foreach ($lists as $id => $n): ?><label class="form-check small"><input class="form-check-input" type="checkbox" name="lists[]" value="<?= (int) $id ?>" checked> <?= e($n) ?></label><?php endforeach; ?>
      <label class="form-check small"><input class="form-check-input" type="checkbox" name="confirm" value="1" checked> पुष्टि ईमेल भेजें (डबल ऑप्ट-इन, सुझाया)</label>
      <button class="btn btn-brand btn-sm mt-2" type="submit">जोड़ें</button>
      <p class="form-text">सिर्फ़ वही पते जोड़ें जिन्होंने अनुमति दी है। जिन्होंने अनसब्सक्राइब किया, वे दोबारा नहीं जुड़ते।</p>
    </form>
  </div></section></div>
  <?php endif; ?>
</div>
