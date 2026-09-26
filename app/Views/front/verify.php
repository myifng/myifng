<?php
use App\Models\Reporter;
$this->layout('layouts/front');
?>
<div class="wrap narrow">
  <div class="box">
    <h1 class="list-title"><i class="fa-solid fa-shield-halved"></i> रिपोर्टर सत्यापन</h1>
    <p class="list-desc">किसी भी व्यक्ति के <?= e(setting('site_name')) ?> का रिपोर्टर होने की पुष्टि यहाँ करें। ID कार्ड का QR स्कैन करें या रिपोर्टर ID और मोबाइल नंबर डालें।</p>
    <?php if ($info): ?>
      <div class="verify-card <?= $info['valid'] ? 'is-valid' : 'is-invalid' ?>" role="status">
        <div class="vc-badge"><?= $info['valid'] ? '<i class="fa-solid fa-circle-check"></i> सत्यापित रिपोर्टर' : '<i class="fa-solid fa-circle-xmark"></i> अभी मान्य नहीं' ?></div>
        <div class="vc-body">
          <?php if ($info['photo']): ?><img src="<?= e(upload_url($info['photo'])) ?>" alt="<?= e($info['name']) ?> की फ़ोटो" width="110" height="130"><?php endif; ?>
          <dl>
            <dt>नाम</dt><dd><b><?= e($info['name']) ?></b></dd>
            <dt>रिपोर्टर ID</dt><dd><?= e($info['code']) ?></dd>
            <dt>पद</dt><dd><?= e($info['designation']) ?></dd>
            <dt>क्षेत्र</dt><dd><?= e(implode(', ', array_filter([$info['district'], $info['state']])) ?: '—') ?></dd>
            <dt>जॉइनिंग</dt><dd><?= hindi_date($info['joining']) ?></dd>
            <dt>वैधता</dt><dd><?= hindi_date($info['valid_until']) ?></dd>
            <dt>स्थिति</dt><dd><?= e(Reporter::STATUSES[$info['status']][0]) ?></dd>
            <dt>मोबाइल</dt><dd><?= e($info['mobile']) ?></dd>
            <?php if ($info['card']): ?><dt>कार्ड संख्या</dt><dd><?= e($info['card']) ?></dd><?php endif; ?>
          </dl>
        </div>
        <?php if (!$info['valid']): ?><p class="vc-warn">यह व्यक्ति अभी <?= e(setting('site_name')) ?> की ओर से रिपोर्टिंग के लिए अधिकृत नहीं है। शिकायत के लिए <?= e(setting('contact_email')) ?> पर लिखें।</p><?php endif; ?>
      </div>
    <?php elseif ($error): ?>
      <div class="notice notice-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(route('verify.check')) ?>" class="fgrid mt">
      <?= csrf_field() ?>
      <?= ff('text', 'code', 'रिपोर्टर ID', ['required' => true, 'value' => $code, 'attrs' => ['placeholder' => 'RPT-2026-0001', 'maxlength' => 30, 'autocapitalize' => 'characters']]) ?>
      <?= ff('tel', 'mobile', 'रिपोर्टर का मोबाइल नंबर', ['required' => true, 'attrs' => ['maxlength' => 14, 'inputmode' => 'numeric']]) ?>
      <div class="ff-wide"><button class="btn" type="submit">जाँचें</button></div>
    </form>
  </div>
</div>
