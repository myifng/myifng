<?php
$this->layout('layouts/admin');
$isNew = $inv === null;
$title = $isNew ? 'नया इनवॉइस' : 'इनवॉइस ' . $inv['invoice_no'];
$rows = is_array(old('items')) ? array_values(old('items')) : $lines;
$row = fn($l, $i) => '<tr data-line><td><input class="form-control form-control-sm" name="items[' . $i . '][description]" value="' . e($l['description'] ?? '') . '" maxlength="300" placeholder="जैसे: होमपेज बैनर, 1–30 नवंबर" aria-label="विवरण"></td>'
    . '<td><input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="items[' . $i . '][qty]" value="' . e($l['qty'] ?? 1) . '" data-qty aria-label="मात्रा"></td>'
    . '<td><input class="form-control form-control-sm text-end" type="number" step="0.01" min="0" name="items[' . $i . '][rate]" value="' . e($l['rate'] ?? '') . '" data-rate aria-label="दर"></td>'
    . '<td class="text-end align-middle" data-amount>0.00</td><td><button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-line-remove aria-label="पंक्ति हटाएँ">×</button></td></tr>';
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.invoices.index')) ?>">इनवॉइस</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
  <h1><?= e($title) ?></h1></div></div>
<?php if (!$advertisers): ?><section class="panel"><div class="panel-body">पहले <a href="<?= e(route('admin.advertisers.create')) ?>">विज्ञापनदाता</a> जोड़ें।</div></section><?php else: ?>
<form method="post" action="<?= e($isNew ? route('admin.invoices.store') : route('admin.invoices.update', ['id' => $inv['id']])) ?>" novalidate data-unsaved data-invoice>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <div class="row g-2">
          <div class="col-md-6"><?= field('select', 'advertiser_id', 'विज्ञापनदाता', $advertiser ?: '', ['options' => $advertisers, 'empty' => 'चुनें…', 'required' => true, 'attrs' => ['data-inv-adv' => true]]) ?></div>
          <div class="col-md-6"><label class="form-label" for="f_campaign_id">कैंपेन (वैकल्पिक)</label>
            <select class="form-select<?= error('campaign_id') ? ' is-invalid' : '' ?>" id="f_campaign_id" name="campaign_id" data-inv-camp><option value="">— कोई नहीं —</option>
              <?php $cs = (int) old('campaign_id', $campaign ?? 0); foreach ($campaigns as $c): ?><option value="<?= (int) $c['id'] ?>" data-adv="<?= (int) $c['advertiser_id'] ?>"<?= selected($c['id'], $cs) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
            <?php if (error('campaign_id')): ?><div class="invalid-feedback d-block"><?= e(error('campaign_id')) ?></div><?php endif; ?></div>
        </div>
        <div class="table-responsive mt-3"><table class="table inv-lines mb-2">
          <thead><tr><th>विवरण</th><th style="width:110px" class="text-end">मात्रा</th><th style="width:150px" class="text-end">दर (₹)</th><th style="width:130px" class="text-end">राशि</th><th style="width:44px"></th></tr></thead>
          <tbody data-lines><?php foreach ($rows as $i => $l): ?><?= $row((array) $l, (string) $i) ?><?php endforeach; ?></tbody>
        </table></div>
        <?php if (error('items')): ?><div class="alert alert-danger py-2"><?= e(error('items')) ?></div><?php endif; ?>
        <template data-line-template><?= $row([], '__i__') ?></template>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-line-add><i class="fa-solid fa-plus me-1"></i>पंक्ति जोड़ें</button>
        <div class="inv-totals mt-3">
          <div><span>राशि</span><b data-sub>₹0.00</b></div>
          <div><span>GST (<span data-rate-show>0</span>%)</span><b data-tax>₹0.00</b></div>
          <div class="grand"><span>कुल</span><span data-total>₹0.00</span></div>
        </div>
        <p class="small text-body-secondary mb-0">अंतिम जोड़ सर्वर पर गिना जाता है।</p>
      </div></section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl"><div class="panel-body">
        <?= field('date', 'issue_date', 'इनवॉइस की तारीख़', $inv['issue_date'] ?? date('Y-m-d'), ['required' => true]) ?>
        <?= field('date', 'due_date', 'भुगतान की अंतिम तारीख़', $inv['due_date'] ?? date('Y-m-d', strtotime('+15 days'))) ?>
        <?= field('number', 'tax_rate', 'GST %', $inv['tax_rate'] ?? setting('billing_tax_rate', '18'), ['attrs' => ['min' => 0, 'max' => 50, 'step' => '0.01', 'data-tax-rate' => true]]) ?>
        <?= field('textarea', 'notes', 'नोट (इनवॉइस पर छपेगा)', $inv['notes'] ?? '', ['rows' => 3, 'attrs' => ['maxlength' => 2000]]) ?>
        <div class="d-grid gap-2">
          <?php if ($isNew): ?><button class="btn btn-brand" type="submit" name="action" value="send"><i class="fa-solid fa-paper-plane me-1"></i> बनाएँ और “भेजा गया” करें</button>
            <button class="btn btn-outline-secondary" type="submit" name="action" value="draft">ड्राफ़्ट सेव करें</button>
          <?php else: ?><button class="btn btn-brand" type="submit">सेव करें</button><a class="btn btn-light" href="<?= e(route('admin.invoices.show', ['id' => $inv['id']])) ?>">वापस</a><?php endif; ?>
        </div>
      </div></section>
    </div>
  </div>
</form>
<?php endif; ?>
