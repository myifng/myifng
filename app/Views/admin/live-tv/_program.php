<?php
/** कार्यक्रम का फ़ॉर्म (नया + हर पंक्ति में बदलें) */
use App\Models\LiveTvProgram;
$mine = old('_form') === $uid; // पिछली बार यही फ़ॉर्म जमा हुआ था?
$fresh = !$mine;
$days = $mine && is_array(old('days')) ? array_map('strval', old('days')) : ($p ? explode(',', (string) $p['days']) : ['0', '1', '2', '3', '4', '5', '6']);
?>
<form method="post" action="<?= e($action) ?>" novalidate class="row g-2 align-items-end">
  <?= csrf_field() ?><?= $method ? method_field($method) : '' ?><input type="hidden" name="_form" value="<?= e($uid) ?>">
  <div class="col-md-4"><?= field('text', 'title', 'कार्यक्रम', $p['title'] ?? '', ['id' => 'pt_' . $uid, 'fresh' => $fresh, 'required' => true, 'wrap' => '', 'attrs' => ['maxlength' => 190]]) ?></div>
  <div class="col-md-3"><?= field('text', 'host', 'एंकर', $p['host'] ?? '', ['id' => 'ph_' . $uid, 'fresh' => $fresh, 'wrap' => '', 'attrs' => ['maxlength' => 150]]) ?></div>
  <div class="col-6 col-md-2"><?= field('time', 'start_time', 'शुरू', isset($p['start_time']) ? substr($p['start_time'], 0, 5) : '', ['id' => 'ps_' . $uid, 'fresh' => $fresh, 'required' => true, 'wrap' => '']) ?></div>
  <div class="col-6 col-md-2"><?= field('time', 'end_time', 'ख़त्म', isset($p['end_time']) ? substr($p['end_time'], 0, 5) : '', ['id' => 'pe_' . $uid, 'fresh' => $fresh, 'required' => true, 'wrap' => '']) ?></div>
  <div class="col-md-1"><?= field('select', 'status', 'स्थिति', $p['status'] ?? 'active', ['id' => 'pst_' . $uid, 'fresh' => $fresh, 'wrap' => '', 'options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
  <div class="col-md-9">
    <span class="form-label d-block">दिन</span>
    <div class="d-flex flex-wrap gap-3">
      <?php foreach (LiveTvProgram::DAYS as $d => $l): ?>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="days[]" value="<?= $d ?>" id="pd_<?= e($uid) ?>_<?= $d ?>"<?= in_array((string) $d, $days, true) ? ' checked' : '' ?>><label class="form-check-label" for="pd_<?= e($uid) ?>_<?= $d ?>"><?= e($l) ?></label></div>
      <?php endforeach; ?>
    </div>
    <?php if ($mine && error('days')): ?><div class="invalid-feedback d-block"><?= e(error('days')) ?></div><?php endif; ?>
  </div>
  <div class="col-md-3 text-md-end"><button class="btn btn-sm btn-brand" type="submit"><?= $p ? 'सेव करें' : 'जोड़ें' ?></button></div>
</form>
