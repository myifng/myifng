<?php
use App\Services\FactCheckService as FC;
$this->layout('layouts/admin');
$isNew = $fc === null;
$title = $isNew ? 'नई फ़ैक्ट चेक' : $fc['title'];
$verdictNow = old('verdict', $fc['verdict'] ?? 'unverified');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.fact_checks.index')) ?>">फ़ैक्ट चेक</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नई' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
    <?php if (!$isNew): ?><p><?= FC::badge($fc['verdict']) ?> <span class="badge text-bg-<?= e(FC::STATUSES[$fc['status']][1]) ?>"><?= e(FC::STATUSES[$fc['status']][0]) ?></span><?= $fc['published_at'] ? ' · प्रकाशित ' . hindi_date($fc['published_at'], true) : '' ?></p><?php endif; ?>
  </div>
  <?php if (!$isNew && $fc['status'] === 'published'): ?><a class="btn btn-outline-secondary" href="<?= e(FC::url($fc)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर देखें</a><?php endif; ?>
</div>
<form method="post" action="<?= e($isNew ? route('admin.fact_checks.store') : route('admin.fact_checks.update', ['id' => $fc['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-head"><h2><i class="fa-solid fa-bullhorn me-2 text-body-secondary"></i>दावा</h2></div><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $fc['title'] ?? ($prefill ? 'फ़ैक्ट चेक: ' . $prefill['title'] : ''), ['required' => true, 'attrs' => ['maxlength' => 255], 'placeholder' => 'जैसे: फ़ैक्ट चेक: क्या सच में 1 जनवरी से बदल रहे हैं बैंक के नियम?']) ?>
        <?= field('textarea', 'claim', 'दावा (जैसा वायरल है)', $fc['claim'] ?? '', ['required' => true, 'rows' => 3, 'help' => 'दावे के शब्द, बिना राय के।']) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= field('text', 'claim_by', 'दावा किसने किया', $fc['claim_by'] ?? '', ['placeholder' => 'जैसे: सोशल मीडिया यूज़र / एक नेता / एक चैनल']) ?></div>
          <div class="col-6 col-md-3"><?= field('select', 'claim_medium', 'माध्यम', $fc['claim_medium'] ?? 'social', ['options' => FC::MEDIUMS]) ?></div>
          <div class="col-6 col-md-3"><?= field('date', 'claim_date', 'दावे की तारीख़', $fc['claim_date'] ?? '') ?></div>
        </div>
        <?= field('url', 'claim_url', 'दावे का लिंक (पोस्ट/वीडियो, वैकल्पिक)', $fc['claim_url'] ?? '', ['placeholder' => 'https://…', 'help' => 'वेबसाइट पर "nofollow" के साथ दिखेगा। भ्रामक सामग्री को बढ़ावा न देना हो तो ख़ाली छोड़ें।']) ?>
      </div></section>

      <section class="panel mt-3"><div class="panel-head"><h2><i class="fa-solid fa-magnifying-glass me-2 text-body-secondary"></i>जाँच</h2></div><div class="panel-body">
        <span class="form-label d-block">सबूत (क्या-क्या जाँचा: रिवर्स इमेज, आधिकारिक बयान, दस्तावेज़…)</span>
        <div class="rte mb-3" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>"><textarea name="evidence" class="rte-source" aria-label="सबूत"><?= e(old('evidence', $fc['evidence'] ?? '')) ?></textarea></div>
        <span class="form-label d-block">व्याख्या: दावा सच/झूठ क्यों है <span class="text-danger">*</span></span>
        <div class="rte mb-1" data-editor data-upload="<?= e(route('admin.editor.upload')) ?>"><textarea name="explanation" class="rte-source" aria-label="व्याख्या"><?= e(old('explanation', $fc['explanation'] ?? '')) ?></textarea></div>
        <?php if (error('explanation')): ?><div class="invalid-feedback d-block mb-3"><?= e(error('explanation')) ?></div><?php else: ?><div class="form-text mb-3">प्रकाशन के लिए ज़रूरी।</div><?php endif; ?>
        <?= field('textarea', 'sources', 'स्रोत (एक लाइन में एक: नाम | https://लिंक)', FC::sourcesText($fc['sources'] ?? null), ['rows' => 4, 'placeholder' => "PIB Fact Check | https://…\nरिज़र्व बैंक की प्रेस रिलीज़ | https://…\nज़िलाधिकारी से फ़ोन पर बातचीत", 'help' => 'प्रकाशन के लिए कम से कम एक। बिना लिंक वाला स्रोत भी चलेगा (जैसे बातचीत)।']) ?>
      </div></section>
    </div>

    <div class="col-xl-4">
      <section class="panel sticky-xl"><div class="panel-body">
        <span class="form-label d-block">फ़ैसला <span class="text-danger">*</span></span>
        <div class="fc-verdicts mb-3" role="radiogroup" aria-label="फ़ैसला">
          <?php foreach (FC::VERDICTS as $k => [$l, $cls, $ic]): ?>
            <label class="fc-pick <?= e($cls) ?>"><input type="radio" name="verdict" value="<?= e($k) ?>"<?= checked($verdictNow === $k) ?>><span><i class="fa-solid <?= e($ic) ?>"></i> <?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
        <?= field('text', 'summary', 'एक लाइन में नतीजा', $fc['summary'] ?? '', ['attrs' => ['maxlength' => 500], 'placeholder' => 'जैसे: वायरल वीडियो 2019 का है, हाल का नहीं।']) ?>
        <?php if (!$isNew && $fc['published_at']): ?>
          <?= field('textarea', 'update_note', 'अपडेट नोट (फ़ैसला बदलें तो ज़रूरी, पाठकों को दिखेगा)', $fc['update_note'] ?? '', ['rows' => 2]) ?>
        <?php endif; ?>
        <?= field('select', 'status', 'स्थिति', $fc['status'] ?? 'draft', ['options' => $statuses]) ?>
        <?php if (!can('fact_checks.publish')): ?><p class="small text-body-secondary">प्रकाशन संपादक करेंगे; "समीक्षा के लिए भेजें" चुनें।</p><?php endif; ?>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
      </div></section>

      <section class="panel mt-3"><div class="panel-body">
        <?= media_field('image', 'इमेज (वायरल पोस्ट का स्क्रीनशॉट आदि)', $fc['image'] ?? '', ['help' => 'भ्रामक इमेज पर "झूठ" जैसा निशान लगाकर अपलोड करें।']) ?>
        <?= field('select', 'category_id', 'श्रेणी', $fc['category_id'] ?? ($prefill['category_id'] ?? ''), ['options' => $categories, 'empty' => '—']) ?>
        <?= $this->insert('partials/admin/location-picker', ['location' => $location, 'label' => 'लोकेशन', 'name' => 'location_id']) ?>
        <?= field('number', 'news_id', 'जुड़ी ख़बर (ID)', $fc['news_id'] ?? ($prefill['id'] ?? ''), ['help' => $linked ? 'अभी: ' . $linked['title'] : 'ख़बर के पेज पर यह फ़ैक्ट चेक दिखेगी।']) ?>
        <?php if (!$isNew): ?><?= field('text', 'slug', 'पता (स्लग)', $fc['slug'], ['attrs' => ['maxlength' => 190]]) ?><?php endif; ?>
        <details><summary class="small fw-semibold mb-2">SEO</summary>
          <?= field('text', 'meta_title', 'SEO शीर्षक', $fc['meta_title'] ?? '', ['attrs' => ['maxlength' => 190]]) ?>
          <?= field('textarea', 'meta_description', 'SEO विवरण', $fc['meta_description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 320]]) ?>
        </details>
      </div></section>
    </div>
  </div>
</form>
<?php if (!$isNew && can('fact_checks.delete') && ($fc['status'] !== 'published' || can('fact_checks.publish'))): ?>
<div class="danger-zone mt-3"><div><b>हटाएँ</b><p class="mb-0 small">प्रकाशित फ़ैक्ट चेक को हटाने के बजाय "आर्काइव" करना बेहतर है।</p></div><?= delete_button(route('admin.fact_checks.destroy', ['id' => $fc['id']]), 'यह फ़ैक्ट चेक हमेशा के लिए हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
