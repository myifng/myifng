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
$hon = ['male' => 'श्री', 'female' => 'सुश्री'][$gender ?? ''] ?? 'श्री/सुश्री';
$rel = ['male' => 'पुत्र', 'female' => 'पुत्री/पत्नी'][$gender ?? ''] ?? 'पुत्र/पुत्री/पत्नी';
$areaText = implode(', ', array_filter([$area && $area !== $district ? $area : null, $district, $state])) ?: 'संस्थान द्वारा तय क्षेत्र';
$revoked = $doc['status'] !== 'active';
$sig = setting('signature_image') ? upload_url(setting('signature_image')) : null;
$stamp = setting('stamp_image') ? upload_url(setting('stamp_image')) : null;
$endDate = $r['status'] === 'resigned' ? date('Y-m-d', strtotime((string) $r['updated_at'])) : date('Y-m-d');
$docName = ReporterDocument::TYPES[$type][0];
$bodies = [
    'authorization' => "प्रमाणित किया जाता है कि $hon <b>" . e($name) . '</b>, ' . $rel . ' ' . e($r['guardian_name'] ?: '—') . ', रिपोर्टर ID <b>' . e($r['reporter_code']) . '</b>, '
        . e($site) . ' में <b>' . e($r['designation']) . '</b> के रूप में <b>' . e($areaText) . '</b> क्षेत्र में समाचार संकलन, फ़ोटो/वीडियो कवरेज और संबंधित पत्रकारिता कार्यों के लिए अधिकृत हैं।'
        . '<br><br>संबंधित विभागों और अधिकारियों से अनुरोध है कि इन्हें समाचार संकलन में आवश्यक सहयोग प्रदान करें। यह अधिकार पत्र <b>' . hindi_date($doc['valid_until'] ?: $r['valid_until']) . '</b> तक मान्य है। '
        . 'इस पत्र का उपयोग किसी भी प्रकार की वसूली, दबाव या निजी लाभ के लिए वर्जित है।',
    'appointment' => "$hon <b>" . e($name) . '</b>,<br><br>आपके आवेदन और साक्षात्कार/सत्यापन के आधार पर आपको दिनांक <b>' . hindi_date($r['joining_date']) . '</b> से ' . e($site) . ' में <b>' . e($r['designation']) . '</b> ('
        . e(Reporter::TYPES[$r['reporter_type']] ?? $r['reporter_type']) . ') के पद पर नियुक्त किया जाता है। आपका कार्यक्षेत्र <b>' . e($areaText) . '</b>' . ($bureau ? ' तथा ब्यूरो <b>' . e($bureau) . '</b>' : '') . ($beat ? '; बीट: <b>' . e($beat) . '</b>' : '') . ' रहेगा।'
        . '<br><br>शर्तें:<ol><li>आप संस्थान की संपादकीय नीति, आचार संहिता और रिपोर्टर नीति का पालन करेंगे।</li><li>हर ख़बर तथ्यों की जाँच के बाद ही भेजेंगे; किसी भी ख़बर के प्रकाशन का अंतिम निर्णय संपादक का होगा।</li>'
        . '<li>संस्थान के नाम, ID कार्ड या पत्र का दुरुपयोग करने पर नियुक्ति तुरंत समाप्त की जा सकती है।</li><li>यह नियुक्ति <b>' . hindi_date($r['valid_until']) . '</b> तक मान्य है और नवीनीकरण संस्थान के निर्णय पर होगा।</li></ol>'
        . 'हम आपके उज्ज्वल भविष्य की कामना करते हैं।',
    'press_certificate' => "प्रमाणित किया जाता है कि $hon <b>" . e($name) . '</b>, रिपोर्टर ID <b>' . e($r['reporter_code']) . '</b>, ' . e($site) . ' के मान्य प्रतिनिधि (<b>' . e($r['designation']) . '</b>) हैं '
        . 'और <b>' . e($areaText) . '</b> क्षेत्र में पत्रकारिता कार्य करते हैं।<br><br>यह प्रमाणपत्र <b>' . hindi_date($doc['valid_until'] ?: $r['valid_until']) . '</b> तक मान्य है। इसकी सत्यता नीचे दिए QR या '
        . e(route('verify')) . ' पर जाँची जा सकती है।',
    'experience' => "प्रमाणित किया जाता है कि $hon <b>" . e($name) . '</b>, ' . $rel . ' ' . e($r['guardian_name'] ?: '—') . ', ने ' . e($site) . ' में दिनांक <b>' . hindi_date($r['joining_date']) . '</b> से <b>'
        . hindi_date($endDate) . '</b> तक <b>' . e($r['designation']) . '</b> के रूप में <b>' . e($areaText) . '</b> क्षेत्र में कार्य किया।<br><br>इस अवधि में इनका कार्य और आचरण संतोषजनक रहा। हम इनके भविष्य के लिए शुभकामनाएँ देते हैं।',
];
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($docName . ' · ' . $name . ' · ' . $doc['doc_no']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root { --b: <?= e($brand) ?>; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e9ec; font-family: "Noto Sans Devanagari", "Mukta", sans-serif; color: #1b1b1b; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position: sticky; top: 0; background: #1b1b1f; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; font-family: Mukta, sans-serif; z-index: 5; }
.toolbar button { background: var(--b); color: #fff; border: 0; padding: 7px 16px; border-radius: 4px; font: inherit; font-weight: 700; cursor: pointer; }
.toolbar span { opacity: .8; font-size: 14px; }
.sheet { margin: 20px auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.12); position: relative; overflow: hidden; }
.revoked::after { content: "रद्द / अमान्य"; position: absolute; inset: 0; display: grid; place-items: center; font: 800 64px Mukta, sans-serif; color: rgba(200,0,0,.28); transform: rotate(-24deg); pointer-events: none; }
/* ID कार्ड: CR80 */
.cards { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding: 20px; }
.card { width: 85.6mm; height: 54mm; border-radius: 3mm; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.15); position: relative; overflow: hidden; font-family: Mukta, sans-serif; }
.card .band { background: var(--b); color: #fff; height: 11mm; display: flex; align-items: center; gap: 2mm; padding: 0 3mm; }
.card .band img { max-height: 8mm; max-width: 30mm; background: #fff; border-radius: 1mm; padding: .5mm; }
.card .band b { font-size: 4.2mm; line-height: 1; font-weight: 800; }
.card .band small { margin-left: auto; font-size: 2.6mm; background: #fff; color: var(--b); padding: .4mm 1.5mm; border-radius: 1mm; font-weight: 800; }
.card .body { display: flex; gap: 3mm; padding: 2.5mm 3mm 0; }
.card .photo { width: 20mm; height: 25mm; object-fit: cover; border: .5mm solid var(--b); border-radius: 1mm; background: #eee; }
.card .info { flex: 1; min-width: 0; font-size: 2.7mm; line-height: 1.35; }
.card .info .nm { font-size: 3.9mm; font-weight: 800; line-height: 1.2; margin-bottom: .6mm; }
.card .info .ds { color: var(--b); font-weight: 700; font-size: 3mm; margin-bottom: .6mm; }
.card .info span { color: #666; }
.card .qr { width: 17mm; height: 17mm; align-self: flex-start; }
.card .qr svg, .card .qr img { width: 100%; height: 100%; }
.card .foot { position: absolute; left: 0; right: 0; bottom: 0; background: #1b1b1f; color: #fff; font-size: 2.4mm; padding: 1mm 3mm; display: flex; justify-content: space-between; }
.card.back { font-size: 2.6mm; }
.card.back .inner { padding: 3mm 4mm; line-height: 1.45; }
.card.back h4 { margin: 0 0 1.5mm; font-size: 3.2mm; color: var(--b); }
.card.back .sig { position: absolute; right: 4mm; bottom: 7mm; text-align: center; font-size: 2.4mm; }
.card.back .sig img { max-height: 9mm; max-width: 26mm; display: block; margin: 0 auto; }
/* A4 पत्र */
.a4 { width: 210mm; min-height: 297mm; padding: 16mm 18mm; }
.lh { display: flex; align-items: center; gap: 5mm; border-bottom: 1.2mm solid var(--b); padding-bottom: 4mm; }
.lh img { max-height: 20mm; max-width: 60mm; }
.lh h1 { margin: 0; font: 800 9mm/1.1 Mukta, sans-serif; color: var(--b); }
.lh p { margin: 1mm 0 0; font-size: 3.2mm; color: #555; }
.ref { display: flex; justify-content: space-between; font-size: 3.4mm; margin: 6mm 0; }
.title { text-align: center; font: 800 6.5mm Mukta, sans-serif; margin: 6mm 0; text-decoration: underline; text-underline-offset: 2mm; }
.body-text { font-size: 4mm; line-height: 1.9; text-align: justify; }
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
<div class="toolbar"><button type="button" onclick="window.print()">प्रिंट / PDF में सेव</button><span><?= e($docName) ?> · <?= e($doc['doc_no']) ?><?= $revoked ? ' · रद्द' : '' ?></span></div>

<?php if ($type === 'id_card'): ?>
<div class="cards">
  <div class="card<?= $revoked ? ' revoked' : '' ?>">
    <div class="band"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><b><?= e($site) ?></b><?php endif; ?><small>PRESS</small></div>
    <div class="body">
      <?php if ($r['photo']): ?><img class="photo" src="<?= e(upload_url($r['photo'])) ?>" alt=""><?php else: ?><div class="photo"></div><?php endif; ?>
      <div class="info">
        <div class="nm"><?= e($name) ?></div>
        <div class="ds"><?= e($r['designation']) ?></div>
        <div><span>ID:</span> <b><?= e($r['reporter_code']) ?></b></div>
        <div><span>क्षेत्र:</span> <?= e(implode(', ', array_filter([$district, $state])) ?: '—') ?></div>
        <div><span>वैधता:</span> <b><?= e(date('d-m-Y', strtotime((string) ($doc['valid_until'] ?: $r['valid_until'])))) ?></b></div>
      </div>
      <div class="qr" data-qr="<?= e($verifyUrl) ?>"></div>
    </div>
    <div class="foot"><span>कार्ड: <?= e($doc['doc_no']) ?></span><span><?= e(parse_url(url(), PHP_URL_HOST) ?: '') ?></span></div>
  </div>
  <div class="card back<?= $revoked ? ' revoked' : '' ?>">
    <div class="inner">
      <h4><?= e($site) ?></h4>
      <?php if (setting('address')): ?><div><?= e(setting('address')) ?></div><?php endif; ?>
      <div><?= e(implode(' · ', array_filter([setting('contact_phone'), setting('contact_email')]))) ?></div>
      <?php if ($r['blood_group']): ?><div style="margin-top:1.5mm">ब्लड ग्रुप: <b><?= e($r['blood_group']) ?></b></div><?php endif; ?>
      <div style="margin-top:1.5mm;color:#555"><?= e(setting('id_card_note')) ?></div>
      <div style="margin-top:1mm;color:#555">सत्यापन: <?= e(route('verify')) ?></div>
    </div>
    <div class="sig"><?php if ($sig): ?><img src="<?= e($sig) ?>" alt=""><?php endif; ?><?= e(setting('signatory_designation', 'प्रधान संपादक')) ?></div>
  </div>
</div>
<?php else: ?>
<div class="sheet a4<?= $revoked ? ' revoked' : '' ?>">
  <div class="lh">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <div><h1><?= e($site) ?></h1><p><?= e(implode(' · ', array_filter([setting('address'), setting('contact_phone'), setting('contact_email')]))) ?></p><?php if (setting('registration_no')): ?><p>पंजीकरण: <?= e(setting('registration_no')) ?></p><?php endif; ?></div>
  </div>
  <div class="ref"><span>पत्र संख्या: <b><?= e($doc['doc_no']) ?></b></span><span>दिनांक: <b><?= hindi_date($doc['issued_at']) ?></b></span></div>
  <div class="title"><?= e($docName) ?></div>
  <?php if (in_array($type, ['authorization', 'press_certificate'], true) && $r['photo']): ?><img class="photo-letter" src="<?= e(upload_url($r['photo'])) ?>" alt=""><?php endif; ?>
  <div class="body-text"><?= $bodies[$type] /* ऊपर हर मान e() से */ ?></div>
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
document.querySelectorAll('[data-qr]').forEach(function (el) {
  if (!window.qrcode) return;
  var q = qrcode(0, 'M'); q.addData(el.getAttribute('data-qr')); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 2, margin: 0, scalable: true });
});
</script>
</body>
</html>
