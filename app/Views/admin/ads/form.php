<?php
use App\Models\Ad;
$this->layout('layouts/admin');
if ($chart) { $this->start('vendor_scripts') ?><script src="<?= asset('vendor/chartjs/chart.umd.min.js') ?>"></script><?php $this->stop(); }
$isNew = $ad === null;
$title = $isNew ? 'नया विज्ञापन' : $ad['name'];
$types = can('ads.manage') ? Ad::TYPES : array_diff_key(Ad::TYPES, array_flip(Ad::CODE_TYPES));
$catSel = array_map('intval', (array) old('category_ids', $ad ? array_filter(explode(',', (string) $ad['category_ids'])) : []));
$slotSel = array_map('intval', (array) old('slots', $slotIds));
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.ads.index')) ?>">विज्ञापन</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <?php if (!$isNew): ?><p><?= num($ad['impressions']) ?> इम्प्रेशन · <?= num($ad['clicks']) ?> क्लिक · CTR <?= e(\App\Services\AdService::ctr((int) $ad['impressions'], (int) $ad['clicks'])) ?></p><?php endif; ?>
  </div>
  <?php if (!$isNew && can('ads.create')): ?><form method="post" action="<?= e(route('admin.ads.duplicate', ['id' => $ad['id']])) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-regular fa-copy me-1"></i>कॉपी बनाएँ</button></form><?php endif; ?>
</div>

