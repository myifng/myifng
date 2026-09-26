<?php
use App\Models\Location;
$this->layout('layouts/admin');
$title = $current ? $current['name'] . ' · लोकेशन' : 'लोकेशन';
$searching = $q !== '';
$here = $current ? route('admin.locations.index') . '?parent=' . $current['id'] : route('admin.locations.index');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li>
      <li class="breadcrumb-item"><a href="<?= e(route('admin.locations.index')) ?>">लोकेशन</a></li>
      <?php foreach ($ancestors as $a): ?><li class="breadcrumb-item"><a href="<?= e(route('admin.locations.index')) ?>?parent=<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a></li><?php endforeach; ?>
      <?php if ($current): ?><li class="breadcrumb-item active" aria-current="page"><?= e($current['name']) ?></li><?php endif; ?>
    </ol></nav>
    <h1><?= $current ? e($current['name']) . ' <span class="badge text-bg-light fw-normal fs-6">' . e(Location::SHORT[$current['type']]) . '</span>' : 'लोकेशन' ?></h1>
    <p><?php if ($current && $current['path']): ?>URL: <span class="font-monospace">/<?= e($current['path']) ?>/</span> · <?php endif; ?>
      राज्य <?= num($stats['state'] ?? 0) ?> · ज़िले <?= num($stats['district'] ?? 0) ?> · शहर <?= num($stats['city'] ?? 0) ?> · तहसील <?= num($stats['tehsil'] ?? 0) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($current && can('locations.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.locations.edit', ['id' => $current['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> इसे बदलें</a><?php endif; ?>
    <?php if (can('locations.create')): ?>
      <a class="btn btn-outline-secondary" href="<?= e(route('admin.locations.import')) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV इम्पोर्ट</a>
      <?php if ($childTypes): ?><a class="btn btn-brand" href="<?= e(route('admin.locations.create')) ?><?= $current ? '?parent=' . (int) $current['id'] : '' ?>"><i class="fa-solid fa-plus me-1"></i> <?= $current ? e($current['name']) . ' में जोड़ें' : 'नया देश' ?></a><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <form class="filter-bar" method="get">
    <?php if ($current && !$searching): ?><input type="hidden" name="parent" value="<?= (int) $current['id'] ?>"><?php endif; ?>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="कहीं भी खोजें: नाम, अंग्रेज़ी नाम, URL या कोड" aria-label="लोकेशन खोजें"></div>
    <select class="form-select w-auto" name="type" aria-label="स्तर">
      <option value="">सभी स्तर</option>
      <?php foreach (Location::SHORT as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $type) ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-dark" type="submit">खोजें</button>
    <?php if ($searching): ?><a class="btn btn-link" href="<?= e($here) ?>">खोज हटाएँ</a><?php endif; ?>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>नाम</th><th>स्तर</th><th>URL</th><th class="text-center">नीचे</th><th>लोकप्रिय</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($items->items as $i => $l): ?>
        <tr class="<?= $l['status'] !== 'active' ? 'is-off' : '' ?>">
          <td>
            <a class="fw-bold text-reset" href="<?= e(route('admin.locations.index')) ?>?parent=<?= (int) $l['id'] ?>"><?= e($l['name']) ?></a>
            <span class="d-block small text-body-secondary"><?= e($l['name_en'] ?? '') ?><?= $searching && $l['parent_name'] ? ' · ' . e($l['parent_name']) : '' ?></span>
          </td>
          <td><span class="chip-sm"><?= e(Location::SHORT[$l['type']]) ?></span></td>
          <td class="small font-monospace"><?= $l['path'] ? '/' . e($l['path']) . '/' : '<span class="text-body-secondary" title="देश और मंडल URL में नहीं आते">—</span>' ?></td>
          <td class="text-center"><?= $l['children'] ? '<a href="' . e(route('admin.locations.index')) . '?parent=' . (int) $l['id'] . '">' . num($l['children']) . '</a>' : '<span class="text-body-secondary">0</span>' ?></td>
          <td>
            <?php if (can('locations.edit')): ?>
              <form method="post" action="<?= e(route('admin.locations.popular', ['id' => $l['id']])) ?>" class="d-inline"><?= csrf_field() ?>
                <button class="btn btn-sm btn-icon <?= $l['is_popular'] ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit" data-no-lock aria-pressed="<?= $l['is_popular'] ? 'true' : 'false' ?>" title="<?= $l['is_popular'] ? 'लोकप्रिय से हटाएँ' : 'लोकप्रिय बनाएँ (मेरा शहर में ऊपर)' ?>" aria-label="लोकप्रिय: <?= e($l['name']) ?>"><i class="fa-<?= $l['is_popular'] ? 'solid' : 'regular' ?> fa-star"></i></button>
              </form>
            <?php else: ?><?= $l['is_popular'] ? '<i class="fa-solid fa-star text-warning"></i>' : '' ?><?php endif; ?>
          </td>
          <td><?= status_badge($l['status']) ?></td>
          <td class="text-end text-nowrap">
            <?php if (!$searching && can('locations.edit')): ?>
              <form method="post" action="<?= e(route('admin.locations.move', ['id' => $l['id']])) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" data-no-lock title="ऊपर" aria-label="<?= e($l['name']) ?> ऊपर"<?= $i === 0 && $items->page === 1 ? ' disabled' : '' ?>><i class="fa-solid fa-arrow-up"></i></button></form>
            <?php endif; ?>
            <?php if (can('locations.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.locations.edit', ['id' => $l['id']])) ?>" title="बदलें" aria-label="बदलें: <?= e($l['name']) ?>"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            <?php if (can('locations.delete') && !$l['children']): ?><?= delete_button(route('admin.locations.destroy', ['id' => $l['id']]), '“' . $l['name'] . '” हट जाएगी।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-map-location-dot"></i><p><?= $searching ? 'कुछ नहीं मिला।' : ($current ? e($current['name']) . ' के नीचे अभी कुछ नहीं जुड़ा।' : 'अभी कोई लोकेशन नहीं है।') ?></p></div>
  <?php endif; ?>
</section>
