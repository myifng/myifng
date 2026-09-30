<?php /** लोकेशन खोजकर चुनें: $location (id, name, chain) या null, $label, $name */
$uid = 'lp' . substr(md5($name . ($label ?? '')), 0, 6); ?>
<div class="mb-3">
  <label class="form-label" for="<?= $uid ?>s"><?= e($label ?? 'लोकेशन') ?></label>
  <div class="loc-picker" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
    <input type="hidden" name="<?= e($name) ?>" value="<?= e(old($name, $location['id'] ?? '')) ?>">
    <input type="search" class="form-control<?= error($name) ? ' is-invalid' : '' ?>" id="<?= $uid ?>s" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="राज्य, ज़िला, शहर… खोजें" role="combobox" aria-expanded="false" aria-controls="<?= $uid ?>l">
    <ul class="loc-results list-group" id="<?= $uid ?>l" role="listbox" hidden></ul>
  </div>
  <div class="form-text"><?= !empty($location['chain']) ? e($location['chain']) : e($help ?? 'ख़ाली छोड़ सकते हैं।') ?></div>
</div>
