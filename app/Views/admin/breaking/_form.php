<?php
/** ब्रेकिंग आइटम का फ़ॉर्म (कंट्रोल रूम में नया + बदलें पेज) */
use App\Controllers\Admin\BreakingController;
use App\Models\BreakingNews;
$isNew = $item === null;
$newsId = (int) old('news_id', $item['news_id'] ?? 0);
$newsTitle = $newsId && $newsId === (int) ($item['news_id'] ?? 0) ? (string) ($item['news_title'] ?? '') : '';
$hours = (int) setting('breaking_expiry_hours', '6');
$endsIn = old('ends_in', $isNew ? (isset(BreakingController::ENDS_IN[(string) $hours]) ? (string) $hours : 'custom') : ($item['ends_at'] ? 'custom' : '0'));
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '';
?>
<form method="post" action="<?= e($isNew ? route('admin.breaking.store') : route('admin.breaking.update', ['id' => $item['id']])) ?>" novalidate class="brk-form"<?= $isNew ? '' : ' data-unsaved' ?>>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <?= field('text', 'title', 'शीर्षक', $item['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 255, 'data-count' => 120, 'autofocus' => $isNew ? false : true], 'placeholder' => 'जैसे: संसद में बजट पेश, वित्त मंत्री का भाषण जारी']) ?>
  <div class="row g-2">
    <div class="col-sm-4"><?= field('select', 'type', 'प्रकार', $item['type'] ?? 'breaking', ['options' => BreakingNews::TYPES]) ?></div>
    <div class="col-sm-4"><?= field('select', 'priority', 'प्राथमिकता', $item['priority'] ?? 1, ['options' => BreakingNews::PRIORITIES, 'help' => 'ऊँची पहले दिखती है']) ?></div>
    <div class="col-sm-4"><?= field('select', 'ends_in', 'कब तक चले', $endsIn, ['options' => BreakingController::ENDS_IN, 'attrs' => ['data-ends-in' => true]]) ?></div>
  </div>
  <div class="row g-2">
    <div class="col-sm-6"><?= field('datetime-local', 'starts_at', 'शुरू (ख़ाली = अभी)', $dt($item['starts_at'] ?? null)) ?></div>
    <div class="col-sm-6" data-ends-at<?= $endsIn === 'custom' ? '' : ' hidden' ?>><?= field('datetime-local', 'ends_at', 'ख़त्म होने का समय', $dt($item['ends_at'] ?? null)) ?></div>
  </div>
  <label class="form-label" for="brkNews">ख़बर से जोड़ें (वैकल्पिक)</label>
  <div class="news-picker mb-2" data-news-picker data-search="<?= e(route('admin.news.search')) ?>" data-name="news_id" data-max="1">
    <ul class="np-list"><?php if ($newsId): ?><li data-id="<?= $newsId ?>"><span>#<?= $newsId ?> <?= e($newsTitle) ?></span><input type="hidden" name="news_id" value="<?= $newsId ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></li><?php endif; ?></ul>
    <input type="search" class="form-control" id="brkNews" autocomplete="off" placeholder="ख़बर का शीर्षक या ID">
    <ul class="loc-results list-group" hidden></ul>
  </div>
  <?= field('text', 'url', 'या कोई और लिंक', $item['url'] ?? '', ['placeholder' => 'https://… या /live-tv', 'help' => 'ख़बर चुनी हो तो लिंक ख़बर का ही रहेगा', 'attrs' => ['maxlength' => 500]]) ?>
  <fieldset class="mb-3">
    <legend class="form-label">कहाँ दिखे</legend>
    <div class="brk-places">
      <?= field('switch', 'show_ticker', 'ब्रेकिंग टिकर (हर पेज)', $item['show_ticker'] ?? 1, ['wrap' => '']) ?>
      <?= field('switch', 'show_banner', 'होमपेज अलर्ट बैनर', $item['show_banner'] ?? 0, ['wrap' => '']) ?>
      <?= field('switch', 'mobile_alert', 'मोबाइल अलर्ट (नीचे पट्टी)', $item['mobile_alert'] ?? 0, ['wrap' => '']) ?>
      <?= field('switch', 'push', 'पुश नोटिफ़िकेशन', $item['push'] ?? 0, ['wrap' => '']) ?>
    </div>
    <div class="form-text">पुश: अभी कतार में दर्ज होता है; भेजना नोटिफ़िकेशन सेंटर (Phase 11) से।<?= !$isNew && $item['push_status'] !== 'none' ? ' स्थिति: ' . e(['queued' => 'कतार में', 'sent' => 'भेजा गया', 'failed' => 'असफल'][$item['push_status']] ?? $item['push_status']) : '' ?></div>
  </fieldset>
  <?php if (can('breaking.publish')): ?>
    <?= field('select', 'status', 'स्थिति', $item['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद (ड्राफ़्ट)']]) ?>
  <?php else: ?>
    <p class="small text-body-secondary"><i class="fa-solid fa-circle-info me-1"></i>आपके पास चालू करने की अनुमति नहीं है; आइटम बंद (ड्राफ़्ट) सेव होगा।</p>
  <?php endif; ?>
  <div class="d-flex gap-2">
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-bolt me-1"></i> <?= $isNew ? (can('breaking.publish') ? 'चलाएँ' : 'सेव करें') : 'सेव करें' ?></button>
    <?php if (!$isNew): ?><a class="btn btn-light" href="<?= e(route('admin.breaking.index')) ?>">वापस</a><?php endif; ?>
  </div>
</form>
