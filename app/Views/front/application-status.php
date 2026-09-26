<?php
use App\Models\ReporterApplication;
$this->layout('layouts/front');
?>
<div class="wrap narrow">
  <div class="box">
    <h1 class="list-title">रिपोर्टर आवेदन की स्थिति</h1>
    <?php if ($step === 'lookup'): ?>
      <p class="list-desc">आवेदन संख्या और आवेदन में दिया मोबाइल नंबर डालें। पुष्टि के लिए आपके ईमेल पर एक कोड भेजा जाएगा।</p>
      <form method="post" action="<?= e(route('application.lookup')) ?>" class="fgrid">
        <?= csrf_field() ?>
        <?= ff('text', 'app_no', 'आवेदन संख्या', ['required' => true, 'attrs' => ['placeholder' => 'RPT-APP-2026-000001', 'maxlength' => 30, 'autocapitalize' => 'characters']]) ?>
        <?= ff('tel', 'mobile', 'मोबाइल नंबर', ['required' => true, 'attrs' => ['maxlength' => 14, 'inputmode' => 'numeric']]) ?>
        <div class="ff-wide"><button class="btn" type="submit">कोड भेजें</button></div>
      </form>
    <?php elseif ($step === 'otp'): ?>
      <p class="list-desc"><?= e($maskedEmail) ?> पर भेजा गया 6 अंकों का कोड डालें (10 मिनट तक मान्य)।</p>
      <?php if ($debugOtp): ?><p class="notice notice-warning">DEBUG मोड: कोड <b><?= e($debugOtp) ?></b> (लाइव साइट पर DEBUG बंद रखें)</p><?php endif; ?>
      <form method="post" action="<?= e(route('application.otp')) ?>" class="otp-form">
        <?= csrf_field() ?>
        <label for="otp" class="visually-hidden">कोड</label>
        <input id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="••••••">
        <button class="btn" type="submit">देखें</button>
      </form>
      <form method="post" action="<?= e(route('application.reset')) ?>"><?= csrf_field() ?><button class="linkish" type="submit">दूसरी आवेदन संख्या डालें</button></form>
    <?php else: [$l, $c] = ReporterApplication::STATUSES[$app['status']]; ?>
      <dl class="status-card">
        <dt>आवेदन संख्या</dt><dd><?= e($app['app_no']) ?></dd>
        <dt>नाम</dt><dd><?= e($app['full_name']) ?></dd>
        <dt>जमा किया</dt><dd><?= hindi_date($app['created_at']) ?></dd>
        <dt>स्थिति</dt><dd><span class="status-pill st-<?= e($app['status']) ?>"><?= e($l) ?></span></dd>
      </dl>
      <p><?= e(ReporterApplication::PUBLIC_TEXT[$app['status']]) ?></p>
      <?php if ($app['public_note'] && in_array($app['status'], ['document_pending', 'on_hold', 'rejected', 'verification_pending'], true)): ?><div class="notice notice-info"><b>संदेश:</b> <?= e($app['public_note']) ?></div><?php endif; ?>
      <form method="post" action="<?= e(route('application.reset')) ?>"><?= csrf_field() ?><button class="linkish" type="submit">बाहर निकलें</button></form>
    <?php endif; ?>
  </div>
</div>
