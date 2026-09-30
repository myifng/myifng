<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box">
    <h1 class="list-title"><i class="fa-regular fa-user"></i> नया खाता</h1>
    <p class="list-desc">मुफ़्त। ख़बरें सेव करें, श्रेणी/रिपोर्टर/शहर फ़ॉलो करें और "आपके लिए" पेज पाएँ।</p>
    <form method="post" action="<?= e(route('account.register.post')) ?>" class="fgrid">
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>वेबसाइट <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <?= ff('text', 'name', 'नाम', ['required' => true, 'attrs' => ['autocomplete' => 'name', 'maxlength' => 120]]) ?>
      <?= ff('email', 'email', 'ईमेल', ['required' => true, 'attrs' => ['autocomplete' => 'email', 'maxlength' => 190]]) ?>
      <?= ff('tel', 'mobile', 'मोबाइल (वैकल्पिक)', ['attrs' => ['autocomplete' => 'tel', 'maxlength' => 14, 'inputmode' => 'numeric']]) ?>
      <?= ff('password', 'password', 'पासवर्ड', ['required' => true, 'help' => 'कम से कम 8 अक्षर, अक्षर और अंक दोनों', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 8]]) ?>
      <?= ff('password', 'password_confirmation', 'पासवर्ड दोबारा', ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
      <div class="ff-wide checks">
        <?php if (\App\Services\NewsletterService::enabled()): ?><label class="check"><input type="checkbox" name="newsletter" value="1"<?= old('newsletter') ? ' checked' : '' ?>> रोज़ की बड़ी ख़बरें ईमेल पर भेजें (न्यूज़लेटर)</label><?php endif; ?>
        <label class="check<?= error('agree') ? ' has-err' : '' ?>"><input type="checkbox" name="agree" value="1" required<?= old('agree') ? ' checked' : '' ?>> मैं <a href="<?= e(url('page/terms-and-conditions')) ?>" target="_blank">नियम</a> और <a href="<?= e(url('page/privacy-policy')) ?>" target="_blank">निजता नीति</a> से सहमत हूँ</label>
        <?php if (error('agree')): ?><small class="ff-err"><?= e(error('agree')) ?></small><?php endif; ?>
      </div>
      <div class="ff-wide"><button class="btn" type="submit">खाता बनाएँ</button></div>
    </form>
    <p class="auth-alt">पहले से खाता है? <a href="<?= e(route('account.login')) ?>"><b>लॉगिन करें</b></a></p>
  </div>
</div>
