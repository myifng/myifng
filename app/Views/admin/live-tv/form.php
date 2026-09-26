<?php
use App\Models\LiveTvChannel;
use App\Models\LiveTvProgram;
use App\Services\LiveTvService;
$this->layout('layouts/admin');
$isNew = $channel === null;
$title = $isNew ? 'नया चैनल' : $channel['name'];
$hm = fn($t) => substr((string) $t, 0, 5);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.live_tv.index')) ?>">लाइव टीवी</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : e($channel['name']) ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
  <?php if (!$isNew && $channel['status'] === 'active'): ?><a class="btn btn-outline-secondary" href="<?= e(route('live_tv.channel', ['slug' => $channel['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>वेबसाइट पर</a><?php endif; ?>
</div>

<form method="post" action="<?= e($isNew ? route('admin.live_tv.store') : route('admin.live_tv.update', ['id' => $channel['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8">
      <section class="panel"><div class="panel-body">
        <?= field('text', 'name', 'चैनल का नाम', $channel['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 150], 'placeholder' => 'जैसे: समाचार भारती लाइव']) ?>
        <?= field('text', 'slug', 'URL (स्लग)', $channel['slug'] ?? '', ['prefix' => '/live-tv/', 'attrs' => ['maxlength' => 170]]) ?>
        <?= field('select', 'source_type', 'स्रोत', $channel['source_type'] ?? 'youtube', ['options' => LiveTvChannel::SOURCES]) ?>
        <?= field('textarea', 'source_url', 'लिंक / एम्बेड कोड', $channel['source_url'] ?? '', ['required' => true, 'rows' => 3, 'class' => 'font-monospace', 'attrs' => ['maxlength' => 2000, 'spellcheck' => 'false'],
            'help' => 'YouTube: वीडियो/लाइव लिंक या चैनल लिंक (youtube.com/channel/UC…)। एम्बेड: iframe का https पता या पूरा <iframe> कोड (सिर्फ़ src लिया जाता है)। स्ट्रीम: https .m3u8 या .mp4; सिर्फ़ वही स्ट्रीम जिसे दिखाने का आपके पास अधिकार है।']) ?>
        <?= field('textarea', 'description', 'विवरण', $channel['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 500]]) ?>
        <?= media_field('logo', 'लोगो / थंबनेल', $channel['logo'] ?? '') ?>
      </div></section>
    </div>
    <div class="col-xl-4">
      <section class="panel sticky-xl">
        <div class="panel-head"><h2>सेटिंग</h2></div>
        <div class="panel-body">
          <?= field('select', 'status', 'स्थिति', $channel['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?>
          <?= field('switch', 'is_live', 'अभी लाइव है (हेडर में LIVE बटन)', $channel['is_live'] ?? 1) ?>
          <?= field('switch', 'is_default', 'डिफ़ॉल्ट चैनल', $channel['is_default'] ?? ($isNew ? 1 : 0)) ?>
          <?= field('number', 'sort_order', 'क्रम', $channel['sort_order'] ?? '', ['attrs' => ['min' => 0, 'max' => 100000]]) ?>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $isNew ? 'बनाएँ' : 'सेव करें' ?></button>
            <a class="btn btn-light" href="<?= e(route('admin.live_tv.index')) ?>">वापस</a>
          </div>
        </div>
      </section>
    </div>
  </div>
</form>

<?php if (!$isNew): ?>
<section class="panel mt-3">
  <div class="panel-head"><h2><i class="fa-regular fa-calendar me-2 text-body-secondary"></i>कार्यक्रम सूची</h2></div>
  <?php if ($programs): ?>
  <div class="table-responsive">
    <table class="table align-middle mb-0 data-table">
      <thead><tr><th>समय</th><th>कार्यक्रम</th><th>दिन</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($programs as $p): ?>
        <tr class="<?= $p['status'] !== 'active' ? 'is-off' : '' ?>">
          <td class="text-nowrap fw-semibold"><?= e(LiveTvService::time($p['start_time'])) ?> – <?= e(LiveTvService::time($p['end_time'])) ?></td>
          <td><b><?= e($p['title']) ?></b><?= $p['host'] ? '<div class="small text-body-secondary">' . e($p['host']) . '</div>' : '' ?></td>
          <td class="small"><?= e(LiveTvService::daysLabel($p['days'])) ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-icon btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#prog<?= (int) $p['id'] ?>" aria-expanded="false" aria-controls="prog<?= (int) $p['id'] ?>" title="बदलें" aria-label="बदलें: <?= e($p['title']) ?>"><i class="fa-solid fa-pen"></i></button>
            <?= delete_button(route('admin.live_tv.programs.destroy', ['pid' => $p['id']]), '“' . $p['title'] . '” कार्यक्रम हट जाएगा।') ?>
          </td>
        </tr>
        <tr class="collapse" id="prog<?= (int) $p['id'] ?>"><td colspan="5" class="bg-body-tertiary">
          <?= $this->insert('admin/live-tv/_program', ['p' => $p, 'action' => route('admin.live_tv.programs.update', ['pid' => $p['id']]), 'method' => 'PUT', 'uid' => 'p' . $p['id']]) ?>
        </td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><div class="empty-state py-3"><p class="mb-0">अभी कोई कार्यक्रम नहीं। नीचे जोड़ें।</p></div><?php endif; ?>
  <div class="panel-body border-top">
    <h3 class="h6 mb-3">नया कार्यक्रम</h3>
    <?= $this->insert('admin/live-tv/_program', ['p' => null, 'action' => route('admin.live_tv.programs.store', ['id' => $channel['id']]), 'method' => null, 'uid' => 'new']) ?>
  </div>
</section>
<?php if (can('live_tv.edit')): ?>
  <div class="danger-zone mt-3">
    <div><b>चैनल हटाएँ</b><p class="mb-0 small">चैनल और उसके सारे कार्यक्रम हट जाएँगे।</p></div>
    <?= delete_button(route('admin.live_tv.destroy', ['id' => $channel['id']]), '“' . $channel['name'] . '” हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?>
  </div>
<?php endif; ?>
<?php endif; ?>
