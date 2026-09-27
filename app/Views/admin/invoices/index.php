<?php
use App\Models\AdInvoice;
use App\Services\AdvertiserService;
$this->layout('layouts/admin');
$title = 'इनवॉइस';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item active" aria-current="page">इनवॉइस</li></ol></nav>
    <h1>इनवॉइस</h1>
    <p>इस सूची में: बिल <?= e(AdvertiserService::money($sum['billed'])) ?> · भुगतान <?= e(AdvertiserService::money($sum['paid'])) ?></p>
  </div>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.export')) ?>?<?= e(http_build_query(array_filter($f))) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a>
    <a class="btn btn-brand" href="<?= e(route('admin.invoices.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया इनवॉइस</a></div>
</div>
<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी स्थिति</option><option value="overdue"<?= selected('overdue', $f['status']) ?>>देय तारीख़ निकली</option><?php foreach (AdInvoice::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="advertiser" aria-label="विज्ञापनदाता"><option value="">सभी विज्ञापनदाता</option><?php foreach ($advertisers as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected($id, $f['advertiser']) ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <input class="form-control w-auto" type="month" name="month" value="<?= e($f['month']) ?>" aria-label="महीना">
    <button class="btn btn-dark" type="submit">दिखाएँ</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>इनवॉइस</th><th>विज्ञापनदाता</th><th>तारीख़</th><th>देय</th><th class="text-end">कुल</th><th class="text-end">बकाया</th><th>स्थिति</th></tr></thead>
    <tbody><?php foreach ($items->items as $i): $late = in_array($i['status'], ['sent', 'partial'], true) && $i['due_date'] && $i['due_date'] < date('Y-m-d'); ?>
      <tr><td><a class="fw-semibold font-monospace text-reset" href="<?= e(route('admin.invoices.show', ['id' => $i['id']])) ?>"><?= e($i['invoice_no']) ?></a></td>
        <td><?= e($i['company']) ?></td><td class="small"><?= hindi_date($i['issue_date']) ?></td>
        <td class="small<?= $late ? ' text-danger fw-semibold' : '' ?>"><?= $i['due_date'] ? hindi_date($i['due_date']) : '—' ?></td>
        <td class="text-end"><?= e(AdvertiserService::money($i['total'])) ?></td>
        <td class="text-end"><?= e(AdvertiserService::money($i['status'] === 'cancelled' ? 0 : $i['total'] - $i['paid'])) ?></td>
        <td><span class="badge text-bg-<?= e(AdInvoice::BADGE[$i['status']]) ?>"><?= e(AdInvoice::STATUSES[$i['status']]) ?></span></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-file-invoice"></i><p>कोई इनवॉइस नहीं मिला।</p></div><?php endif; ?>
</section>
