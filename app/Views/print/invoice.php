<?php
/** इनवॉइस का प्रिंट पेज (A4): ब्राउज़र से प्रिंट → "PDF में सेव" */
use App\Models\AdInvoice;
use App\Services\AdvertiserService as M;
$site = (string) setting('site_name');
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$logo = setting('logo') ? upload_url(setting('logo')) : null;
$billName = setting('billing_name') ?: $site;
$billAddr = setting('billing_address') ?: setting('address');
$due = $inv['status'] === 'cancelled' ? 0 : round($inv['total'] - $inv['paid'], 2);
$half = round((float) $inv['tax'] / 2, 2);
$sameState = $adv['gstin'] && setting('billing_gstin') && substr((string) $adv['gstin'], 0, 2) === substr((string) setting('billing_gstin'), 0, 2);
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($inv['invoice_no'] . ' · ' . $adv['company']) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root { --b: <?= e($brand) ?>; }
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e9ec; font-family: "Noto Sans Devanagari", "Mukta", sans-serif; color: #1b1b1b; font-size: 13.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position: sticky; top: 0; background: #1b1b1f; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; font-family: Mukta, sans-serif; z-index: 5; }
.toolbar button { background: var(--b); color: #fff; border: 0; padding: 7px 16px; border-radius: 4px; font: inherit; font-weight: 700; cursor: pointer; }
.toolbar span { opacity: .8; font-size: 14px; }
.sheet { position: relative; width: 210mm; min-height: 297mm; margin: 20px auto; background: #fff; padding: 16mm 16mm 20mm; box-shadow: 0 4px 20px rgba(0,0,0,.15); overflow: hidden; }
.head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 3px solid var(--b); padding-bottom: 12px; }
.head img { max-height: 54px; max-width: 220px; }
.head h1 { margin: 0; font-family: Mukta, sans-serif; font-size: 30px; color: var(--b); letter-spacing: .03em; text-align: right; }
.org { font-size: 12.5px; line-height: 1.5; margin-top: 6px; white-space: pre-line; }
.meta { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 16px 0; }
.meta h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #777; margin: 0 0 4px; }
.meta b { font-size: 15px; }
.kv { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; justify-content: end; text-align: right; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; }
th { background: #f3f3f4; text-align: left; font-family: Mukta, sans-serif; font-size: 13px; padding: 8px; border-bottom: 2px solid #ddd; }
td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
.r { text-align: right; white-space: nowrap; }
.totals { width: 300px; margin: 12px 0 0 auto; }
.totals div { display: flex; justify-content: space-between; padding: 4px 0; }
.totals .grand { border-top: 2px solid #1b1b1b; font-weight: 800; font-size: 16px; padding-top: 6px; }
.notes { margin-top: 18px; font-size: 12.5px; white-space: pre-line; }
.terms { position: absolute; left: 16mm; right: 16mm; bottom: 14mm; border-top: 1px solid #ddd; padding-top: 8px; font-size: 11.5px; color: #555; white-space: pre-line; }
.stamp { position: absolute; top: 90mm; right: 20mm; transform: rotate(-14deg); border: 4px solid; border-radius: 8px; padding: 6px 18px; font: 800 28px Mukta, sans-serif; opacity: .35; }
.stamp.paid { color: #16833b; } .stamp.cancelled { color: #c00; }
@media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; box-shadow: none; } }
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">प्रिंट / PDF में सेव</button><span>इनवॉइस <?= e($inv['invoice_no']) ?></span></div>
<main class="sheet">
  <?php if (in_array($inv['status'], ['paid', 'cancelled'], true)): ?><div class="stamp <?= e($inv['status']) ?>"><?= $inv['status'] === 'paid' ? 'भुगतान हो गया' : 'रद्द' ?></div><?php endif; ?>
  <div class="head">
    <div><?= $logo ? '<img src="' . e($logo) . '" alt="' . e($site) . '">' : '<b style="font:800 26px Mukta">' . e($site) . '</b>' ?>
      <div class="org"><b><?= e($billName) ?></b><?= $billAddr ? "\n" . e($billAddr) : '' ?><?= setting('billing_gstin') ? "\nGSTIN: " . e(setting('billing_gstin')) : '' ?><?= setting('contact_email') ? "\n" . e(setting('contact_email')) : '' ?><?= setting('contact_phone') ? ' · ' . e(setting('contact_phone')) : '' ?></div></div>
    <div><h1>टैक्स इनवॉइस</h1>
      <div class="kv"><span>इनवॉइस नं.</span><b><?= e($inv['invoice_no']) ?></b><span>तारीख़</span><span><?= hindi_date($inv['issue_date']) ?></span>
        <?php if ($inv['due_date']): ?><span>भुगतान की अंतिम तारीख़</span><span><?= hindi_date($inv['due_date']) ?></span><?php endif; ?><span>स्थिति</span><span><?= e(AdInvoice::STATUSES[$inv['status']]) ?></span></div></div>
  </div>
  <div class="meta">
    <div><h2>बिल किसे</h2><b><?= e($adv['company']) ?></b>
      <div class="org"><?= e(trim(implode("\n", array_filter([$adv['contact_name'], trim(($adv['address'] ?? '') . ($adv['city'] ? ', ' . $adv['city'] : ''), ', '), $adv['gstin'] ? 'GSTIN: ' . $adv['gstin'] : null, $adv['phone'], $adv['email']])))) ?></div></div>
    <?php if ($campaign): ?><div><h2>कैंपेन</h2><b><?= e($campaign['name']) ?></b><div class="org"><?= $campaign['start_date'] ? hindi_date($campaign['start_date']) : '' ?><?= $campaign['end_date'] ? ' – ' . hindi_date($campaign['end_date']) : '' ?></div></div><?php endif; ?>
  </div>
  <table>
    <thead><tr><th style="width:36px">#</th><th>विवरण</th><th class="r">मात्रा</th><th class="r">दर</th><th class="r">राशि</th></tr></thead>
    <tbody><?php foreach ($lines as $i => $l): ?><tr><td><?= $i + 1 ?></td><td><?= e($l['description']) ?></td><td class="r"><?= e(rtrim(rtrim($l['qty'], '0'), '.')) ?></td><td class="r"><?= e(M::money($l['rate'])) ?></td><td class="r"><?= e(M::money($l['amount'])) ?></td></tr><?php endforeach; ?></tbody>
  </table>
  <div class="totals">
    <div><span>राशि</span><span><?= e(M::money($inv['subtotal'])) ?></span></div>
    <?php if ((float) $inv['tax_rate'] > 0): ?>
      <?php if ($sameState): ?><div><span>CGST (<?= e((float) $inv['tax_rate'] / 2) ?>%)</span><span><?= e(M::money($half)) ?></span></div><div><span>SGST (<?= e((float) $inv['tax_rate'] / 2) ?>%)</span><span><?= e(M::money($inv['tax'] - $half)) ?></span></div>
      <?php else: ?><div><span>IGST (<?= e((float) $inv['tax_rate']) ?>%)</span><span><?= e(M::money($inv['tax'])) ?></span></div><?php endif; ?>
    <?php endif; ?>
    <div class="grand"><span>कुल</span><span><?= e(M::money($inv['total'])) ?></span></div>
    <?php if ((float) $inv['paid'] > 0): ?><div><span>भुगतान</span><span><?= e(M::money($inv['paid'])) ?></span></div><div><b>बकाया</b><b><?= e(M::money($due)) ?></b></div><?php endif; ?>
  </div>
  <?php if ($inv['notes']): ?><div class="notes"><?= e($inv['notes']) ?></div><?php endif; ?>
  <div class="terms"><?= setting('billing_terms') ? e(setting('billing_terms')) . "\n" : '' ?>यह कंप्यूटर से बना इनवॉइस है।</div>
</main>
</body>
</html>
