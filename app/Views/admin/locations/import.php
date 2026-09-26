<?php
use App\Services\LocationService;
$this->layout('layouts/admin');
$title = 'लोकेशन CSV इम्पोर्ट';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.locations.index')) ?>">लोकेशन</a></li><li class="breadcrumb-item active" aria-current="page">CSV इम्पोर्ट</li></ol></nav>
    <h1>CSV से लोकेशन जोड़ें</h1>
    <p>एक साथ सैकड़ों ज़िले, तहसील, शहर। पहले से मौजूद (वही पता) छोड़ दिए जाते हैं।</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.locations.export')) ?>?sample=1"><i class="fa-solid fa-download me-1"></i> नमूना फ़ाइल</a>
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.locations.export')) ?>"><i class="fa-solid fa-file-export me-1"></i> सभी का एक्सपोर्ट</a>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-6">
    <section class="panel">
      <form class="panel-body" method="post" action="<?= e(route('admin.locations.import.run')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <?= field('file', 'csv', 'CSV फ़ाइल', null, ['required' => true, 'attrs' => ['accept' => '.csv,text/csv'], 'help' => 'Excel में: File → Save As → “CSV UTF-8”. अधिकतम ' . LocationService::IMPORT_MAX_ROWS . ' पंक्तियाँ, 2 MB।']) ?>
        <button class="btn btn-brand" type="submit"><i class="fa-solid fa-file-import me-1"></i> इम्पोर्ट करें</button>
      </form>
    </section>
  </div>
  <div class="col-lg-6">
    <section class="panel">
      <div class="panel-head"><h2>फ़ाइल का प्रारूप</h2></div>
      <div class="panel-body small">
        <p>पहली पंक्ति में कॉलम के नाम:</p>
        <pre class="code-sample">type,name,name_en,slug,parent,code,is_popular
district,कुशीनगर,Kushinagar,kushinagar,uttar-pradesh,,0
city,पडरौना,Padrauna,,uttar-pradesh/kushinagar,,0</pre>
        <ul class="mb-0">
          <li><b>type</b>: state, division, district, tehsil, block, city, locality</li>
          <li><b>name</b>: हिंदी नाम (ज़रूरी) · <b>name_en</b>: अंग्रेज़ी नाम (URL इसी से)</li>
          <li><b>parent</b>: ऊपर वाली लोकेशन का URL-पता (जैसे <code>uttar-pradesh/kushinagar</code>) या उसका ID; ख़ाली = भारत के नीचे</li>
          <li>एक ही फ़ाइल में पहले ज़िला, फिर उसके शहर लिख सकते हैं</li>
          <li>एक भी पंक्ति ग़लत हो तो कुछ नहीं जुड़ता, और ग़लत पंक्तियों की सूची दिखती है</li>
        </ul>
      </div>
    </section>
  </div>
</div>
