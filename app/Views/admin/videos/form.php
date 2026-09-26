<?php
use App\Models\Video;
use App\Services\EmbedService;
$this->layout('layouts/admin');
$isNew = $row === null;
$title = $isNew ? 'नया वीडियो' : $row['title'];
$yt = !$isNew && $row['source'] === 'youtube' ? EmbedService::youtubeEmbed($row['source_url']) : null;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.videos.index')) ?>">वीडियो</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
</div>
<form method="post" action="<?= e($isNew ? route('admin.videos.store') : route('admin.videos.update', ['id' => $row['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'title', 'शीर्षक', $row['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 255, 'data-count' => 100]]) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $row['slug'] ?? '', ['prefix' => '/video/', 'help' => 'ख़ाली छोड़ें तो शीर्षक से बनेगा', 'attrs' => ['maxlength' => 190]]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= field('select', 'type', 'प्रकार', $row['type'] ?? 'video', ['options' => Video::TYPES, 'help' => 'शॉर्ट = खड़ा (9:16) वीडियो']) ?></div>
          <div class="col-md-6"><?= field('select', 'source', 'स्रोत', $row['source'] ?? 'youtube', ['options' => Video::SOURCES]) ?></div>
        </div>
        <div data-when="source:youtube,embed">
          <?= field('textarea', 'source_url', 'YouTube लिंक / एम्बेड', $row['source_url'] ?? '', ['rows' => 2, 'class' => 'font-monospace', 'attrs' => ['maxlength' => 2000, 'spellcheck' => 'false'], 'help' => 'YouTube: वीडियो या shorts का लिंक। एम्बेड: iframe का https पता या पूरा <iframe> कोड (सिर्फ़ src लिया जाएगा)।']) ?>
        </div>
        <div data-when="source:upload"><?= media_field('file', 'वीडियो फ़ाइल (मीडिया लाइब्रेरी)', $row['file'] ?? '', ['kind' => 'video', 'help' => 'MP4 सबसे अच्छा; बड़ी फ़ाइल हो तो YouTube बेहतर है']) ?></div>
        <?php if ($yt): ?><div class="ratio ratio-16x9 mb-3 rounded overflow-hidden"><iframe src="<?= e($yt) ?>" title="प्रीव्यू" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div><?php endif; ?>
        <?= field('textarea', 'description', 'विवरण', $row['description'] ?? '', ['rows' => 4, 'attrs' => ['maxlength' => 5000]]) ?>
        <div class="row g-2">
          <div class="col-md-6"><?= media_field('cover', 'थंबनेल (ख़ाली = YouTube का)', $row['cover'] ?? '') ?></div>
          <div class="col-md-6">
            <?= field('text', 'duration', 'अवधि', !empty($row['duration']) ? EmbedService::duration((int) $row['duration']) : '', ['placeholder' => '4:35', 'help' => 'मिनट:सेकंड (Google वीडियो SEO के लिए)', 'attrs' => ['maxlength' => 10]]) ?>
            <?= field('text', 'credit', 'क्रेडिट / रिपोर्टर', $row['credit'] ?? '', ['attrs' => ['maxlength' => 150]]) ?>
          </div>
        </div>
      </div></section>
      <?= $this->insert('admin/multimedia/_seo', ['row' => $row]) ?>
    </div>
    <div class="col-xl-4">
      <?php ob_start(); ?>
        <?= field('select', 'playlist_id', 'प्लेलिस्ट / शो', $row['playlist_id'] ?? '', ['options' => $playlists, 'empty' => '— कोई नहीं —']) ?>
        <label class="form-label" for="vLoc">लोकेशन</label>
        <div class="loc-picker mb-3" data-location-picker data-search="<?= e(route('admin.locations.search')) ?>">
          <input type="hidden" name="location_id" value="<?= e(old('location_id', $location['id'] ?? '')) ?>">
          <input type="search" class="form-control" id="vLoc" autocomplete="off" value="<?= e($location['name'] ?? '') ?>" placeholder="ज़िला / शहर खोजें" role="combobox" aria-expanded="false" aria-controls="vLocList">
          <ul class="loc-results list-group" id="vLocList" role="listbox" hidden></ul>
        </div>
      <?php $publishExtra = ob_get_clean(); ?>
      <?= $this->insert('admin/multimedia/_publish', get_defined_vars()) ?>
    </div>
  </div>
</form>
<?= $this->insert('admin/multimedia/_danger', ['row' => $row, 'module' => $module]) ?>
