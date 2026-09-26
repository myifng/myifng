<?php
use App\Models\Reporter;
use App\Models\ReporterApplication;
$this->layout('layouts/front');
$stateOpts = array_column($states, 'name', 'id');
$distOpts = [];
foreach ($districts as $d) {
    $distOpts[$d['id']] = [$d['name'], $d['state_id']];
}
?>
<div class="wrap narrow">
  <div class="box join">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">रिपोर्टर बनें</span></nav>
    <h1 class="list-title">रिपोर्टर बनें</h1>
    <?php if (setting('join_intro')): ?><p class="list-desc"><?= e(setting('join_intro')) ?></p><?php endif; ?>
    <p class="small-note">पहले आवेदन कर चुके हैं? <a href="<?= e(route('application.status')) ?>">आवेदन की स्थिति देखें</a></p>

    <ol class="steps" data-steps-nav hidden>
      <?php foreach (['व्यक्तिगत', 'पता', 'पेशेवर', 'दस्तावेज़', 'घोषणा'] as $i => $s): ?><li data-step-i="<?= $i ?>"><span><?= $i + 1 ?></span><?= e($s) ?></li><?php endforeach; ?>
    </ol>

    <form method="post" action="<?= e(route('join.submit')) ?>" enctype="multipart/form-data" class="join-form" data-steps novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="started_at" value="<?= (int) $startedAt ?>">
      <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

      <fieldset class="step" data-step>
        <legend>1. व्यक्तिगत जानकारी</legend>
        <div class="fgrid">
          <?= ff('text', 'full_name', 'पूरा नाम', ['required' => true, 'attrs' => ['maxlength' => 120, 'autocomplete' => 'name']]) ?>
          <?= ff('text', 'guardian_name', 'पिता/पति का नाम', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?>
          <?= ff('date', 'dob', 'जन्मतिथि', ['required' => true, 'attrs' => ['max' => date('Y-m-d', strtotime('-18 years'))]]) ?>
          <?= ff('select', 'gender', 'लिंग', ['required' => true, 'options' => ReporterApplication::GENDERS]) ?>
          <?= ff('tel', 'mobile', 'मोबाइल', ['required' => true, 'attrs' => ['maxlength' => 14, 'inputmode' => 'numeric', 'autocomplete' => 'tel', 'pattern' => '(\+91)?[6-9][0-9]{9}'], 'help' => '10 अंक; इसी से आवेदन की स्थिति देखेंगे']) ?>
          <?= ff('tel', 'whatsapp', 'WhatsApp नंबर', ['attrs' => ['maxlength' => 14, 'inputmode' => 'numeric']]) ?>
          <?= ff('email', 'email', 'ईमेल', ['required' => true, 'wide' => true, 'attrs' => ['maxlength' => 190, 'autocomplete' => 'email'], 'help' => 'सत्यापन कोड और लॉगिन जानकारी इसी पर आएगी']) ?>
        </div>
      </fieldset>

      <fieldset class="step" data-step>
        <legend>2. पता</legend>
        <div class="fgrid">
          <?= ff('textarea', 'address', 'पूरा पता', ['required' => true, 'wide' => true, 'rows' => 2, 'attrs' => ['maxlength' => 400]]) ?>
          <?= ff('select', 'state_id', 'राज्य', ['required' => true, 'options' => $stateOpts, 'attrs' => ['data-state-select' => true]]) ?>
          <?= ff('select', 'district_id', 'ज़िला', ['required' => true, 'options' => $distOpts, 'attrs' => ['data-district-select' => true]]) ?>
          <?= ff('text', 'city', 'शहर / क़स्बा / तहसील', ['attrs' => ['maxlength' => 120]]) ?>
          <?= ff('text', 'pincode', 'पिनकोड', ['required' => true, 'attrs' => ['maxlength' => 6, 'inputmode' => 'numeric', 'pattern' => '[1-9][0-9]{5}', 'autocomplete' => 'postal-code']]) ?>
        </div>
      </fieldset>

      <fieldset class="step" data-step>
        <legend>3. पेशेवर जानकारी</legend>
        <div class="fgrid">
          <?= ff('select', 'reporter_type', 'किस रूप में जुड़ना चाहते हैं', ['required' => true, 'options' => Reporter::TYPES]) ?>
          <?= ff('number', 'experience_years', 'पत्रकारिता का अनुभव (साल)', ['required' => true, 'value' => '0', 'attrs' => ['min' => 0, 'max' => 60]]) ?>
          <?= ff('text', 'previous_org', 'पिछला संस्थान', ['attrs' => ['maxlength' => 190]]) ?>
          <?= ff('text', 'education', 'शिक्षा', ['required' => true, 'attrs' => ['maxlength' => 150], 'help' => 'जैसे: स्नातक, पत्रकारिता डिप्लोमा']) ?>
          <?= ff('text', 'languages', 'भाषाएँ', ['attrs' => ['maxlength' => 150], 'help' => 'जैसे: हिंदी, भोजपुरी, अंग्रेज़ी']) ?>
          <?= ff('textarea', 'about', 'अपने बारे में', ['wide' => true, 'rows' => 3, 'attrs' => ['maxlength' => 2000], 'help' => 'आप किन मुद्दों पर लिखना चाहते हैं, आपके क्षेत्र की ख़ास बातें']) ?>
        </div>
      </fieldset>

      <fieldset class="step" data-step>
        <legend>4. दस्तावेज़</legend>
        <?php if (error('_files')): ?><p class="notice notice-warning"><?= e(error('_files')) ?></p><?php endif; ?>
        <p class="small-note">फ़ोटो और हस्ताक्षर: JPG/PNG, 2 MB तक। बाकी: JPG/PNG/PDF, 5 MB तक। दस्तावेज़ सुरक्षित रखे जाते हैं और सिर्फ़ जाँच करने वाली टीम देखती है।</p>
        <div class="fgrid">
          <?php foreach (ReporterApplication::DOCUMENTS as $k => [$l, $req]): ?>
            <?= ff('file', 'doc_' . $k, $l, ['required' => $req, 'attrs' => ['accept' => in_array($k, ['photo', 'signature'], true) ? 'image/jpeg,image/png' : 'image/jpeg,image/png,application/pdf']]) ?>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="step" data-step>
        <legend>5. घोषणा</legend>
        <div class="declaration"><?= nl2br(e(setting('join_declaration'))) ?></div>
        <label class="check<?= error('declaration') ? ' has-err' : '' ?>"><input type="checkbox" name="declaration" value="1" required<?= checked(old('declaration')) ?>> मैं ऊपर लिखी घोषणा और <a href="<?= e(url('page/reporter-policy')) ?>" target="_blank" rel="noopener">रिपोर्टर नीति</a> से सहमत हूँ।</label>
        <?php if (error('declaration')): ?><small class="ff-err"><?= e(error('declaration')) ?></small><?php endif; ?>
      </fieldset>

      <div class="step-actions">
        <button type="button" class="btn-outline" data-step-prev hidden>पीछे</button>
        <button type="button" class="btn" data-step-next hidden>आगे</button>
        <button type="submit" class="btn" data-step-submit>आवेदन जमा करें</button>
      </div>
    </form>
  </div>
</div>
