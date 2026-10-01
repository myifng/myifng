<?php
$isRep = ($portal ?? 'admin') === 'reporter';
$rp = $isRep ? 'reporter.' : 'admin.';
$this->layout('layouts/auth');
$title = $isRep ? 'रिपोर्टर लॉगिन' : 'लॉगिन';
?>
<div class="auth-head">
  <span class="auth-icon"><i class="fa-solid <?= $isRep ? 'fa-id-card' : 'fa-user-shield' ?>" aria-hidden="true"></i></span>
  <div>
    <span class="auth-badge<?= $isRep ? '' : ' staff' ?>"><?= $isRep ? 'रिपोर्टर पोर्टल' : 'स्टाफ़ / एडमिन' ?></span>
    <h1 class="auth-title"><?= $isRep ? 'रिपोर्टर लॉगिन' : 'न्यूज़रूम में लॉगिन करें' ?></h1>
  </div>
</div>
<p class="auth-sub"><?= $isRep ? 'अपनी ख़बरें भेजें, असाइनमेंट देखें, ID कार्ड और प्रदर्शन देखें।' : 'संपादक, डेस्क और एडमिन के लिए सुरक्षित प्रवेश।' ?></p>
<form method="post" action="<?= e(route($rp . 'login.submit')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('email', 'email', 'ईमेल', '', ['required' => true, 'placeholder' => 'aapka@email.com', 'attrs' => ['autocomplete' => 'username', 'autofocus' => true, 'inputmode' => 'email'], 'prefix' => '<i class="fa-regular fa-envelope"></i>']) ?>
  <?= field('password', 'password', 'पासवर्ड', '', ['required' => true, 'wrap' => 'mb-2', 'placeholder' => '••••••••', 'attrs' => ['autocomplete' => 'current-password'], 'prefix' => '<i class="fa-solid fa-lock"></i>', 'suffix' => password_eye('f_password')]) ?>
  <div class="d-flex justify-content-end mb-4">
    <a class="small fw-semibold" href="<?= e(route($rp . 'password.forgot')) ?>">पासवर्ड भूल गए?</a>
  </div>
  <button class="btn btn-brand w-100 btn-lg auth-submit" type="submit">लॉगिन करें <i class="fa-solid fa-arrow-right ms-1"></i></button>
  <?php if (\App\Services\TwoFactorService::enabled()): ?><p class="auth-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> दो-चरण सुरक्षा चालू है: पासवर्ड के बाद ईमेल पर OTP आएगा।</p><?php endif; ?>
</form>
<?php if ($isRep): ?>
  <div class="auth-alt">
    <?php if (app('router')->has('join') && setting('join_enabled', '1') === '1'): ?><a href="<?= e(route('join')) ?>"><i class="fa-solid fa-user-plus me-1"></i>रिपोर्टर बनना है? आवेदन करें</a><?php endif; ?>
    <?php if (app('router')->has('application.status')): ?><a href="<?= e(route('application.status')) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i>आवेदन की स्थिति</a><?php endif; ?>
  </div>
<?php elseif (\App\Controllers\Admin\AuthController::separated()): ?>
  <div class="auth-alt"><a href="<?= e(route('reporter.login')) ?>"><i class="fa-solid fa-id-card me-1"></i>रिपोर्टर हैं? रिपोर्टर लॉगिन पेज पर जाएँ</a></div>
<?php endif; ?>
