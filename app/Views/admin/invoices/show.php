<?php
use App\Models\AdInvoice;
use App\Models\AdPayment;
use App\Services\AdvertiserService as M;
$this->layout('layouts/admin');
$title = 'इनवॉइस ' . $inv['invoice_no'];
$due = $inv['status'] === 'cancelled' ? 0 : round($inv['total'] - $inv['paid'], 2);
$editable = in_array($inv['status'], ['draft', 'sent'], true) && (float) $inv['paid'] == 0.0;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.invoices.index')) ?>">इनवॉइस</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($inv['invoice_no']) ?></li></ol></nav>
    <h1 class="font-monospace"><?= e($inv['invoice_no']) ?> <span class="badge text-bg-<?= e(AdInvoice::BADGE[$inv['status']]) ?> align-middle"><?= e(AdInvoice::STATUSES[$inv['status']]) ?></span></h1>
    <p><a href="<?= e(route('admin.advertisers.show', ['id' => $adv['id']])) ?>"><?= e($adv['company']) ?></a><?= $campaign ? ' · ' . e($campaign['name']) : '' ?> · <?= hindi_date($inv['issue_date']) ?><?= $inv['due_date'] ? ' · देय ' . hindi_date($inv['due_date']) : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.print', ['id' => $inv['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i> प्रिंट / PDF</a>
    <?php if ($editable): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.edit', ['id' => $inv['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
    <?php foreach (['sent' => 'भेजा गया करें', 'draft' => 'ड्राफ़्ट करें', 'cancelled' => 'रद्द करें'] as $s => $l): if ($s === $inv['status'] || ($s === 'draft' && $inv['status'] !== 'sent') || ($s === 'sent' && !in_array($inv['status'], ['draft', 'cancelled'], true)) || ($s === 'cancelled' && (float) $inv['paid'] > 0)) continue; ?>
      <form method="post" action="<?= e(route('admin.invoices.status', ['id' => $inv['id']])) ?>"<?= $s === 'cancelled' ? ' data-confirm="इनवॉइस रद्द होगा।"' : '' ?>><?= csrf_field() ?><input type="hidden" name="status" value="<?= e($s) ?>"><button class="btn <?= $s === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" type="submit"><?= e($l) ?></button></form>
    <?php endforeach; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel"><div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>विवरण</th><th class="text-end">मात्रा</th><th class="text-end">दर</th><th class="text-end">राशि</th></tr></thead>
      <tbody><?php foreach ($lines as $l): ?><tr><td><?= e($l['description']) ?></td><td class="text-end"><?= e(rtrim(rtrim($l['qty'], '0'), '.')) ?></td><td class="text-end"><?= e(M::money($l['rate'])) ?></td><td class="text-end"><?= e(M::money($l['amount'])) ?></td></tr><?php endforeach; ?></tbody>
      <tfoot><tr><td colspan="3" class="text-end">राशि</td><td class="text-end"><?= e(M::money($inv['subtotal'])) ?></td></tr>
        <tr><td colspan="3" class="text-end">GST (<?= e(rtrim(rtrim($inv['tax_rate'], '0'), '.')) ?>%)</td><td class="text-end"><?= e(M::money($inv['tax'])) ?></td></tr>
        <tr class="fw-bold"><td colspan="3" class="text-end">कुल</td><td class="text-end"><?= e(M::money($inv['total'])) ?></td></tr>
        <tr><td colspan="3" class="text-end">भुगतान</td><td class="text-end text-success"><?= e(M::money($inv['paid'])) ?></td></tr>
        <tr class="fw-bold"><td colspan="3" class="text-end">बकाया</td><td class="text-end<?= $due > 0 ? ' text-danger' : '' ?>"><?= e(M::money($due)) ?></td></tr></tfoot>
    </table></div></section>
    <?php if ($inv['notes']): ?><section class="panel mt-3"><div class="panel-body small" style="white-space:pre-line"><?= e($inv['notes']) ?></div></section><?php endif; ?>
  </div>
  <div class="col-xl-4">
    <?php if ($due > 0 && !in_array($inv['status'], ['draft', 'cancelled'], true)): ?>
    <section class="panel"><div class="panel-head"><h2><i class="fa-solid fa-indian-rupee-sign me-2 text-body-secondary"></i>भुगतान दर्ज करें</h2></div><div class="panel-body">
      <form method="post" action="<?= e(route('admin.invoices.pay', ['id' => $inv['id']])) ?>" novalidate>
        <?= csrf_field() ?>
        <?= field('number', 'amount', 'राशि (₹)', $due, ['required' => true, 'attrs' => ['min' => '0.01', 'max' => $due, 'step' => '0.01']]) ?>
        <div class="row g-2"><div class="col-6"><?= field('date', 'paid_on', 'तारीख़', date('Y-m-d'), ['attrs' => ['max' => date('Y-m-d')]]) ?></div><div class="col-6"><?= field('select', 'method', 'तरीका', 'bank', ['options' => AdPayment::METHODS]) ?></div></div>
        <?= field('text', 'reference', 'रेफ़रेंस (UTR / चेक नं.)', '', ['attrs' => ['maxlength' => 120]]) ?>
        <?= field('text', 'notes', 'नोट', '', ['attrs' => ['maxlength' => 300]]) ?>
        <button class="btn btn-brand w-100" type="submit">भुगतान दर्ज करें</button>
      </form>
    </div></section>
    <?php elseif ($inv['status'] === 'draft'): ?><div class="alert alert-info">भुगतान दर्ज करने के लिए पहले इनवॉइस को “भेजा गया” करें।</div><?php endif; ?>
    <section class="panel mt-3"><div class="panel-head"><h2>भुगतान</h2></div>
      <?php if ($payments): ?><ul class="list-group list-group-flush"><?php foreach ($payments as $p): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 small"><span><b><?= e(M::money($p['amount'])) ?></b> · <?= hindi_date($p['paid_on']) ?><br><?= e(AdPayment::METHODS[$p['method']]) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?><?= $p['by_name'] ? ' · ' . e($p['by_name']) : '' ?></span>
          <?= delete_button(route('admin.invoices.unpay', ['id' => $inv['id'], 'pid' => $p['id']]), 'यह भुगतान हटेगा और बकाया फिर से गिना जाएगा।') ?></li>
      <?php endforeach; ?></ul><?php else: ?><div class="empty-state py-3"><p class="mb-0">अभी कोई भुगतान नहीं।</p></div><?php endif; ?>
    </section>
    <?php if (in_array($inv['status'], ['draft', 'cancelled'], true) && (float) $inv['paid'] == 0.0): ?>
      <div class="danger-zone mt-3"><div><b>इनवॉइस हटाएँ</b></div><?= delete_button(route('admin.invoices.destroy', ['id' => $inv['id']]), $inv['invoice_no'] . ' हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
    <?php endif; ?>
  </div>
</div>
