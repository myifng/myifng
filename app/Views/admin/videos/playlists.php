<?php
use App\Models\VideoPlaylist;
$this->layout('layouts/admin');
$title = 'प्लेलिस्ट और शो';
$p = $edit;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.videos.index')) ?>">वीडियो</a></li><li class="breadcrumb-item active" aria-current="page">प्लेलिस्ट</li></ol></nav>
    <h1>प्लेलिस्ट और शो</h1>
    <p>वीडियो को सीरीज़ में जोड़ें (जैसे “चुनावी चौपाल”, “सुबह की बड़ी ख़बरें”)। हर एक का अपना पेज: /videos/playlist/…</p>
  </div>
</div>
<div class="row g-3">
  <div class="<?= $p ? 'col-xl-7' : 'col-xl-4 order-xl-2' ?>">
    <?php if (can($p ? 'videos.edit' : 'videos.create')): ?>
    <section class="panel">
      <div class="panel-head"><h2><?= $p ? 'बदलें: ' . e($p['name']) : 'नई प्लेलिस्ट / शो' ?></h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e($p ? route('admin.playlists.update', ['id' => $p['id']]) : route('admin.playlists.store')) ?>" novalidate>
          <?= csrf_field() ?><?= $p ? method_field('PUT') : '' ?>
          <?= field('text', 'name', 'नाम', $p['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 150]]) ?>
          <?= field('text', 'slug', 'URL (स्लग)', $p['slug'] ?? '', ['prefix' => '/videos/playlist/', 'attrs' => ['maxlength' => 170]]) ?>
          <div class="row g-2">
            <div class="col-6"><?= field('select', 'type', 'प्रकार', $p['type'] ?? 'playlist', ['options' => VideoPlaylist::TYPES]) ?></div>
            <div class="col-6"><?= field('select', 'status', 'स्थिति', $p['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
          </div>
          <?= field('textarea', 'description', 'विवरण', $p['description'] ?? '', ['rows' => 2, 'attrs' => ['maxlength' => 1000]]) ?>
          <?= media_field('cover', 'कवर', $p['cover'] ?? '') ?>
          <?= field('number', 'sort_order', 'क्रम', $p['sort_order'] ?? '', ['attrs' => ['min' => 0, 'max' => 100000]]) ?>
          <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><?= $p ? 'सेव करें' : 'बनाएँ' ?></button><?php if ($p): ?><a class="btn btn-light" href="<?= e(route('admin.playlists.index')) ?>">वापस</a><?php endif; ?></div>
        </form>
      </div>
    </section>
    <?php endif; ?>
  </div>
  <?php if (!$p): ?>
  <div class="col-xl-8">
    <section class="panel">
      <?php if ($items): ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>नाम</th><th>प्रकार</th><th>वीडियो</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
        <tbody><?php foreach ($items as $r): ?>
          <tr class="<?= $r['status'] !== 'active' ? 'is-off' : '' ?>">
            <td><b><?= e($r['name']) ?></b><div class="small text-body-secondary font-monospace">/videos/playlist/<?= e($r['slug']) ?></div></td>
            <td class="small"><?= e(VideoPlaylist::TYPES[$r['type']]) ?></td>
            <td><?= num($r['live_videos']) ?> <span class="small text-body-secondary">/ <?= num($r['videos']) ?></span></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($r['status'] === 'active' && $r['live_videos']): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('videos.playlist', ['slug' => $r['slug']])) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
              <?php if (can('videos.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.playlists.edit', ['id' => $r['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
              <?php if (can('videos.delete')): ?><?= delete_button(route('admin.playlists.destroy', ['id' => $r['id']]), '“' . $r['name'] . '” हट जाएगी; उसके वीडियो बने रहेंगे।') ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><div class="empty-state"><i class="fa-solid fa-list"></i><p>अभी कोई प्लेलिस्ट नहीं।</p></div><?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
</div>
