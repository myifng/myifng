<?php
/** वीडियो / गैलरी / वेब स्टोरी / ऑडियो की साझा सूची */
use App\Services\MultimediaService;
$this->layout('layouts/admin');
$title = $labels['many'];
$stateBadge = ['draft' => ['secondary', 'ड्राफ़्ट'], 'scheduled' => ['info', 'शेड्यूल'], 'published' => ['success', 'प्रकाशित']];
$publicRoute = MultimediaService::KINDS[$kind]['route'];
$q = $q ?? '';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($labels['many']) ?></li></ol></nav>
    <h1><?= e($labels['many']) ?></h1>
    <p><?= e($labels['desc']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?= $extraActions ?? '' ?>
    <?php if ($kind === 'video' && can('videos.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.playlists.index')) ?>"><i class="fa-solid fa-list me-1"></i> प्लेलिस्ट / शो</a><?php endif; ?>
    <?php if ($kind === 'audio' && can('audio.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.podcasts.index')) ?>"><i class="fa-solid fa-podcast me-1"></i> पॉडकास्ट सीरीज़</a><?php endif; ?>
    <?php if (can($module . '.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.' . $module . '.create')) ?>"><i class="fa-solid fa-plus me-1"></i> <?= e($labels['new']) ?></a><?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach (['' => 'सभी', 'published' => 'प्रकाशित', 'scheduled' => 'शेड्यूल', 'draft' => 'ड्राफ़्ट'] as $k => $l): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'type' => $type, 'q' => $q]))) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($counts[$k]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <?php if ($types): ?><select class="form-select w-auto" name="type" aria-label="प्रकार"><option value="">सभी प्रकार</option><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $type) ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="शीर्षक से खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>शीर्षक</th><?php if ($types): ?><th>प्रकार</th><?php endif; ?><th>श्रेणी</th><th>स्थिति</th><th class="text-end">व्यूज़</th><th>बदला</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($items->items as $r): $st = MultimediaService::state($r); [$bc, $bl] = $stateBadge[$st]; $thumb = mm_thumb_src($r + ['kind' => $kind], 'thumb'); ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($thumb): ?><img class="thumb-sm<?= $kind === 'story' ? ' tall' : '' ?>" src="<?= e($thumb) ?>" alt=""><?php else: ?><span class="cat-dot" style="--c:#9aa0a6"><i class="fa-solid <?= e(MultimediaService::KINDS[$kind]['icon']) ?>"></i></span><?php endif; ?>
              <div class="min-w-0"><b class="d-block"><?= can($module . '.view') ? '<a class="text-reset" href="' . e(route('admin.' . $module . '.edit', ['id' => $r['id']])) . '">' . e($r['title']) . '</a>' : e($r['title']) ?><?= $r['is_featured'] ? ' <i class="fa-solid fa-star text-warning" title="फ़ीचर्ड"></i>' : '' ?></b>
                <span class="small text-body-secondary"><?= e($r['author'] ?? '') ?></span></div>
            </div>
          </td>
          <?php if ($types): ?><td class="small"><?= e($types[$r['type']] ?? $r['type']) ?></td><?php endif; ?>
          <td class="small"><?= e($r['category'] ?? '—') ?></td>
          <td><span class="badge-status text-bg-<?= e($bc) ?>"><i class="dot"></i><?= e($bl) ?></span><?php if ($st === 'scheduled'): ?><div class="small text-body-secondary"><?= hindi_date($r['published_at'], true) ?></div><?php endif; ?></td>
          <td class="text-end"><?= num($r['views']) ?></td>
          <td class="small text-nowrap"><?= e(time_ago($r['updated_at'])) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($st === 'published'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route($publicRoute, ['slug' => $r['slug']])) ?>" target="_blank" rel="noopener" title="वेबसाइट पर देखें" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
            <?php if (can($module . '.view')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.' . $module . '.edit', ['id' => $r['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            <?php if (can($module . '.delete')): ?><?= delete_button(route('admin.' . $module . '.destroy', ['id' => $r['id']]), '“' . $r['title'] . '” हमेशा के लिए हट जाएगा।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid <?= e(MultimediaService::KINDS[$kind]['icon']) ?>"></i><p><?= $q !== '' || $status !== '' || $type !== '' ? 'कुछ नहीं मिला।' : 'अभी यहाँ कुछ नहीं है।' ?></p>
      <?php if (can($module . '.create') && $q === '' && $status === ''): ?><a class="btn btn-brand" href="<?= e(route('admin.' . $module . '.create')) ?>"><?= e($labels['new']) ?></a><?php endif; ?></div>
  <?php endif; ?>
</section>
