<?php $this->layout('layouts/admin'); $title = 'साइट सेटिंग: ' . $schema['label'];
$tabRoute ??= 'admin.settings'; $saveRoute ??= 'admin.settings.update'; // SEO कमांड सेंटर इसी व्यू को अपने रूट से दिखाता है
$section ??= ['साइट सेटिंग', route('admin.settings.index'), 'ब्रांड, संपर्क, हेडर-फ़ुटर और फ़ीचर यहीं से बदलें। किसी डेवलपर की ज़रूरत नहीं।']; ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e($section[1]) ?>"><?= e($section[0]) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= e($schema['label']) ?></li></ol></nav>
    <h1><?= e($section[0]) ?></h1>
    <p><?= e($section[2]) ?></p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= e(url()) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe me-1"></i> वेबसाइट देखें</a>
</div>

<div class="settings-shell">
  <nav class="settings-nav" aria-label="सेटिंग के टैब">
    <?php foreach ($tabs as $key => $t): ?>
      <a href="<?= e(route($tabRoute, ['tab' => $key])) ?>" class="<?= $key === $active ? 'active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
        <i class="fa-solid <?= e($t['icon']) ?> fa-fw"></i><span><?= e($t['label']) ?></span>
        <?php if (in_array($t['permission'] ?? 'edit', ['manage', 'seo.manage'], true)): ?><i class="fa-solid fa-lock small ms-auto opacity-50" title="संवेदनशील: सिर्फ़ प्रबंधक"></i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <form class="panel settings-form" method="post" action="<?= e(route($saveRoute, ['tab' => $active])) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="panel-head"><h2><i class="fa-solid <?= e($schema['icon']) ?> me-2 text-body-secondary"></i><?= e($schema['label']) ?></h2>
      <?php if (!$canEdit): ?><span class="badge text-bg-light"><i class="fa-solid fa-eye me-1"></i> सिर्फ़ देख सकते हैं</span><?php endif; ?></div>
    <div class="panel-body">
      <?php if (in_array($schema['permission'] ?? '', ['manage', 'seo.manage'], true)): ?>
        <div class="alert alert-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i> यह संवेदनशील सेटिंग है। हर बदलाव ऑडिट लॉग में दर्ज होता है।</div>
      <?php endif; ?>
      <?php if ($active === 'layout'): ?>
        <div class="alert alert-light border small"><i class="fa-solid fa-circle-info me-1"></i> हेडर और फ़ुटर के लिंक <a href="<?= e(route('admin.menus.index')) ?>">मेनू बिल्डर</a> से बदलें: टॉप मेनू, मुख्य नेविगेशन, फ़ुटर के 4 कॉलम और लीगल मेनू।</div>
      <?php endif; ?>
      <div class="row">
        <?php foreach ($schema['fields'] as $name => $f): ?>
          <?= setting_field($name, $f, !$canEdit) ?>
        <?php endforeach; ?>
      </div>
      <?php if ($active === 'branding'): ?>
        <div class="brand-preview" aria-hidden="true">
          <span class="bp-label">प्रीव्यू</span>
          <div class="bp-bar" style="background:<?= e(setting('secondary_color')) ?>"><span></span><span></span></div>
          <div class="bp-nav" style="background:<?= e(setting('primary_color')) ?>"><span>होम</span><span>देश</span><span>राज्य</span><span>खेल</span></div>
          <div class="bp-body"><b style="font-family:'<?= e(setting('font_heading')) ?>'">ख़बर का शीर्षक ऐसा दिखेगा</b><p style="font-family:'<?= e(setting('font_body')) ?>'">लेख का सामान्य टेक्स्ट इस फ़ॉन्ट में दिखेगा।</p></div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($canEdit): ?><div class="panel-foot"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i>सेव करें</button></div><?php endif; ?>
  </form>
</div>
