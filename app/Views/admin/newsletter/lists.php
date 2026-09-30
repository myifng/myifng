<?php
$this->layout('layouts/admin');
$title = 'न्यूज़लेटर सूचियाँ';
$l = $edit;
?>
<div class="page-head"><div>
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.newsletter.index')) ?>">न्यूज़लेटर</a></li><li class="breadcrumb-item active" aria-current="page">सूचियाँ</li></ol></nav>
  <h1>सूचियाँ</h1><p>जैसे "रोज़ की बड़ी ख़बरें", "खेल", "गोरखपुर"। सार्वजनिक सूचियाँ पाठक फ़ॉर्म में चुन सकते हैं।</p></div></div>
<?= $this->insert('admin/newsletter/_nav', ['active' => 'lists']) ?>
<div class="row g-3">
  <div class="col-xl-8"><section class="panel">
    <div class="table-responsive"><table class="table align-middle mb-0 data-table">
      <thead><tr><th>सूची</th><th>सब्सक्राइब्ड</th><th></th><th class="text-end">काम</th></tr></thead>
      <tbody><?php foreach ($items as $x): ?><tr>
        <td><b><?= e($x['name']) ?></b><?= $x['description'] ? '<div class="small text-body-secondary">' . e($x['description']) . '</div>' : '' ?></td>
        <td><a href="<?= e(route('admin.newsletter.subscribers')) ?>?list=<?= (int) $x['id'] ?>&amp;status=subscribed"><?= num($x['subs']) ?></a></td>
        <td class="small"><?= $x['is_default'] ? '<span class="badge text-bg-primary">डिफ़ॉल्ट</span> ' : '' ?><?= $x['is_public'] ? 'सार्वजनिक' : 'अंदरूनी' ?></td>
        <td class="text-end text-nowrap"><?php if (can('newsletter.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="?edit=<?= (int) $x['id'] ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
          <?php if (can('newsletter.delete')): ?><?= delete_button(route('admin.newsletter.lists.destroy', ['id' => $x['id']]), 'सूची हटेगी; सब्सक्राइबर बने रहेंगे।') ?><?php endif; ?></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
  </section></div>
  <?php if (can('newsletter.edit')): ?>
  <div class="col-xl-4"><section class="panel"><div class="panel-head"><h2><?= $l ? 'बदलें: ' . e($l['name']) : 'नई सूची' ?></h2></div><div class="panel-body">
    <form method="post" action="<?= e(route('admin.newsletter.lists.save')) ?>" novalidate><?= csrf_field() ?><?php if ($l): ?><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><?php endif; ?>
      <?= field('text', 'name', 'नाम', $l['name'] ?? '', ['required' => true, 'attrs' => ['maxlength' => 120]]) ?>
      <?= field('text', 'description', 'विवरण', $l['description'] ?? '', ['attrs' => ['maxlength' => 300]]) ?>
      <?= field('select', 'category_id', 'श्रेणी से जुड़ी (भविष्य में उस श्रेणी का अपने आप डाइजेस्ट)', $l['category_id'] ?? '', ['options' => $categories, 'empty' => '— कोई नहीं —']) ?>
      <?= field('switch', 'is_public', 'सार्वजनिक (पाठक चुन सकें)', (int) ($l['is_public'] ?? 1)) ?>
      <?= field('switch', 'is_default', 'डिफ़ॉल्ट (नए सब्सक्राइबर अपने आप)', (int) ($l['is_default'] ?? 0)) ?>
      <div class="d-flex gap-2"><button class="btn btn-brand" type="submit">सेव करें</button><?php if ($l): ?><a class="btn btn-light" href="<?= e(route('admin.newsletter.lists')) ?>">रद्द</a><?php endif; ?></div>
    </form></div></section></div>
  <?php endif; ?>
</div>
