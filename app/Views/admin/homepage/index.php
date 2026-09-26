<?php
use App\Services\HomepageService;
$this->layout('layouts/admin');
$title = 'होमपेज बिल्डर';
$canEdit = can('homepage.edit');
$canManage = can('homepage.manage');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">होमपेज बिल्डर</li></ol></nav>
    <h1>होमपेज बिल्डर</h1>
    <p>वेबसाइट का होमपेज इन्हीं सेक्शन से, इसी क्रम में बनता है। खींचकर क्रम बदलें, हर सेक्शन की सेटिंग खोलकर बदलें।</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(url()) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe me-1"></i> होमपेज देखें</a>
</div>
<div class="alert alert-light border small"><i class="fa-solid fa-circle-info me-1"></i> ख़बरों वाले ब्लॉक (हीरो, श्रेणी, वीडियो…) की सामग्री उनके मॉड्यूल बनने पर अपने आप भरेगी। ढाँचा आप अभी तय कर सकते हैं।</div>

<div class="hb" data-home-builder data-reorder="<?= e(route('admin.homepage.reorder')) ?>">
  <?php if ($canEdit): ?>
  <aside class="panel hb-palette">
    <div class="panel-head"><h2>ब्लॉक जोड़ें</h2></div>
    <div class="hb-blocks">
      <?php foreach ($blocks as $type => $b):
          if (!empty($b['manage']) && !$canManage) continue;
          $wait = HomepageService::readiness($b); ?>
        <form method="post" action="<?= e(route('admin.homepage.store')) ?>">
          <?= csrf_field() ?><input type="hidden" name="block_type" value="<?= e($type) ?>">
          <button type="submit" class="hb-block" title="<?= e($b['desc']) ?>">
            <i class="fa-solid <?= e($b['icon']) ?>"></i><span><?= e($b['label']) ?></span>
            <?php if ($wait): ?><small>Phase <?= (int) $wait ?></small><?php endif; ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </aside>
  <?php endif; ?>

  <section class="hb-main">
    <ol class="hb-list" data-sortable="sections">
      <?php foreach ($sections as $s):
          $b = $blocks[$s['block_type']] ?? ['label' => $s['block_type'], 'icon' => 'fa-square', 'fields' => [], 'desc' => ''];
          $wait = HomepageService::readiness($b);
          $locked = !empty($b['manage']) && !$canManage; ?>
        <li class="hb-section<?= $s['is_active'] ? '' : ' is-off' ?>" id="section-<?= (int) $s['id'] ?>" data-id="<?= (int) $s['id'] ?>" data-type="<?= e($s['block_type']) ?>" data-desktop="<?= (int) $s['show_desktop'] ?>" data-mobile="<?= (int) $s['show_mobile'] ?>" draggable="<?= $canEdit ? 'true' : 'false' ?>">
          <div class="hb-head">
            <?php if ($canEdit): ?><span class="hb-handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span><?php endif; ?>
            <span class="hb-icon"><i class="fa-solid <?= e($b['icon']) ?>"></i></span>
            <div class="hb-name"><b><?= e($s['title'] ?: $b['label']) ?></b><small><?= e($b['label']) ?><?= $wait ? ' · सामग्री Phase ' . (int) $wait . ' में' : '' ?></small></div>
            <span class="hb-devices" title="कहाँ दिखेगा"><i class="fa-solid fa-desktop<?= $s['show_desktop'] ? '' : ' off' ?>" aria-label="डेस्कटॉप"></i><i class="fa-solid fa-mobile-screen<?= $s['show_mobile'] ? '' : ' off' ?>" aria-label="मोबाइल"></i></span>
            <?php if (!$s['is_active']): ?><span class="badge text-bg-light">बंद</span><?php endif; ?>
            <?php if ($canEdit): ?>
              <span class="hb-actions">
                <button type="button" class="btn btn-sm btn-icon btn-ghost" data-move="up" aria-label="ऊपर"><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" class="btn btn-sm btn-icon btn-ghost" data-move="down" aria-label="नीचे"><i class="fa-solid fa-arrow-down"></i></button>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#hs-edit-<?= (int) $s['id'] ?>" aria-expanded="false" aria-controls="hs-edit-<?= (int) $s['id'] ?>"<?= $locked ? ' disabled title="सिर्फ़ प्रबंधक"' : '' ?>><i class="fa-solid fa-sliders me-1"></i>सेटिंग</button>
              </span>
            <?php endif; ?>
          </div>
          <?php if ($canEdit && !$locked): ?>
          <div class="collapse" id="hs-edit-<?= (int) $s['id'] ?>">
            <form class="hb-form" method="post" action="<?= e(route('admin.homepage.update', ['id' => $s['id']])) ?>" novalidate>
              <?= csrf_field() ?><?= method_field('PUT') ?>
              <div class="row g-2">
                <div class="col-md-8"><label class="form-label small" for="hs<?= (int) $s['id'] ?>_title">हेडिंग</label><input class="form-control form-control-sm" id="hs<?= (int) $s['id'] ?>_title" name="title" value="<?= e($s['title']) ?>" maxlength="150"></div>
                <?php foreach ($b['fields'] as $name => $f): ?>
                  <?= block_field((int) $s['id'], $name, $f, $s['settings'][$name] ?? null, $categories, $locations) ?>
                <?php endforeach; ?>
                <div class="col-12 d-flex flex-wrap gap-3 pt-1 border-top mt-2">
                  <label class="form-check form-switch small"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1"<?= checked($s['is_active']) ?>> <span class="form-check-label">चालू</span></label>
                  <label class="form-check form-switch small"><input type="hidden" name="show_desktop" value="0"><input class="form-check-input" type="checkbox" name="show_desktop" value="1"<?= checked($s['show_desktop']) ?>> <span class="form-check-label">डेस्कटॉप पर दिखे</span></label>
                  <label class="form-check form-switch small"><input type="hidden" name="show_mobile" value="0"><input class="form-check-input" type="checkbox" name="show_mobile" value="1"<?= checked($s['show_mobile']) ?>> <span class="form-check-label">मोबाइल पर दिखे</span></label>
                </div>
              </div>
              <div class="hb-form-foot">
                <button class="btn btn-sm btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i>सेव करें</button>
                <button class="btn btn-sm btn-light" type="submit" form="hs-dup-<?= (int) $s['id'] ?>"><i class="fa-regular fa-copy me-1"></i>कॉपी</button>
                <button class="btn btn-sm btn-outline-danger ms-auto" type="submit" form="hs-del-<?= (int) $s['id'] ?>"><i class="fa-regular fa-trash-can me-1"></i>हटाएँ</button>
              </div>
            </form>
            <form id="hs-dup-<?= (int) $s['id'] ?>" method="post" action="<?= e(route('admin.homepage.duplicate', ['id' => $s['id']])) ?>" hidden><?= csrf_field() ?></form>
            <form id="hs-del-<?= (int) $s['id'] ?>" method="post" action="<?= e(route('admin.homepage.destroy', ['id' => $s['id']])) ?>" hidden data-confirm="सेक्शन “<?= e($s['title'] ?: $b['label']) ?>” होमपेज से हट जाएगा।"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
          </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (!$sections): ?><div class="empty-state panel"><i class="fa-solid fa-table-cells-large"></i><p>होमपेज पर अभी कोई सेक्शन नहीं है। बाईं ओर से ब्लॉक जोड़ें।</p></div><?php endif; ?>
  </section>

  <aside class="panel hb-preview">
    <div class="panel-head"><h2>ढाँचा</h2>
      <div class="btn-group btn-group-sm" role="group" aria-label="प्रीव्यू">
        <button type="button" class="btn btn-light active" data-preview-device="desktop" aria-pressed="true"><i class="fa-solid fa-desktop"></i></button>
        <button type="button" class="btn btn-light" data-preview-device="mobile" aria-pressed="false"><i class="fa-solid fa-mobile-screen"></i></button>
      </div>
    </div>
    <div class="wire" data-wire data-device="desktop">
      <div class="wire-head"></div><div class="wire-nav"></div>
      <div data-wire-list></div>
      <div class="wire-foot"></div>
    </div>
  </aside>
</div>
