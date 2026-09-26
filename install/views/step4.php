<?php $f = st('admin_form', ['name' => '', 'email' => '', 'mobile' => '']); ?>
<form method="post" class="panel" autocomplete="off">
  <input type="hidden" name="_t" value="<?= h(itoken()) ?>">
  <div class="panel-head"><h2>कदम 4: Super Admin खाता</h2><span class="badge text-bg-success"><i class="fa-solid fa-check me-1"></i><?= count(st('migrated', [])) ?> migration पूरी</span></div>
  <div class="panel-body">
    <p class="text-body-secondary">यह खाता पूरे सिस्टम का मालिक होगा। इसकी सभी अनुमतियाँ अपने आप रहेंगी।</p>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label" for="name">पूरा नाम <span class="text-danger">*</span></label><input class="form-control" id="name" name="name" value="<?= h($f['name']) ?>" required></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="mobile">मोबाइल</label><input class="form-control" id="mobile" name="mobile" value="<?= h($f['mobile']) ?>" inputmode="numeric" maxlength="14"></div>
      <div class="col-md-12 mb-3"><label class="form-label" for="email">ईमेल (लॉगिन के लिए) <span class="text-danger">*</span></label><input class="form-control" id="email" name="email" type="email" value="<?= h($f['email']) ?>" required autocomplete="username"></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="password">पासवर्ड <span class="text-danger">*</span></label><input class="form-control" id="password" name="password" type="password" required minlength="8" autocomplete="new-password"><div class="form-text">कम से कम 8 अक्षर, अक्षर और अंक दोनों</div></div>
      <div class="col-md-6 mb-3"><label class="form-label" for="password_confirmation">पासवर्ड दोबारा <span class="text-danger">*</span></label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    </div>
  </div>
  <div class="panel-foot"><button class="btn btn-brand" type="submit">खाता बनाएँ और आगे बढ़ें <i class="fa-solid fa-arrow-right ms-1"></i></button></div>
</form>