<form method="post" action="<?= e($isNew ? route('admin.ads.store') : route('admin.ads.update', ['id' => $ad['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <div class="row g-2">
          <div class="col-md-7"><?= field('text', 'name', 'नाम (अंदर के लिए)', $ad['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: दीवाली सेल - हेडर बैनर', 'attrs' => ['maxlength' => 190]]) ?></div>
          <div class="col-md-5"><?= field('select', 'type', 'प्रकार', $ad['type'] ?? 'image', ['options' => $types]) ?></div>
        </div>
        <?= field('select', 'campaign_id', 'कैंपेन (विज्ञापनदाता)', $ad['campaign_id'] ?? $campaign, ['options' => $campaigns, 'empty' => '— हाउस विज्ञापन (अपना) —']) ?>
        <div data-when="type:image" class="row g-2">
          <div class="col-md-6"><?= media_field('image', 'बैनर इमेज', $ad['image'] ?? '', ['help' => 'स्लॉट के साइज़ के हिसाब से, जैसे 728×90']) ?></div>
          <div class="col-md-6"><?= media_field('mobile_image', 'मोबाइल के लिए अलग इमेज (वैकल्पिक)', $ad['mobile_image'] ?? '', ['help' => 'जैसे 320×100; ख़ाली = वही बैनर']) ?></div>
        </div>
        <div data-when="type:html,adsense">
          <?= field('textarea', 'code', 'विज्ञापन का कोड', $ad['code'] ?? '', ['rows' => 7, 'class' => 'font-monospace', 'attrs' => ['spellcheck' => 'false', 'maxlength' => 60000],
              'help' => 'AdSense / Google Ad Manager / किसी नेटवर्क का पूरा कोड (<script> सहित)। यह कोड वेबसाइट पर बिना बदले चलता है, इसलिए सिर्फ़ भरोसेमंद स्रोत का कोड डालें।']) ?>
        </div>
        <div data-when="type:video"><?= field('text', 'video_url', 'वीडियो', $ad['video_url'] ?? '', ['placeholder' => 'YouTube लिंक, https://….mp4 या मीडिया लाइब्रेरी का पाथ', 'attrs' => ['maxlength' => 500],
            'help' => 'मीडिया लाइब्रेरी से चुनने के लिए नीचे का बटन']) ?>
          <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-media-pick="#f_video_url" data-pick-value data-kind="video"><i class="fa-solid fa-film me-1"></i>लाइब्रेरी से वीडियो</button></div>
        <div data-when="type:image,video,link"><?= field('text', 'target_url', 'क्लिक पर कहाँ जाए', $ad['target_url'] ?? '', ['placeholder' => 'https://advertiser.com/offer', 'attrs' => ['maxlength' => 500]]) ?></div>
        <div data-when="type:image,video,link"><?= field('text', 'text', 'टेक्स्ट (टेक्स्ट विज्ञापन में लाइन, वीडियो में बटन, इमेज में alt)', $ad['text'] ?? '', ['attrs' => ['maxlength' => 300]]) ?></div>
      </div></section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-solid fa-table-cells me-2 text-body-secondary"></i>कहाँ दिखे</h2><a class="small" href="<?= e(route('admin.ads.slots')) ?>">स्लॉट प्रबंधित करें</a></div>
        <div class="panel-body">
          <div class="ad-slot-grid">
            <?php foreach ($slots as $s): ?>
              <label class="ad-slot-opt<?= $s['status'] !== 'active' ? ' is-off' : '' ?>"><input class="form-check-input" type="checkbox" name="slots[]" value="<?= (int) $s['id'] ?>"<?= in_array((int) $s['id'], $slotSel, true) ? ' checked' : '' ?>>
                <span><b><?= e($s['name']) ?></b><small><?= e($s['size'] ?: '') ?><?= $s['placement'] === 'custom' ? ' · [ad:' . e($s['slot_key']) . ']' : '' ?><?= $s['status'] !== 'active' ? ' · बंद' : '' ?></small></span></label>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="panel mt-3">
        <div class="panel-head"><h2><i class="fa-solid fa-crosshairs me-2 text-body-secondary"></i>किसे दिखे (टार्गेटिंग)</h2></div>
        <div class="panel-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="adCats">श्रेणियाँ</label>
              <select class="form-select" id="adCats" name="category_ids[]" multiple size="8"><?php foreach ($categories as $id => $n): ?><option value="<?= (int) $id ?>"<?= in_array((int) $id, $catSel, true) ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
              <div class="form-text">कुछ न चुनें = हर जगह। चुनें तो सिर्फ़ उन श्रेणियों की ख़बरों/पेजों पर (Ctrl से कई)।</div>
            </div>
            <div class="col-md-6">
              <span class="form-label d-block">लोकेशन</span>
              <div class="loc-multi" data-loc-multi data-search="<?= e(route('admin.locations.search')) ?>" data-name="location_ids[]">
                <ul class="np-list"><?php foreach ($locations as $l): ?><li data-id="<?= (int) $l['id'] ?>"><span><?= e($l['name']) ?></span><input type="hidden" name="location_ids[]" value="<?= (int) $l['id'] ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></li><?php endforeach; ?></ul>
                <input type="search" class="form-control" autocomplete="off" placeholder="राज्य / ज़िला / शहर खोजें" aria-label="लोकेशन खोजें">
                <ul class="loc-results list-group" hidden></ul>
              </div>
              <div class="form-text">जैसे “गोरखपुर” चुनें तो गोरखपुर और उसके नीचे की लोकेशन की ख़बरों पर।</div>
            </div>
          </div>
        </div>
      </section>
      <?php if ($chart): ?>
      <section class="panel mt-3"><div class="panel-head"><h2>पिछले 30 दिन</h2></div><div class="panel-body"><div style="height:200px"><canvas data-chart='<?= e(json_encode(['type' => 'bar', 'labels' => $chart['labels'],
          'datasets' => [['label' => 'इम्प्रेशन', 'data' => $chart['imp'], 'color' => 'muted'], ['label' => 'क्लिक', 'data' => $chart['clk'], 'color' => 'brand']]], JSON_UNESCAPED_UNICODE)) ?>' aria-label="30 दिन का प्रदर्शन"></canvas></div></div></section>
      <?php endif; ?>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>कब और कैसे</h2></div>
        <div class="panel-body">
          <?= field('select', 'status', 'स्थिति', $ad['status'] ?? 'active', ['options' => Ad::STATUSES]) ?>
          <?= field('datetime-local', 'start_at', 'शुरू (ख़ाली = अभी से)', $dt($ad['start_at'] ?? null)) ?>
          <?= field('datetime-local', 'end_at', 'ख़त्म (ख़ाली = बिना अंत)', $dt($ad['end_at'] ?? null)) ?>
          <?= field('select', 'devices', 'डिवाइस', $ad['devices'] ?? 'all', ['options' => Ad::DEVICES]) ?>
          <?= field('number', 'priority', 'प्राथमिकता (1-10)', $ad['priority'] ?? 5, ['attrs' => ['min' => 1, 'max' => 10], 'help' => 'एक स्लॉट में ऊँची वाला पहले; बराबर हों तो बारी-बारी']) ?>
          <div class="row g-2">
            <div class="col-6"><?= field('number', 'max_impressions', 'अधिकतम इम्प्रेशन', $ad['max_impressions'] ?? '', ['attrs' => ['min' => 1]]) ?></div>
            <div class="col-6"><?= field('number', 'max_clicks', 'अधिकतम क्लिक', $ad['max_clicks'] ?? '', ['attrs' => ['min' => 1]]) ?></div>
          </div>
          <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button>
          <?php if ($preview !== ''): ?>
            <hr><h3 class="h6">प्रीव्यू</h3>
            <div class="ad-preview"><?= in_array($ad['type'], Ad::CODE_TYPES, true) ? '<p class="small text-body-secondary mb-0">HTML/AdSense कोड का प्रीव्यू एडमिन में नहीं चलाया जाता; वेबसाइट पर देखें।</p>' : $preview ?></div>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('ads.delete') && (!in_array($ad['type'], Ad::CODE_TYPES, true) || can('ads.manage'))): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">विज्ञापन और उसके रोज़ के आँकड़े हट जाएँगे।</p></div>
          <?= delete_button(route('admin.ads.destroy', ['id' => $ad['id']]), '“' . $ad['name'] . '” हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
