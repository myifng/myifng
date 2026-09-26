<?php
$this->layout('layouts/admin');
$title = 'पॉडकास्ट सीरीज़';
$p = $edit;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.audio.index')) ?>">ऑडियो</a></li><li class="breadcrumb-item active" aria-current="page">पॉडकास्ट सीरीज़</li></ol></nav>
    <h1>पॉडकास्ट सीरीज़</h1>
    <p>हर सीरीज़ का पेज (/podcast/…) और RSS फ़ीड, जिसे Apple Podcasts, Spotify, JioSaavn आदि में जमा किया जा सकता है।</p>
  </div>
</div>
<div class="row g-3">
  <div class="<?= $p ? 'col-xl-7' : 'col-xl-4 order-xl-2' ?>">
    <?php if (can($p ? 'audio.edit' : 'audio.create')): ?>
    <section class="panel">
      <div class="panel-head"><h2><?= $p ? 'बदलें: ' . e($p['title']) : 'नई सीरीज़' ?></h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e($p ? route('admin.podcasts.update', ['id' => $p['id']]) : route('admin.podcasts.store')) ?>" novalidate>
          <?= csrf_field() ?><?= $p ? method_field('PUT') : '' ?>
          <?= field('text', 'title', 'नाम', $p['title'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 190]]) ?>
          <?= field('text', 'slug', 'URL (स्लग)', $p['slug'] ?? '', ['prefix' => '/podcast/', 'attrs' => ['maxlength' => 190]]) ?>
          <?= field('textarea', 'description', 'विवरण', $p['description'] ?? '', ['rows' => 3, 'attrs' => ['maxlength' => 4000]]) ?>
          <?= field('text', 'author', 'होस्ट / लेखक', $p['author'] ?? '', ['attrs' => ['maxlength' => 150]]) ?>
          <?= field('select', 'category_id', 'श्रेणी', $p['category_id'] ?? '', ['options' => $categories, 'empty' => '— कोई नहीं —']) ?>
          <?= media_field('cover', 'कवर (वर्गाकार, कम से कम 1400×1400 अच्छा)', $p['cover'] ?? '') ?>
          <div class="row g-2">
            <div class="col-6"><?= field('select', 'status', 'स्थिति', $p['status'] ?? 'active', ['options' => ['active' => 'चालू', 'inactive' => 'बंद']]) ?></div>
            <div class="col-6"><?= field('number', 'sort_order', 'क्रम', $p['sort_order'] ?? '', ['attrs' => ['min' => 0, 'max' => 100000]]) ?></div>
          </div>
          <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><?= $p ? 'सेव करें' : 'बनाएँ' ?></button><?php if ($p): ?><a class="btn btn-light" href="<?= e(route('admin.podcasts.index')) ?>">वापस</a><?php endif; ?></div>
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
        <thead><tr><th>सीरीज़</th><th>एपिसोड</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
        <tbody><?php foreach ($items as $r): ?>
          <tr class="<?= $r['status'] !== 'active' ? 'is-off' : '' ?>">
            <td><div class="d-flex align-items-center gap-2"><?php if ($r['cover']): ?><img class="thumb-sm" src="<?= e(media_url($r['cover'], 'thumb')) ?>" alt=""><?php endif; ?>
              <div><b><?= e($r['title']) ?></b><?php if ($r['status'] === 'active'): ?><div class="small"><a href="<?= e(route('podcast.feed', ['slug' => $r['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-rss me-1"></i>RSS फ़ीड</a></div><?php endif; ?></div></div></td>
            <td><?= num($r['live_episodes']) ?> <span class="small text-body-secondary">/ <?= num($r['episodes']) ?></span></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-end text-nowrap">
              <?php if ($r['status'] === 'active'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('podcast', ['slug' => $r['slug']])) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
              <?php if (can('audio.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.podcasts.edit', ['id' => $r['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
              <?php if (can('audio.delete')): ?><?= delete_button(route('admin.podcasts.destroy', ['id' => $r['id']]), '“' . $r['title'] . '” हट जाएगी; एपिसोड बने रहेंगे।') ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><div class="empty-state"><i class="fa-solid fa-podcast"></i><p>अभी कोई सीरीज़ नहीं।</p></div><?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
</div>
