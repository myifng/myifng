<?php
use App\Services\ReaderService;
$this->layout('layouts/front');
?>
<div class="wrap page-wrap acct">
  <?= $this->insert('front/account/_nav', ['tab' => $tab, 'reader' => $reader, 'unread' => $unread]) ?>
  <div class="acct-settings">
    <section class="box"><h2 class="box-title">प्रोफ़ाइल</h2>
      <form method="post" action="<?= e(route('account.profile')) ?>" class="fgrid"><?= csrf_field() ?>
        <?= ff('text', 'name', 'नाम', ['required' => true, 'value' => $reader['name'], 'attrs' => ['maxlength' => 120]]) ?>
        <?= ff('tel', 'mobile', 'मोबाइल', ['value' => $reader['mobile'] ?? '', 'attrs' => ['maxlength' => 14, 'inputmode' => 'numeric']]) ?>
        <div class="ff ff-wide loc-pick" data-loc-pick data-search="<?= e(route('api.locations')) ?>"><label for="ff_city">मेरा शहर</label>
          <input type="hidden" name="location_id" value="<?= e((string) ($city['id'] ?? '')) ?>">
          <input type="search" id="ff_city" value="<?= e($city['name'] ?? '') ?>" placeholder="शहर या ज़िला खोजें" autocomplete="off"><div class="follow-chips" data-loc-results></div>
          <small class="ff-help">"आपके लिए" पेज पर इस शहर की लोकप्रिय ख़बरें</small></div>
        <div class="ff-wide"><button class="btn" type="submit">सेव करें</button></div>
      </form>
    </section>
    <section class="box"><h2 class="box-title">सूचनाएँ और निजता</h2>
      <form method="post" action="<?= e(route('account.prefs')) ?>"><?= csrf_field() ?>
        <?php foreach (ReaderService::PREFS as $k => [$label]): ?><label class="check"><input type="checkbox" name="pref_<?= e($k) ?>" value="1"<?= $prefs[$k] ? ' checked' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
        <label class="check"><input type="checkbox" name="newsletter" value="1"<?= ($sub['status'] ?? '') === 'subscribed' ? ' checked' : '' ?>> न्यूज़लेटर (रोज़ की बड़ी ख़बरें ईमेल पर)</label>
        <label class="check"><input type="checkbox" name="history_enabled" value="1"<?= (int) $reader['history_enabled'] ? ' checked' : '' ?>> पढ़ने का इतिहास रखें (बंद करने पर पुराना इतिहास मिट जाएगा)</label>
        <div class="push-opt" data-push-box hidden><p class="muted">इस ब्राउज़र पर ब्रेकिंग न्यूज़ की पुश सूचना:</p><button type="button" class="btn btn-light" data-push-toggle>पुश चालू करें</button></div>
        <button class="btn mt" type="submit">सेव करें</button>
      </form>
    </section>
    <section class="box"><h2 class="box-title">पासवर्ड बदलें</h2>
      <form method="post" action="<?= e(route('account.password')) ?>" class="fgrid"><?= csrf_field() ?>
        <?= ff('password', 'current_password', 'मौजूदा पासवर्ड', ['required' => true, 'wide' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
        <?= ff('password', 'password', 'नया पासवर्ड', ['required' => true, 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 8]]) ?>
        <?= ff('password', 'password_confirmation', 'नया पासवर्ड दोबारा', ['required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
        <div class="ff-wide"><button class="btn" type="submit">पासवर्ड बदलें</button></div>
      </form>
    </section>
    <section class="box danger-box"><h2 class="box-title">खाता हटाएँ</h2>
      <p>आपका खाता, सेव की गई ख़बरें, फ़ॉलो और इतिहास हमेशा के लिए मिट जाएँगे। आपकी टिप्पणियाँ "पूर्व पाठक" नाम से रहेंगी।</p>
      <form method="post" action="<?= e(route('account.delete')) ?>" class="inline-form" data-confirm="खाता हमेशा के लिए हट जाएगा। यह वापस नहीं होगा।"><?= csrf_field() ?>
        <input type="password" name="password" required placeholder="पासवर्ड लिखें" aria-label="पासवर्ड" autocomplete="current-password"><button class="btn btn-danger" type="submit">खाता हटाएँ</button></form>
      <?php if (error('delete_password')): ?><small class="ff-err"><?= e(error('delete_password')) ?></small><?php endif; ?>
    </section>
  </div>
</div>
