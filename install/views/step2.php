<?php $f = st('db_form', ['host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => '', 'prefix' => 'np_']); ?>
<form method="post" class="panel" autocomplete="off">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 2: डेटाबेस की जानकारी</h2></div>
  <div class="panel-body">
    <div class="alert alert-light border small"><b>पहले cPanel में:</b> MySQL Databases → नया डेटाबेस बनाएँ → नया यूज़र बनाएँ → यूज़र को डेटाबेस से “All Privileges” के साथ जोड़ें। फिर वही जानकारी नीचे लिखें।</div>
    <div class="row">
      <div class="col-md-8 mb-3"><label class="form-label" for="host">डेटाबेस होस्ट</label><input class="form-control" id="host" name="host" value="<?= h($f['host']) ?>" required><div class="form-text">ज़्यादातर “localhost”</div></div>
      <div class="col-md-4 mb-3"><label class="form-label" for="port">पोर्ट</label><input class="form-control" id="port" name="port" type="number" value="<?= h($f['port']) ?>"></div>
      <div class="col-md-12 mb-3"><label class="form-label" for="name">डेटाबेस का नाम <span class="text-danger">*</span></label><input class="form-control" id="name" name="name" value="<?= h($f['name']) ?>" required placeholder="जैसे: cpanel_news"></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="user">डेटाबेस यूज़र <span class="text-danger">*</span></label><input class="form-control" id="user" name="user" value="<?= h($f['user']) ?>" required></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="pass">पासवर्ड</label><input class="form-control" id="pass" name="pass" type="password" autocomplete="new-password"></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="prefix">टेबल प्रीफ़िक्स</label><input class="form-control" id="prefix" name="prefix" value="<?= h($f['prefix']) ?>" maxlength="20"><div class="form-text">एक डेटाबेस में कई साइट हों तो हर साइट का अलग प्रीफ़िक्स रखें।</div></div>
    </div>
    <?php if (st('can_resume')): ?>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="resume" value="1" id="resume"><label class="form-check-label" for="resume">अधूरा इंस्टॉल जारी रखें (मौजूदा टेबल और डेटा बने रहेंगे)</label></div>
    <?php endif; ?>
  </div>
  <div class="panel-foot"><a class="btn btn-light" href="?step=1">← पीछे</a><button class="btn btn-brand" type="submit"><i class="fa-solid fa-plug me-1"></i>कनेक्शन जाँचें और आगे बढ़ें</button></div>
</form>
