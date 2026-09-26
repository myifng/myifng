<?php
$f = st('site_form', ['site_name' => '', 'tagline' => '', 'site_url' => detected_url(), 'contact_email' => st('admin_form')['email'] ?? '', 'primary_color' => '#d71920', 'secondary_color' => '#15161a', 'timezone' => 'Asia/Kolkata', 'language' => 'hi', 'admin_path' => 'admin']);
$zones = ['Asia/Kolkata', 'Asia/Kathmandu', 'Asia/Dubai', 'Asia/Dhaka', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC'];
?>
<form method="post" class="panel" enctype="multipart/form-data">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 5: वेबसाइट की जानकारी</h2></div>
  <div class="panel-body">
    <p class="text-body-secondary">यह सब बाद में एडमिन → साइट सेटिंग से भी बदला जा सकता है।</p>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label" for="site_name">वेबसाइट का नाम <span class="text-danger">*</span></label><input class="form-control" id="site_name" name="site_name" value="<?= h($f['site_name']) ?>" required placeholder="जैसे: समाचार भारती"></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="tagline">टैगलाइन</label><input class="form-control" id="tagline" name="tagline" value="<?= h($f['tagline']) ?>" placeholder="जैसे: सच के साथ, सबसे पहले"></div>
      <div class="col-md-8 mb-3"><label class="form-label" for="site_url">वेबसाइट का पता (URL) <span class="text-danger">*</span></label><input class="form-control" id="site_url" name="site_url" type="url" value="<?= h($f['site_url']) ?>" required><div class="form-text">अपने आप पहचाना गया। आख़िर में / न लगाएँ।</div></div>
      <div class="col-md-4 mb-3"><label class="form-label" for="admin_path">एडमिन पैनल का पता</label><div class="input-group"><span class="input-group-text">/</span><input class="form-control" id="admin_path" name="admin_path" value="<?= h($f['admin_path']) ?>" pattern="[a-z0-9-]{3,30}"></div><div class="form-text">सुरक्षा के लिए बदल सकते हैं</div></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="contact_email">संपर्क ईमेल</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= h($f['contact_email']) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label" for="primary_color">मुख्य रंग</label><input class="form-control form-control-color w-100" id="primary_color" name="primary_color" type="color" value="<?= h($f['primary_color']) ?>"></div>
      <div class="col-md-3 mb-3"><label class="form-label" for="secondary_color">दूसरा रंग</label><input class="form-control form-control-color w-100" id="secondary_color" name="secondary_color" type="color" value="<?= h($f['secondary_color']) ?>"></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="logo">लोगो</label><input class="form-control" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><div class="form-text">PNG/WebP, चौड़ाई 400-800px सबसे अच्छी<?= st('logo') ? ' · पहले से अपलोड है' : '' ?></div></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="favicon">फ़ेविकॉन</label><input class="form-control" id="favicon" name="favicon" type="file" accept="image/png,image/x-icon,image/svg+xml,image/webp"><div class="form-text">ब्राउज़र टैब का छोटा आइकन (PNG 512×512)</div></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="timezone">टाइमज़ोन</label><select class="form-select" id="timezone" name="timezone"><?php foreach ($zones as $z): ?><option<?= $z === $f['timezone'] ? ' selected' : '' ?>><?= h($z) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="language">मुख्य भाषा</label><select class="form-select" id="language" name="language"><option value="hi"<?= $f['language'] === 'hi' ? ' selected' : '' ?>>हिंदी</option><option value="en"<?= $f['language'] === 'en' ? ' selected' : '' ?>>English</option></select></div>
    </div>
  </div>
  <div class="panel-foot"><button class="btn btn-brand" type="submit">सेव करें और आगे बढ़ें <i class="fa-solid fa-arrow-right ms-1"></i></button></div>
</form>
