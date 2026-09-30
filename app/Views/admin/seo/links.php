<?php
$this->layout('layouts/admin');
$title = 'टूटे अंदरूनी लिंक';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.seo.index')) ?>">SEO</a></li><li class="breadcrumb-item active" aria-current="page">टूटे लिंक</li></ol></nav>
    <h1>टूटे अंदरूनी लिंक</h1>
    <p>प्रकाशित ख़बरों में अपनी साइट के ऐसे लिंक जो किसी हटी/न मिलने वाली ख़बर, श्रेणी, टैग, टॉपिक या पेज पर जाते हैं।</p>
  </div>
  <a class="btn btn-brand" href="<?= e(route('admin.seo.links')) ?>?run=1"><i class="fa-solid fa-magnifying-glass me-1"></i> <?= $broken === null ? 'जाँच शुरू करें' : 'दोबारा जाँचें' ?></a>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => 'links']) ?>
<section class="panel">
  <div class="panel-head"><h2>ख़बरों के अंदर के लिंक</h2><?php if ($broken !== null): ?><span class="small text-body-secondary">पिछली 2000 ख़बरें जाँची गईं</span><?php endif; ?></div>
  <?php if ($broken === null): ?>
    <div class="empty-state"><i class="fa-solid fa-chain-broken"></i><p>"जाँच शुरू करें" दबाएँ। बड़ी साइट पर कुछ सेकंड लग सकते हैं।</p></div>
  <?php elseif (!$broken): ?>
    <div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>कोई टूटा अंदरूनी लिंक नहीं मिला।</p></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table align-middle mb-0 data-table">
    <thead><tr><th>ख़बर</th><th>लिंक</th><th>समस्या</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($broken as $b): ?>
      <tr><td><b><?= e(\App\Helpers\Str::limit($b['title'], 80)) ?></b></td>
        <td class="font-monospace small text-break"><?= e($b['path']) ?></td>
        <td class="small"><?= e($b['reason']) ?><?php if ($b['redirect']): ?><div class="text-success"><i class="fa-solid fa-diamond-turn-right"></i> रीडायरेक्ट से चल रहा है; फिर भी लिंक सीधा ठीक करें</div><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <?php if (can('news.edit')): ?><a class="btn btn-sm btn-brand" href="<?= e(route('admin.news.edit', ['id' => $b['id']])) ?>">ख़बर ठीक करें</a><?php endif; ?>
          <?php if (!$b['redirect'] && can('redirects.create')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.redirects.create')) ?>?source=<?= e(rawurlencode($b['path'])) ?>">रीडायरेक्ट</a><?php endif; ?>
        </td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<section class="panel mt-3">
  <div class="panel-head"><h2>पाठकों को मिले टूटे लिंक (404 लॉग से)</h2><a class="small" href="<?= e(route('admin.seo.404')) ?>?view=internal">सब देखें</a></div>
  <?php if ($internal): ?>
  <div class="table-responsive"><table class="table align-middle mb-0 data-table">
    <thead><tr><th>टूटा पता</th><th>किस पेज पर लिंक है</th><th>हिट</th></tr></thead>
    <tbody><?php foreach ($internal as $r): ?>
      <tr><td class="font-monospace small text-break"><?= e($r['path']) ?></td>
        <td class="small text-break"><?= $r['referrer'] ? '<a href="' . e($r['referrer']) . '" target="_blank" rel="noopener">' . e(\App\Helpers\Str::limit($r['referrer'], 90)) . '</a>' : '' ?></td>
        <td><?= num($r['hits']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>अभी तक कोई नहीं।</p></div><?php endif; ?>
</section>
