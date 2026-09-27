<?php
use App\Models\AdCampaign;
use App\Models\AdInvoice;
use App\Models\AdPayment;
use App\Models\Advertiser;
use App\Services\AdService;
use App\Services\AdvertiserService;
$this->layout('layouts/admin');
$title = $adv['company'];
$money = can('advertisers.manage');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.advertisers.index')) ?>">विज्ञापनदाता</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($adv['company']) ?></li></ol></nav>
    <h1><?= e($adv['company']) ?> <span class="badge-status text-bg-<?= ['lead' => 'info', 'active' => 'success', 'inactive' => 'secondary'][$adv['status']] ?> align-middle"><i class="dot"></i><?= e(Advertiser::STATUSES[$adv['status']]) ?></span></h1>
    <p><?= e(implode(' · ', array_filter([$adv['contact_name'], $adv['phone'], $adv['email'], $adv['city'], $adv['gstin'] ? 'GSTIN ' . $adv['gstin'] : null]))) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('advertisers.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.campaigns.create')) ?>?advertiser=<?= (int) $adv['id'] ?>"><i class="fa-solid fa-bullhorn me-1"></i> नया कैंपेन</a><?php endif; ?>
    <?php if ($money): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.create')) ?>?advertiser=<?= (int) $adv['id'] ?>"><i class="fa-solid fa-file-invoice me-1"></i> इनवॉइस</a><?php endif; ?>
    <?php if (can('advertisers.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.advertisers.edit', ['id' => $adv['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
  </div>
</div>
<div class="row g-3 mb-3">
  <?php foreach (array_filter([['विज्ञापन', num($sum['ads'])], ['इम्प्रेशन', num($sum['impressions'])], ['क्लिक · CTR', num($sum['clicks']) . ' · ' . AdService::ctr($sum['impressions'], $sum['clicks'])],
      $money ? ['कुल बिल', AdvertiserService::money($sum['billed'])] : null, $money ? ['भुगतान', AdvertiserService::money($sum['paid'])] : null, $money ? ['बकाया', AdvertiserService::money($sum['due'])] : null]) as [$l, $v]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="panel h-100"><div class="panel-body kpi"><span><?= e($l) ?></span><b><?= e($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel">
      <div class="panel-head"><h2>कैंपेन</h2></div>
      <?php if ($campaigns): ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>कैंपेन</th><th>समय</th><th>भाव</th><th class="text-end">बजट / ख़र्च</th><th class="text-end">इम्प्रेशन · क्लिक</th><th>स्थिति</th></tr></thead>
        <tbody><?php foreach ($campaigns as $c): ?>
          <tr><td><a class="fw-semibold text-reset" href="<?= e(route('admin.campaigns.show', ['id' => $c['id']])) ?>"><?= e($c['name']) ?></a><div class="small text-body-secondary"><?= num($c['ads']) ?> विज्ञापन</div></td>
            <td class="small text-nowrap"><?= $c['start_date'] ? hindi_date($c['start_date']) : '—' ?> → <?= $c['end_date'] ? hindi_date($c['end_date']) : '—' ?></td>
            <td class="small"><?= e(AdCampaign::PRICING[$c['pricing']]) ?><?= $c['pricing'] !== 'fixed' ? ' @ ' . e(AdvertiserService::money($c['rate'])) : '' ?></td>
            <td class="text-end small"><?= e(AdvertiserService::money($c['budget'])) ?><?= $c['pricing'] !== 'fixed' ? '<div class="text-body-secondary">' . e(AdvertiserService::money($spend[(int) $c['id']] ?? 0)) . '</div>' : '' ?></td>
            <td class="text-end"><?= num($c['imp']) ?> · <?= num($c['clk']) ?></td>
            <td><?= e(AdCampaign::STATUSES[$c['status']]) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><div class="empty-state py-3"><p class="mb-0">अभी कोई कैंपेन नहीं।</p></div><?php endif; ?>
    </section>
    <?php if ($money): ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2>इनवॉइस</h2></div>
      <?php if ($invoices): ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>इनवॉइस</th><th>तारीख़</th><th class="text-end">कुल</th><th class="text-end">बकाया</th><th>स्थिति</th></tr></thead>
        <tbody><?php foreach ($invoices as $i): ?>
          <tr><td><a class="fw-semibold text-reset font-monospace" href="<?= e(route('admin.invoices.show', ['id' => $i['id']])) ?>"><?= e($i['invoice_no']) ?></a></td><td class="small"><?= hindi_date($i['issue_date']) ?></td>
            <td class="text-end"><?= e(AdvertiserService::money($i['total'])) ?></td><td class="text-end"><?= e(AdvertiserService::money($i['status'] === 'cancelled' ? 0 : $i['total'] - $i['paid'])) ?></td>
            <td><span class="badge text-bg-<?= e(AdInvoice::BADGE[$i['status']]) ?>"><?= e(AdInvoice::STATUSES[$i['status']]) ?></span></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><div class="empty-state py-3"><p class="mb-0">अभी कोई इनवॉइस नहीं।</p></div><?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
  <div class="col-xl-4">
    <section class="panel"><div class="panel-head"><h2>जानकारी</h2></div><div class="panel-body small">
      <?php foreach (['संपर्क' => $adv['contact_name'], 'फ़ोन' => $adv['phone'], 'ईमेल' => $adv['email'], 'GSTIN' => $adv['gstin'], 'पता' => trim(($adv['address'] ?? '') . ', ' . ($adv['city'] ?? ''), ', ')] as $k => $val): if (!$val) continue; ?>
        <div class="mb-2"><span class="text-body-secondary d-block"><?= e($k) ?></span><?= e($val) ?></div>
      <?php endforeach; ?>
      <?php if ($adv['notes']): ?><div class="mt-2 p-2 bg-body-tertiary rounded" style="white-space:pre-line"><?= e($adv['notes']) ?></div><?php endif; ?>
      <p class="text-body-secondary mt-3 mb-0">जुड़ा: <?= hindi_date($adv['created_at']) ?></p>
    </div></section>
    <?php if ($money && $payments): ?>
    <section class="panel mt-3"><div class="panel-head"><h2>हाल के भुगतान</h2></div>
      <ul class="list-group list-group-flush"><?php foreach ($payments as $p): ?><li class="list-group-item d-flex justify-content-between small"><span><?= hindi_date($p['paid_on']) ?> · <?= e(AdPayment::METHODS[$p['method']]) ?><br><span class="text-body-secondary font-monospace"><?= e($p['invoice_no']) ?></span></span><b><?= e(AdvertiserService::money($p['amount'])) ?></b></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
    <?php if (can('advertisers.delete')): ?>
      <div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">इनवॉइस हों तो नहीं हटेगा; तब “बंद” करें।</p></div>
        <?= delete_button(route('admin.advertisers.destroy', ['id' => $adv['id']]), '“' . $adv['company'] . '” और उसके कैंपेन हट जाएँगे।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
    <?php endif; ?>
  </div>
</div>
