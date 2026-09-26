<?php
use App\Models\AudioItem;
use App\Services\EmbedService;
$this->layout('layouts/admin');
$isNew = $row === null;
$title = $isNew ? 'नया ऑडियो' : $row['title'];
$newsId = (int) old('news_id', $row['news_id'] ?? 0);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.audio.index')) ?>">ऑडियो</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.audio.store') : route('admin.audio.update', ['id' => $row['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $row['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 255, 'data-count' => 100]]) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $row['slug'] ?? '', ['prefix' => '/audio/', 'attrs' => ['maxlength' => 190]]) ?>
        <div class="row g-2">
          <div class="col-md-4"><?= field('select', 'type', 'प्रकार', $row['type'] ?? 'news', ['options' => AudioItem::TYPES]) ?></div>
          <div class="col-md-5" data-when="type:episode"><?= field('select', 'series_id', 'पॉडकास्ट सीरीज़', $row['series_id'] ?? '', ['options' => $series, 'empty' => '— चुनें —']) ?></div>
          <div class="col-md-3" data-when="type:episode"><?= field('number', 'episode_no', 'एपिसोड नं.', $row['episode_no'] ?? '', ['attrs' => ['min' => 1]]) ?></div>
        </div>
        <?php if (!$series): ?><p class="small text-body-secondary" data-when="type:episode">पहले <a href="<?= e(route('admin.podcasts.index')) ?>">पॉडकास्ट सीरीज़</a> बनाएँ।</p><?php endif; ?>
        <?= media_field('file', 'ऑडियो फ़ाइल (MP3 / M4A)', $row['file'] ?? '', ['kind' => 'audio']) ?>
        <?php if (!$isNew && $row['file']): ?><audio class="w-100 mb-3" src="<?= e(upload_url($row['file'])) ?>" controls preload="none"></audio><?php endif; ?>
        <?= field('url', 'external_url', 'या बाहरी लिंक', $row['external_url'] ?? '', ['placeholder' => 'https://…/episode.mp3', 'help' => 'फ़ाइल चुनी हो तो फ़ाइल ही चलेगी', 'attrs' => ['maxlength' => 500]]) ?>
        <?= field('textarea', 'description', 'विवरण / शो नोट्स', $row['description'] ?? '', ['rows' => 4, 'attrs' => ['maxlength' => 5000]]) ?>
        <?= field('textarea', 'transcript', 'ट्रांसक्रिप्ट (पूरा पाठ)', $row['transcript'] ?? '', ['rows' => 6, 'attrs' => ['maxlength' => 100000],
            'help' => 'पेज पर "पूरा पाठ पढ़ें" में दिखता है (SEO और सुलभता)। ' . ($tts ? 'इसी से Text-to-Speech ऑडियो बन सकता है।' : 'आगे Text-to-Speech जुड़ने पर इसी पाठ से ऑडियो बनेगा।')]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= media_field('cover', 'थंबनेल (ख़ाली = सीरीज़ का कवर)', $row['cover'] ?? '') ?></div>
          <div class="col-md-6"><?= field('text', 'duration', 'अवधि', !empty($row['duration']) ? EmbedService::duration((int) $row['duration']) : '', ['placeholder' => '12:30', 'attrs' => ['maxlength' => 10]]) ?></div>
        </div>
      </div></section>
      <?= $this->insert('admin/multimedia/_seo', ['row' => $row]) ?>
    </div>
    <div class="col-xl-4">
      <?php ob_start(); ?>
        <label class="form-label" for="aNews">किस ख़बर का ऑडियो (वैकल्पिक)</label>
        <div class="news-picker mb-3" data-news-picker data-search="<?= e(route('admin.news.search')) ?>" data-name="news_id" data-max="1">
          <ul class="np-list"><?php if ($newsId): ?><li data-id="<?= $newsId ?>"><span>#<?= $newsId ?> <?= e($linkedNews['title'] ?? '') ?></span><input type="hidden" name="news_id" value="<?= $newsId ?>"><button type="button" class="ml-remove" aria-label="हटाएँ">×</button></li><?php endif; ?></ul>
          <input type="search" class="form-control" id="aNews" autocomplete="off" placeholder="ख़बर का शीर्षक या ID">
          <ul class="loc-results list-group" hidden></ul>
        </div>
      <?php $publishExtra = ob_get_clean(); ?>
      <?= $this->insert('admin/multimedia/_publish', get_defined_vars()) ?>
    </div>
  </div>
</form>
<?= $this->insert('admin/multimedia/_danger', ['row' => $row, 'module' => $module]) ?>
