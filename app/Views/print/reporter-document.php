<?php
/**
 * रिपोर्टर दस्तावेज़ का प्रिंट पेज: ID कार्ड (CR80, आगे-पीछे) या A4 पत्र।
 * ब्राउज़र से प्रिंट → "PDF में सेव"। कोई बाहरी लाइब्रेरी नहीं; QR लोकल qrcode-generator से।
 */
use App\Models\Reporter;
use App\Models\ReporterDocument;
$type = $doc['type'];
$site = (string) setting('site_name');
$brand = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('primary_color')) ? setting('primary_color') : '#d71920';
$logo = setting('logo') ? upload_url(setting('logo')) : null;
$revoked = $doc['status'] !== 'active';
$sig = setting('signature_image') ? upload_url(setting('signature_image')) : null;
$stamp = setting('stamp_image') ? upload_url(setting('stamp_image')) : null;
$docName = ReporterDocument::TYPES[$type][0];
// ID कार्ड का आकार: सेटिंग से; प्रीव्यू के लिए ?orient= से बदल सकते हैं
$orient = in_array($_GET['orient'] ?? '', ['landscape', 'portrait'], true) ? $_GET['orient'] : (setting('id_card_orientation', 'landscape') === 'portrait' ? 'portrait' : 'landscape');
// पत्र/प्रमाणपत्र की सामग्री: एडमिन के टेम्पलेट (सेटिंग → पत्र और प्रमाणपत्र) से, कंट्रोलर में बनी ($content)
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($docName . ' · ' . $name . ' · ' . $doc['doc_no']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('vendor/fontawesome/css/all.min.css') ?>">
<style>
:root { --b: <?= e($brand) ?>; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e9ec; font-family: "Noto Sans Devanagari", "Mukta", sans-serif; color: #1b1b1b; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position: sticky; top: 0; background: #1b1b1f; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; font-family: Mukta, sans-serif; z-index: 5; }
.toolbar button { background: var(--b); color: #fff; border: 0; padding: 7px 16px; border-radius: 4px; font: inherit; font-weight: 700; cursor: pointer; }
.toolbar span { opacity: .8; font-size: 14px; }
.sheet { margin: 20px auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.12); position: relative; overflow: hidden; }
.revoked::after { content: "रद्द / अमान्य"; position: absolute; inset: 0; display: grid; place-items: center; font: 800 64px Mukta, sans-serif; color: rgba(200,0,0,.28); transform: rotate(-24deg); pointer-events: none; }
/* ID कार्ड: CR80, आड़ा (85.6 × 54 mm) या खड़ा (54 × 85.6 mm), आगे-पीछे
   --k = सामग्री का आकार; लंबा नाम/पता हो तो JS इसे घटाकर सब कुछ कार्ड में फ़िट करता है */
.cards { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding: 24px 16px; }
.card { --k: 1; --bd: color-mix(in srgb, var(--b) 72%, #000); width: 85.6mm; height: 54mm; border-radius: 3.2mm; background: #fff; box-shadow: 0 6px 24px rgba(0,0,0,.18); position: relative; overflow: hidden;
  font-family: Mukta, "Noto Sans Devanagari", sans-serif; color: #1c1c22; display: flex; flex-direction: column; }
.card.portrait { width: 54mm; height: 85.6mm; }
.card .fa-solid, .card .fa-brands { width: 2.8mm; text-align: center; color: var(--b); font-size: calc(2.3mm * var(--k)); flex: none; }
/* आगे */
.card .band { position: relative; flex: none; height: 12mm; background: linear-gradient(115deg, var(--b) 0%, var(--b) 55%, var(--bd) 100%); color: #fff; display: flex; align-items: center; gap: 2mm; padding: 0 3mm; z-index: 2; }
.card .band::after { content: ""; position: absolute; left: 0; right: 0; bottom: -.9mm; height: .9mm; background: linear-gradient(90deg, #f5b800, #ffd84d, #f5b800); }
.card .logo { background: #fff; border-radius: 1.4mm; padding: .6mm 1.2mm; height: 9mm; display: flex; align-items: center; box-shadow: 0 .4mm 1.2mm rgba(0,0,0,.18); min-width: 0; }
.card .logo img { max-height: 7.6mm; max-width: 30mm; display: block; }
.card .band .site { min-width: 0; line-height: 1.05; }
.card .band .site b { display: block; font-size: 3.5mm; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card .band .site small { font-size: 2.1mm; opacity: .9; }
.card .press { margin-left: auto; background: #fff; color: var(--b); font-weight: 800; font-size: 2.9mm; letter-spacing: .5mm; padding: .9mm 2.2mm; border-radius: 1.2mm; box-shadow: 0 .4mm 1.2mm rgba(0,0,0,.2); line-height: 1; flex: none; }
.card .body { flex: 1; min-height: 0; overflow: hidden; display: grid; grid-template-columns: 19.5mm minmax(0, 1fr) 14.5mm; gap: 2.6mm; padding: 3mm 3mm 1mm; position: relative; z-index: 1; align-content: start; }
.card .ph { display: grid; gap: 1mm; justify-items: center; align-content: start; }
.card .photo { width: 19.5mm; height: 24mm; object-fit: cover; border-radius: 1.6mm; border: .6mm solid var(--b); background: #eef0f3; box-shadow: 0 .5mm 1.5mm rgba(0,0,0,.15); display: block; }
.card .ph-empty { display: grid; place-items: center; } .card .ph-empty .fa-solid { width: auto; font-size: 11mm; color: #c5c9d1; }
.card .blood { font-size: 2.1mm; font-weight: 800; color: #b40000; background: #ffe9e9; border-radius: 1mm; padding: .2mm 1.2mm; white-space: nowrap; }
.card .info { min-width: 0; }
.card .nm { font-size: calc(3.9mm * var(--kn, 1) * var(--k)); font-weight: 800; line-height: 1.12; text-transform: uppercase; letter-spacing: .1mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
.card .ds { display: inline-block; margin: .8mm 0 1.2mm; background: color-mix(in srgb, var(--b) 12%, #fff); color: var(--b); font-weight: 800; font-size: calc(2.6mm * var(--kn, 1) * var(--k)); line-height: 1.1; padding: .8mm 1.6mm; border-radius: 1mm; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.card .row { display: flex; gap: 1.1mm; align-items: flex-start; font-size: calc(2.45mm * var(--k)); line-height: 1.3; margin-bottom: calc(.45mm * var(--k)); min-width: 0; }
.card .row .v { min-width: 0; overflow: hidden; overflow-wrap: anywhere; }
.card .row b { font-weight: 800; }
.card .row .clamp { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.card .row .fa-solid { margin-top: .35mm; }
.card .qrcol { display: grid; justify-items: center; align-content: start; gap: .6mm; }
.card .qr { width: 14.5mm; height: 14.5mm; padding: .6mm; background: #fff; border: .3mm solid #dfe1e6; border-radius: 1mm; }
.card .qr svg, .card .qr img { width: 100%; height: 100%; display: block; }
.card .qrcol small { font-size: 1.8mm; color: #666; text-align: center; line-height: 1.1; }
.card .wm { position: absolute; right: -9mm; bottom: -12mm; width: 38mm; height: 38mm; border-radius: 50%; background: radial-gradient(circle, color-mix(in srgb, var(--b) 9%, transparent) 0 60%, transparent 61%); z-index: 0; }
.card .foot { flex: none; height: 5.2mm; background: #1c1c22; color: #fff; font-size: 2.3mm; padding: 0 3mm; display: flex; align-items: center; justify-content: space-between; gap: 2mm; z-index: 1; white-space: nowrap; overflow: hidden; }
.card .foot b { color: #ffd84d; font-weight: 700; }
.card .foot .fa-solid { color: #ffd84d; }

/* आगे: खड़ा (Portrait) — ऊपर लोगो, बीच में बड़ी फ़ोटो, नाम, फिर जानकारी + QR */
.card.portrait .band { height: 15mm; flex-direction: column; justify-content: center; gap: .8mm; padding: 0 3mm; text-align: center; }
.card.portrait .logo { height: 8.6mm; }
.card.portrait .logo img { max-height: 7.2mm; max-width: 40mm; }
.card.portrait .band .site b { font-size: 3.4mm; }
.card.portrait .press { position: absolute; right: 2mm; bottom: -2.6mm; margin: 0; font-size: 2.2mm; padding: .7mm 1.6mm; z-index: 3; }
.card.portrait .body { grid-template-columns: minmax(0, 1fr) 13mm; grid-template-areas: "ph ph" "idn idn" "rows qr"; gap: 1.6mm 2mm; padding: 3.2mm 3mm 1mm; }
.card.portrait .ph { grid-area: ph; }
.card.portrait .photo { width: 21mm; height: 26mm; border-width: .7mm; }
.card.portrait .info { display: contents; }
.card.portrait .idn { grid-area: idn; text-align: center; min-width: 0; }
.card.portrait .nm { font-size: calc(3.6mm * var(--kn, 1) * var(--k)); }
.card.portrait .ds { margin: .8mm 0 0; }
.card.portrait .rows { grid-area: rows; min-width: 0; }
.card.portrait .qrcol { grid-area: qr; }
.card.portrait .qr { width: 13mm; height: 13mm; }
.card.portrait .wm { right: -14mm; bottom: -6mm; }
.card.portrait .foot { flex-direction: column; justify-content: center; gap: 0; height: 7mm; font-size: 2.1mm; line-height: 1.25; }

/* पीछे */
.card.back .bk-wm { position: absolute; left: 50%; top: 58%; transform: translate(-50%, -50%); max-width: 46mm; max-height: 22mm; opacity: .07; mix-blend-mode: multiply; pointer-events: none; }
.card.back .bk-top { flex: none; height: 2.2mm; background: linear-gradient(90deg, var(--b), var(--bd)); }
.card.back .bk-head { flex: none; display: flex; align-items: center; gap: 2.6mm; padding: 2.4mm 3.5mm 1.8mm; border-bottom: .3mm dashed #d9dbe0; min-width: 0; }
.card.back .bk-head img { max-height: 11mm; max-width: 34mm; display: block; }
.card.back .bk-head .t { min-width: 0; }
.card.back .bk-head .t b { display: block; font-size: calc(3.6mm * var(--k)); font-weight: 800; color: var(--b); line-height: 1.1; }
.card.back .bk-head .t small { font-size: calc(2.2mm * var(--k)); color: #555; }
.card.back .bk-body { flex: 1; min-height: 0; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1fr) 23mm; gap: 2.5mm; padding: 2.2mm 3.5mm 1.2mm; position: relative; z-index: 1; }
.card.back .bk-body > div:first-child { display: flex; flex-direction: column; min-width: 0; }
.card.back .row { margin-bottom: calc(.8mm * var(--k)); }
.card.back .note { font-size: calc(2.05mm * var(--k)); color: #555; line-height: 1.3; margin-top: auto; padding: 1mm 1.5mm; background: #f6f7f9; border-left: .6mm solid var(--b); border-radius: .8mm; }
.card.back .sig { position: relative; text-align: center; font-size: 2.2mm; align-self: end; line-height: 1.2; }
.card.back .sig img.s { max-height: 9mm; max-width: 23mm; display: block; margin: 0 auto -.5mm; position: relative; z-index: 1; }
.card.back .sig img.st { position: absolute; left: 50%; top: -6mm; transform: translateX(-50%) rotate(-12deg); max-height: 15mm; opacity: .55; z-index: 0; }
.card.back .sig b { display: block; font-size: 2.3mm; border-top: .25mm solid #999; padding-top: .5mm; }
.card.back .foot { background: linear-gradient(90deg, var(--b), var(--bd)); justify-content: center; font-weight: 600; }
.card.back .foot .fa-solid { color: #fff; }
/* पीछे: खड़ा — लोगो बीच में, जानकारी, नोट, नीचे हस्ताक्षर */
.card.back.portrait .bk-head { flex-direction: column; text-align: center; gap: 1.2mm; padding: 3mm 3mm 2mm; }
.card.back.portrait .bk-head img { max-height: 12mm; max-width: 40mm; }
.card.back.portrait .bk-body { grid-template-columns: minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) auto; gap: 2mm; padding: 2.4mm 3mm 1.2mm; }
.card.back.portrait .sig { justify-self: center; width: 30mm; }
.card.back.portrait .foot { height: 6mm; font-size: 2.05mm; }
.orient-switch { display: inline-flex; border: 1px solid #555; border-radius: 6px; overflow: hidden; margin-left: auto; }
.orient-switch a { color: #ddd; text-decoration: none; padding: 5px 12px; font-size: 14px; }
.orient-switch a.on { background: var(--b); color: #fff; font-weight: 700; }
/* A4 पत्र */
.a4 { width: 210mm; min-height: 297mm; padding: 16mm 18mm; }
.lh { display: flex; align-items: center; gap: 5mm; border-bottom: 1.2mm solid var(--b); padding-bottom: 4mm; }
.lh img { max-height: 20mm; max-width: 60mm; }
.lh h1 { margin: 0; font: 800 9mm/1.1 Mukta, sans-serif; color: var(--b); }
.lh p { margin: 1mm 0 0; font-size: 3.2mm; color: #555; }
.ref { display: flex; justify-content: space-between; font-size: 3.4mm; margin: 6mm 0; }
.title { text-align: center; font: 800 6.5mm Mukta, sans-serif; margin: 6mm 0; text-decoration: underline; text-underline-offset: 2mm; }
.body-text { font-size: 4mm; line-height: 1.9; text-align: justify; }
.body-text p { margin: 0 0 4mm; } .body-text ol, .body-text ul { margin: 0 0 4mm; padding-left: 7mm; } .body-text li { margin-bottom: 1mm; }
.sign { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 18mm; }
.sign .who { text-align: center; font-size: 3.6mm; }
.sign .who img { max-height: 16mm; max-width: 50mm; display: block; margin: 0 auto -2mm; }
.sign .stamp { max-height: 28mm; opacity: .9; }
.sign .qrbox { text-align: center; font-size: 2.8mm; color: #555; }
.sign .qrbox div { width: 26mm; height: 26mm; margin: 0 auto 1mm; }
.sign .qrbox svg { width: 100%; height: 100%; }
.a4 .photo-letter { float: right; width: 28mm; height: 34mm; object-fit: cover; border: .6mm solid #ccc; margin: 0 0 3mm 5mm; }
.foot-note { position: absolute; left: 18mm; right: 18mm; bottom: 10mm; font-size: 2.9mm; color: #777; border-top: .3mm solid #ddd; padding-top: 2mm; text-align: center; }
@page { size: <?= $type === 'id_card' ? 'A4' : 'A4' ?>; margin: <?= $type === 'id_card' ? '10mm' : '0' ?>; }
@media print {
  body { background: #fff; }
  .toolbar { display: none; }
  .sheet, .card { box-shadow: none; margin: 0; }
  .card { border: .2mm solid #bbb; }
  .cards { padding: 0; justify-content: flex-start; }
}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">प्रिंट / PDF में सेव</button><span><?= e($docName) ?> · <?= e($doc['doc_no']) ?><?= $revoked ? ' · रद्द' : '' ?></span>
  <?php if (!empty($canRefresh)): ?><form method="post" action="<?= e(route('admin.reporters.document.refresh', ['id' => $r['id'], 'doc' => $doc['id']])) ?>" style="margin-left:auto" onsubmit="return confirm('इस पत्र की सामग्री अभी के टेम्पलेट (सेटिंग → पत्र और प्रमाणपत्र) से दोबारा बनेगी। आगे बढ़ें?')"><?= csrf_field() ?><button type="submit" style="background:#3a3a42">नए टेम्पलेट से अपडेट करें</button></form><?php endif; ?>
  <?php if ($type === 'id_card'): ?><nav class="orient-switch" aria-label="कार्ड का आकार"><?php foreach (['landscape' => 'आड़ा', 'portrait' => 'खड़ा'] as $o => $l): ?><a href="?orient=<?= $o ?>" class="<?= $orient === $o ? 'on' : '' ?>"<?= $orient === $o ? ' aria-current="true"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav><?php endif; ?></div>

<?php if ($type === 'id_card'):
    $host = parse_url(url(), PHP_URL_HOST) ?: '';
    $mob = preg_replace('/\D/', '', (string) $r['mobile']);
    $mobTxt = $mob !== '' ? (strlen($mob) === 10 ? '+91 ' . substr($mob, 0, 5) . ' ' . substr($mob, 5) : (string) $r['mobile']) : '';
    $addr = trim((string) $r['address']);
    $showMob = setting('id_card_show_mobile', '1') === '1' && $mobTxt !== '';
    $showAddr = setting('id_card_show_address', '1') === '1' && $addr !== '';
    $valid = date('d-m-Y', strtotime((string) ($doc['valid_until'] ?: $r['valid_until'])));
    $phones = array_values(array_unique(array_filter([setting('contact_phone'), setting('whatsapp') && preg_replace('/\D/', '', (string) setting('whatsapp')) !== preg_replace('/\D/', '', (string) setting('contact_phone')) ? setting('whatsapp') : null])));
?>
<div class="cards">
  <!-- आगे -->
  <div class="card front <?= $orient ?><?= $revoked ? ' revoked' : '' ?>">
    <div class="band">
      <?php if ($logo): ?><span class="logo"><img src="<?= e($logo) ?>" alt=""></span><?php endif; ?>
      <?php if (!$logo): ?><span class="site"><b><?= e($site) ?></b><?php if (setting('tagline')): ?><small><?= e(setting('tagline')) ?></small><?php endif; ?></span><?php endif; ?>
      <span class="press">PRESS</span>
    </div>
    <span class="wm" aria-hidden="true"></span>
    <div class="body" data-fit>
      <div class="ph">
        <?php if ($r['photo']): ?><img class="photo" src="<?= e(upload_url($r['photo'])) ?>" alt=""><?php else: ?><div class="photo ph-empty"><i class="fa-solid fa-user"></i></div><?php endif; ?>
        <?php if ($r['blood_group']): ?><span class="blood"><i class="fa-solid fa-droplet" style="color:#b40000"></i> <?= e($r['blood_group']) ?></span><?php endif; ?>
      </div>
      <div class="info">
        <div class="idn">
          <div class="nm"><?= e($name) ?></div>
          <div class="ds"><?= e($r['designation']) ?></div>
        </div>
        <div class="rows">
          <div class="row"><i class="fa-solid fa-id-badge"></i><span class="v">ID: <b><?= e($r['reporter_code']) ?></b></span></div>
          <?php if ($showMob): ?><div class="row"><i class="fa-solid fa-phone"></i><span class="v"><b><?= e($mobTxt) ?></b></span></div><?php endif; ?>
          <?php if ($areaTxt = implode(', ', array_filter([$area && $area !== $district ? $area : null, $district, $state]))): ?><div class="row"><i class="fa-solid fa-location-dot"></i><span class="v"><?= e($areaTxt) ?></span></div><?php endif; ?>
          <?php if ($showAddr): ?><div class="row"><i class="fa-solid fa-house"></i><span class="v clamp"><?= e($addr) ?></span></div><?php endif; ?>
          <div class="row"><i class="fa-solid fa-calendar-check"></i><span class="v">वैधता: <b><?= e($valid) ?></b></span></div>
        </div>
      </div>
      <div class="qrcol"><div class="qr" data-qr="<?= e($verifyUrl) ?>"></div><small>सत्यापन के लिए<br>स्कैन करें</small></div>
    </div>
    <div class="foot"><span>कार्ड: <b><?= e($doc['doc_no']) ?></b></span><span><i class="fa-solid fa-globe"></i> <?= e($host) ?></span></div>
  </div>

  <!-- पीछे -->
  <div class="card back <?= $orient ?><?= $revoked ? ' revoked' : '' ?>">
    <div class="bk-top"></div>
    <?php if ($logo): ?><img class="bk-wm" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <div class="bk-head">
      <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
      <div class="t"><b><?= e($site) ?></b><?php if (setting('tagline')): ?><small><?= e(setting('tagline')) ?></small><?php elseif (setting('registration_no')): ?><small>पंजीकरण: <?= e(setting('registration_no')) ?></small><?php endif; ?></div>
    </div>
    <div class="bk-body" data-fit>
      <div>
        <?php if (setting('address')): ?><div class="row"><i class="fa-solid fa-location-dot"></i><span class="v clamp"><?= e(setting('address')) ?></span></div><?php endif; ?>
        <?php if ($phones): ?><div class="row"><i class="fa-solid fa-phone"></i><span class="v"><b><?= e(implode(', ', $phones)) ?></b></span></div><?php endif; ?>
        <?php if (setting('contact_email')): ?><div class="row"><i class="fa-solid fa-envelope"></i><span class="v"><?= e(setting('contact_email')) ?></span></div><?php endif; ?>
        <div class="row"><i class="fa-solid fa-globe"></i><span class="v"><?= e($host) ?></span></div>
        <?php if (setting('id_card_note')): ?><div class="note"><?= e(setting('id_card_note')) ?></div><?php endif; ?>
      </div>
      <div class="sig">
        <?php if ($stamp): ?><img class="st" src="<?= e($stamp) ?>" alt=""><?php endif; ?>
        <?php if ($sig): ?><img class="s" src="<?= e($sig) ?>" alt=""><?php endif; ?>
        <b><?= e(setting('signatory_designation', 'प्रधान संपादक')) ?></b>
        <?php if (setting('signatory_name')): ?><span><?= e(setting('signatory_name')) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="foot"><span><i class="fa-solid fa-shield-halved"></i> सत्यापन: <?= e(preg_replace('~^https?://~', '', route('verify'))) ?></span></div>
  </div>
</div>
<?php else: ?>
<div class="sheet a4<?= $revoked ? ' revoked' : '' ?>">
  <div class="lh">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <div><h1><?= e($site) ?></h1><p><?= e(implode(' · ', array_filter([setting('address'), setting('contact_phone'), setting('contact_email')]))) ?></p><?php if (setting('registration_no')): ?><p>पंजीकरण: <?= e(setting('registration_no')) ?></p><?php endif; ?></div>
  </div>
  <div class="ref"><span>पत्र संख्या: <b><?= e($doc['doc_no']) ?></b></span><span>दिनांक: <b><?= hindi_date($doc['issued_at']) ?></b></span></div>
  <div class="title"><?= e($content['title'] ?? $docName) ?></div>
  <?php if (in_array($type, ['authorization', 'press_certificate'], true) && $r['photo']): ?><img class="photo-letter" src="<?= e(upload_url($r['photo'])) ?>" alt=""><?php endif; ?>
  <div class="body-text"><?= $content['body'] ?? '' /* DocumentTemplateService में हर मान e() से */ ?></div>
  <div class="sign">
    <div class="qrbox"><div data-qr="<?= e($verifyUrl) ?>"></div>सत्यापन के लिए स्कैन करें</div>
    <?php if ($stamp): ?><img class="stamp" src="<?= e($stamp) ?>" alt=""><?php endif; ?>
    <div class="who"><?php if ($sig): ?><img src="<?= e($sig) ?>" alt=""><?php endif; ?><b><?= e(setting('signatory_name') ?: '__________________') ?></b><br><?= e(setting('signatory_designation', 'प्रधान संपादक')) ?><br><?= e($site) ?></div>
  </div>
  <?php if ($type === 'appointment' && $signature): ?><p style="margin-top:10mm;font-size:3.4mm">मैंने ऊपर लिखी शर्तें पढ़कर स्वीकार की हैं।<br><img src="<?= e($signature) ?>" alt="" style="max-height:14mm"><br>(<?= e($name) ?>)</p><?php endif; ?>
  <div class="foot-note">यह दस्तावेज़ <?= e($site) ?> द्वारा जारी किया गया है। सत्यता जाँचें: <?= e(route('verify')) ?> (रिपोर्टर ID: <?= e($r['reporter_code']) ?>)</div>
</div>
<?php endif; ?>

<script src="<?= asset('vendor/qrcode/qrcode.js') ?>"></script>
<script>
// लंबा नाम/पता/नोट हो तो अक्षर थोड़े छोटे करके सब कुछ कार्ड के अंदर (प्रिंट से पहले भी)
function fitCards() {
  var bad = function (els) { return Array.prototype.some.call(els, function (f) { return f.scrollHeight > f.clientHeight + 1 || f.scrollWidth > f.clientWidth + 1; }); };
  document.querySelectorAll('.card').forEach(function (c) {
    var k = 1, kn = 1; c.style.setProperty('--k', k); c.style.setProperty('--kn', kn);
    // 1) पहले सिर्फ़ नाम/पद छोटे करें (लंबा नाम "…" न बने)
    while (bad(c.querySelectorAll('.nm, .ds')) && kn > 0.6) { kn = Math.round((kn - 0.04) * 100) / 100; c.style.setProperty('--kn', kn); }
    // 2) फिर बाकी सामग्री (पता, नोट, पूरा हिस्सा) कार्ड के अंदर आने तक
    while (bad(c.querySelectorAll('[data-fit], .clamp, .note')) && k > 0.72) { k = Math.round((k - 0.04) * 100) / 100; c.style.setProperty('--k', k); }
  });
}
window.addEventListener('load', fitCards);
if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitCards);
window.addEventListener('beforeprint', fitCards);
document.querySelectorAll('[data-qr]').forEach(function (el) {
  if (!window.qrcode) return;
  var q = qrcode(0, 'M'); q.addData(el.getAttribute('data-qr')); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 2, margin: 0, scalable: true });
});
</script>
</body>
</html>
