<?php
/** दो-चरण लॉगिन: ईमेल पर आया OTP */
$isRep = ($portal ?? 'admin') === 'reporter';
$rp = $isRep ? 'reporter.' : 'admin.';
$this->layout('layouts/auth');
$title = 'OTP से पुष्टि';
$err = error('otp');
?>
<ol class="auth-progress" aria-label="लॉगिन के चरण">
  <li class="done"><span><i class="fa-solid fa-check" aria-hidden="true"></i></span>पासवर्ड</li>
  <li class="now" aria-current="step"><span>2</span>ईमेल OTP</li>
</ol>
<div class="auth-head">
  <span class="auth-icon"><i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i></span>
  <div><span class="auth-badge<?= $isRep ? '' : ' staff' ?>">दो-चरण सुरक्षा</span><h1 class="auth-title">ईमेल पर आया OTP डालें</h1></div>
</div>
<p class="auth-sub">हमने <b dir="ltr"><?= e($email) ?></b> पर 6 अंकों का कोड भेजा है। इनबॉक्स में न दिखे तो स्पैम फ़ोल्डर भी देखें।</p>
<form method="post" action="<?= e(route($rp . 'login.otp.verify')) ?>" novalidate data-otp-form>
  <?= csrf_field() ?>
  <label class="form-label" for="f_otp">OTP</label>
  <input class="form-control form-control-lg otp-input<?= $err ? ' is-invalid' : '' ?>" id="f_otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
    autocomplete="one-time-code" autofocus required placeholder="••••••" aria-describedby="otpHelp<?= $err ? ' otpErr' : '' ?>">
  <?php if ($err): ?><div class="invalid-feedback d-block" id="otpErr"><?= e($err) ?></div><?php endif; ?>
  <div class="form-text mb-3" id="otpHelp">कोड <span data-otp-left data-sec="<?= max(0, (int) $expires - time()) ?>"><?= (int) ceil(max(0, $expires - time()) / 60) ?> मिनट</span> में ख़त्म होगा।</div>
  <?php if ($days > 0): ?>
    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember_device" value="1"> <span class="form-check-label">इस डिवाइस को <?= (int) $days ?> दिन याद रखें (यहाँ दोबारा OTP नहीं माँगेगा)</span></label>
  <?php endif; ?>
  <button class="btn btn-brand w-100 btn-lg auth-submit" type="submit">पुष्टि करें और लॉगिन <i class="fa-solid fa-arrow-right ms-1"></i></button>
</form>
<form method="post" action="<?= e(route($rp . 'login.otp.resend')) ?>" class="text-center mt-3">
  <?= csrf_field() ?>
  <button class="btn btn-link btn-sm" type="submit" data-otp-resend data-wait="<?= (int) $wait ?>"<?= $wait > 0 ? ' disabled' : '' ?>><i class="fa-solid fa-rotate-right me-1"></i>OTP दोबारा भेजें<?= $wait > 0 ? ' (' . (int) $wait . ' सेकंड)' : '' ?></button>
</form>
<div class="auth-alt"><a href="<?= e(route($rp . 'login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>दूसरे खाते से लॉगिन करें</a></div>
<script>
(function () {
  var inp = document.getElementById('f_otp'), f = document.querySelector('[data-otp-form]');
  // सिर्फ़ अंक; 6 पूरे होते ही अपने-आप भेजें
  inp.addEventListener('input', function () { inp.value = inp.value.replace(/\D/g, '').slice(0, 6); if (inp.value.length === 6 && !f.dataset.sent) { f.dataset.sent = 1; f.submit(); } });
  var b = document.querySelector('[data-otp-resend]'), w = +b.dataset.wait;
  if (w > 0) { var t = setInterval(function () { w--; if (w <= 0) { clearInterval(t); b.disabled = false; b.lastChild.nodeValue = 'OTP दोबारा भेजें'; } else { b.lastChild.nodeValue = 'OTP दोबारा भेजें (' + w + ' सेकंड)'; } }, 1000); }
  var l = document.querySelector('[data-otp-left]'), exp = Date.now() + l.dataset.sec * 1000; // सर्वर से बचे सेकंड (घड़ी के फ़र्क़ से बचाव)
  setInterval(function () { var s = Math.max(0, Math.round((exp - Date.now()) / 1000)); l.textContent = s > 0 ? Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2) + ' मिनट' : 'अभी (समय ख़त्म, दोबारा लॉगिन करें)'; }, 1000);
})();
</script>
