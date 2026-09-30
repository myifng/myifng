<?php
$isRep = ($portal ?? 'admin') === 'reporter';
$rp = $isRep ? 'reporter.' : 'admin.';
$this->layout('layouts/auth');
$title = $isRep ? 'रिपोर्टर लॉगिन' : 'लॉगिन';
?>
<?php if ($isRep): ?>
  <span class="auth-badge"><i class="fa-solid fa-id-card"></i> रिपोर्टर पोर्टल</span>
  <h1 class="h3 fw-bold mb-1">रिपोर्टर लॉगिन</h1>
  <p class="text-body-secondary mb-4">अपनी ख़बरें भेजें, असाइनमेंट देखें, ID कार्ड और प्रदर्शन देखें।</p>
<?php else: ?>
  <span class="auth-badge staff"><i class="fa-solid fa-user-shield"></i> स्टाफ़ / एडमिन</span>
  <h1 class="h3 fw-bold mb-1">न्यूज़रूम में लॉगिन करें</h1>
  <p class="text-body-secondary mb-4">संपादक, डेस्क और एडमिन के लिए।</p>
<?php endif; ?>
<form method="post" action="<?= e(route($rp . 'login.submit')) ?>" novalidate>
  <?= csrf_field() ?>
  <?= field('email', 'email', 'ईमेल', '', ['required' => true, 'attrs' => ['autocomplete' => 'username', 'autofocus' => true], 'prefix' => '<i class="fa-regular fa-envelope"></i>']) ?>
  <div class="mb-2">
    <?= field('password', 'password', 'पासवर्ड', '', ['required' => true, 'wrap' => '', 'attrs' => ['autocomplete' => 'current-password'], 'prefix' => '<i class="fa-solid fa-lock"></i>']) ?>
  </div>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <label class="form-check small mb-0"><input class="form-check-input" type="checkbox" data-show-password="#f_password"> <span class="form-check-label">पासवर्ड दिखाएँ</span></label>
    <a class="small" href="<?= e(route($rp . 'password.forgot')) ?>">पासवर्ड भूल गए?</a>
  </div>
  <button class="btn btn-brand w-100 btn-lg" type="submit">लॉगिन करें <i class="fa-solid fa-arrow-right ms-1"></i></button>
</form>
<?php if ($isRep): ?>
  <div class="auth-alt">
    <?php if (app('router')->has('join') && setting('join_enabled', '1') === '1'): ?><a href="<?= e(route('join')) ?>"><i class="fa-solid fa-user-plus me-1"></i>रिपोर्टर बनना है? आवेदन करें</a><?php endif; ?>
    <?php if (app('router')->has('application.status')): ?><a href="<?= e(route('application.status')) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i>आवेदन की स्थिति</a><?php endif; ?>
    <a href="<?= e(url()) ?>"><i class="fa-solid fa-house me-1"></i>वेबसाइट पर जाएँ</a>
  </div>
<?php elseif (\App\Controllers\Admin\AuthController::separated()): ?>
  <p class="auth-alt"><a href="<?= e(route('reporter.login')) ?>"><i class="fa-solid fa-id-card me-1"></i>रिपोर्टर हैं? रिपोर्टर लॉगिन पेज पर जाएँ</a></p>
<?php endif; ?>
