<?php
$this->layout('layouts/admin');
$title = 'मेनू: ' . $menu['name'];
$canEdit = can('menus.edit');
$loc = config('menus.locations.' . $menu['location']) ?? $menu['location'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.menus.index')) ?>">मेनू बिल्डर</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($loc) ?></li></ol></nav>
    <h1><?= e($loc) ?></h1>
    <p>खींचकर क्रम बदलें। <i class="fa-solid fa-arrow-right"></i> बटन से किसी लिंक को ऊपर वाले का उप-मेनू बनाएँ (<?= (int) $maxDepth ?> स्तर तक)।</p>
  </div>
</div>

<div class="menu-builder" data-menu-builder data-save="<?= e(route('admin.menus.update', ['id' => $menu['id']])) ?>" data-max-depth="<?= (int) $maxDepth ?>" data-readonly="<?= $canEdit ? '0' : '1' ?>">
  <?php if ($canEdit): ?>
  <aside class="panel mb-add">
    <div class="panel-head"><h2>लिंक जोड़ें</h2></div>
    <div class="panel-body">
      <label class="form-label" for="mb_type">प्रकार</label>
      <select class="form-select mb-3" id="mb_type">
        <?php foreach ($types as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t['label']) ?></option><?php endforeach; ?>
      </select>
      <?php foreach ($options as $k => $rows): ?>
        <div class="mb-3" data-type-panel="<?= e($k) ?>" hidden>
          <label class="form-label" for="mb_ref_<?= e($k) ?>"><?= e($types[$k]['label']) ?> चुनें</label>
          <select class="form-select" id="mb_ref_<?= e($k) ?>" data-ref-select>
            <?php foreach ($rows as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['title']) ?></option><?php endforeach; ?>
          </select>
          <?php if (!$rows): ?><div class="form-text">अभी कोई <?= e($types[$k]['label']) ?> नहीं है।</div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="mb-3" data-type-panel="custom external" hidden>
        <label class="form-label" for="mb_url">पता (URL)</label>
        <input class="form-control" id="mb_url" placeholder="/page/about-us या https://…">
      </div>
      <label class="form-label" for="mb_title">दिखने वाला नाम</label>
      <input class="form-control mb-3" id="mb_title" maxlength="120" placeholder="ख़ाली हो तो चुने गए पेज का नाम">
      <button type="button" class="btn btn-dark w-100" data-add-item><i class="fa-solid fa-plus me-1"></i>मेनू में जोड़ें</button>
      <p class="small text-body-secondary mt-3 mb-0">उपलब्ध नहीं: <?= e(implode(', ', array_map(fn($t) => $t['label'], array_diff_key($allTypes, $types))) ?: '—') ?> (अपने phase में आएँगे)</p>
    </div>
  </aside>
  <?php endif; ?>

  <section class="panel mb-main">
    <div class="panel-head">
      <div class="d-flex align-items-center gap-2 flex-grow-1">
        <label class="visually-hidden" for="mb_name">मेनू का नाम</label>
        <input class="form-control fw-semibold mb-name" id="mb_name" value="<?= e($menu['name']) ?>" maxlength="100"<?= $canEdit ? '' : ' readonly' ?>>
      </div>
      <?php if ($canEdit): ?><button type="button" class="btn btn-brand" data-save-menu><i class="fa-solid fa-check me-1"></i>मेनू सेव करें</button><?php endif; ?>
    </div>
    <div class="alert alert-danger m-3 mb-0" data-menu-errors hidden role="alert"></div>
    <ol class="mb-tree" data-tree aria-label="मेनू के लिंक"></ol>
    <div class="empty-state" data-tree-empty hidden><i class="fa-solid fa-bars-staggered"></i><p>अभी इस मेनू में कोई लिंक नहीं है। बाईं ओर से जोड़ें।</p></div>
  </section>
</div>

<template id="mbItemTpl">
  <li class="mb-item" draggable="true">
    <div class="mb-row">
      <span class="mb-handle" title="खींचें" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
      <span class="mb-title"></span>
      <span class="mb-type"></span>
      <span class="mb-off badge text-bg-light" hidden>बंद</span>
      <span class="mb-actions">
        <button type="button" class="btn btn-sm btn-icon btn-ghost" data-act="up" aria-label="ऊपर"><i class="fa-solid fa-arrow-up"></i></button>
        <button type="button" class="btn btn-sm btn-icon btn-ghost" data-act="down" aria-label="नीचे"><i class="fa-solid fa-arrow-down"></i></button>
        <button type="button" class="btn btn-sm btn-icon btn-ghost" data-act="out" aria-label="बाहर (स्तर घटाएँ)"><i class="fa-solid fa-arrow-left"></i></button>
        <button type="button" class="btn btn-sm btn-icon btn-ghost" data-act="in" aria-label="अंदर (उप-मेनू बनाएँ)"><i class="fa-solid fa-arrow-right"></i></button>
        <button type="button" class="btn btn-sm btn-icon btn-ghost" data-act="edit" aria-label="बदलें"><i class="fa-solid fa-pen"></i></button>
        <button type="button" class="btn btn-sm btn-icon btn-ghost text-danger" data-act="remove" aria-label="हटाएँ"><i class="fa-solid fa-xmark"></i></button>
      </span>
    </div>
    <div class="mb-edit" hidden>
      <div class="row g-2">
        <div class="col-md-5"><label class="form-label small">नाम</label><input class="form-control form-control-sm" data-f="title" maxlength="120"></div>
        <div class="col-md-7" data-url-wrap><label class="form-label small">पता</label><input class="form-control form-control-sm" data-f="url"></div>
        <div class="col-12 d-flex gap-3 flex-wrap">
          <label class="form-check small"><input class="form-check-input" type="checkbox" data-f="target_blank"> <span class="form-check-label">नई टैब में खुले</span></label>
          <label class="form-check small"><input class="form-check-input" type="checkbox" data-f="is_active"> <span class="form-check-label">चालू</span></label>
        </div>
      </div>
    </div>
  </li>
</template>
<script type="application/json" id="menuData"><?= json_encode(['items' => array_map(fn($i) => [
    'title' => $i['title'], 'type' => $i['type'], 'reference_id' => $i['reference_id'] ? (int) $i['reference_id'] : null, 'url' => $i['url'],
    'target_blank' => (int) $i['target_blank'], 'is_active' => (int) $i['is_active'], 'depth' => (int) $i['depth'], 'ref_title' => $i['ref_title'], 'missing' => $i['href'] === null && $i['type'] !== 'external',
], $tree), 'types' => array_map(fn($t) => $t['label'], $allTypes)], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
