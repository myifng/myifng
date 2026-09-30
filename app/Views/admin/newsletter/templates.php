<?php
$this->layout('layouts/admin');
$title = 'न्यूज़लेटर टेम्पलेट';
$t = $edit;
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.newsletter.index')) ?>">न्यूज़लेटर</a></li><li class="breadcrumb-item active" aria-current="page">टेम्पलेट</li></ol></nav>
  <h1>टेम्पलेट</h1><p>ईमेल का ढाँचा (HTML)। खाने: <code>{{content}}</code> <code>{{top_news}}</code> <code>{{unsubscribe_url}}</code> <code>{{subject}}</code> <code>{{preheader}}</code> <code>{{site_name}}</code> <code>{{site_url}}</code> <code>{{logo}}</code> <code>{{brand_color}}</code> <code>{{name}}</code></p></div></div>
<?= $this->insert('admin/newsletter/_nav', ['active' => 'templates']) ?>
<div class="row g-3">
  <div class="col-xl-4"><section class="panel"><ul class="list-group list-group-flush">
    <?php foreach ($items as $x): ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><?= e($x['name']) ?><?= $x['is_default'] ? ' <span class="badge text-bg-primary">डिफ़ॉल्ट</span>' : '' ?></span>
      <span class="text-nowrap"><?php if (can('newsletter.manage')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="?edit=<?= (int) $x['id'] ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a>
        <?php if (!$x['is_default']): ?><?= delete_button(route('admin.newsletter.templates.destroy', ['id' => $x['id']]), 'टेम्पलेट हटेगा।') ?><?php endif; ?><?php endif; ?></span></li><?php endforeach; ?>
  </ul></section></div>
  <?php if (can('newsletter.manage')): ?>
  <div class="col-xl-8"><section class="panel"><div class="panel-head"><h2><?= $t ? 'बदलें: ' . e($t['name']) : 'नया टेम्पलेट' ?></h2></div><div class="panel-body">
    <form method="post" action="<?= e(route('admin.newsletter.templates.save')) ?>" novalidate><?= csrf_field() ?><?php if ($t): ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><?php endif; ?>
      <?= field('text', 'name', 'नाम', $t['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?>
      <?= field('textarea', 'html', 'HTML', $t['html'] ?? '', ['rows' => 16, 'class' => 'font-monospace small', 'attrs' => ['spellcheck' => 'false']]) ?>
      <?= field('switch', 'is_default', 'डिफ़ॉल्ट टेम्पलेट', (int) ($t['is_default'] ?? 0)) ?>
      <div class="d-flex gap-2"><button class="btn btn-brand" type="submit">सेव करें</button><?php if ($t): ?><a class="btn btn-light" href="<?= e(route('admin.newsletter.templates')) ?>">नया</a><?php endif; ?></div>
    </form></div></section></div>
  <?php else: ?><div class="col-xl-8"><div class="alert alert-light border">टेम्पलेट का HTML सिर्फ़ प्रबंधक (newsletter.manage) बदल सकते हैं।</div></div><?php endif; ?>
</div>
