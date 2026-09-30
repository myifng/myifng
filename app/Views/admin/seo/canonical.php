<?php
use App\Models\News;
$this->layout('layouts/admin');
$title = 'Canonical और noindex';
$host = rtrim((string) (setting('seo_canonical_host') ?: config('app.url')), '/');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.seo.index')) ?>">SEO</a></li><li class="breadcrumb-item active" aria-current="page">Canonical</li></ol></nav>
    <h1>Canonical मैनेजर</h1>
    <p>हर पेज का canonical अपने आप बनता है (मुख्य डोमेन से, <code>utm_</code> जैसे पैरामीटर हटाकर)। यहाँ वो सामग्री है जिसका canonical किसी और पते पर है या जो Google में नहीं जाती।</p>
  </div>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => 'canonical']) ?>
<section class="panel mb-3"><div class="panel-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
  <div><span class="small text-body-secondary d-block">मुख्य डोमेन (canonical)</span><b class="font-monospace"><?= e($host) ?></b><?= setting('seo_canonical_host') ? '' : ' <span class="small text-body-secondary">(इंस्टॉल वाला पता)</span>' ?></div>
  <?php if (can('seo.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.seo.settings', ['tab' => 'seo'])) ?>">बदलें</a><?php endif; ?>
</div></section>
<section class="panel">
  <div class="panel-head"><h2>ख़बरें (<?= count($news) ?>)</h2></div>
  <?php if ($news): ?>
  <div class="table-responsive"><table class="table align-middle mb-0 data-table">
    <thead><tr><th>ख़बर</th><th>Canonical</th><th>सर्च इंजन</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($news as $n): ?>
      <tr><td><b><?= e(\App\Helpers\Str::limit($n['title'], 80)) ?></b><div class="small text-body-secondary"><?= e(\App\Services\NewsWorkflow::label($n['status'])) ?></div></td>
        <td class="small text-break"><?= $n['canonical_url'] ? '<a href="' . e($n['canonical_url']) . '" target="_blank" rel="noopener nofollow">' . e($n['canonical_url']) . '</a>' : '<span class="text-body-secondary">अपना (सामान्य)</span>' ?></td>
        <td class="small"><?= e(News::ROBOTS[$n['robots']] ?? $n['robots']) ?></td>
        <td class="text-end"><?php if (can('news.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>">बदलें</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>सारी ख़बरें अपने canonical और index पर हैं।</p></div><?php endif; ?>
</section>
<section class="panel mt-3">
  <div class="panel-head"><h2>noindex पेज (<?= count($pages) ?>)</h2></div>
  <?php if ($pages): ?>
  <ul class="list-group list-group-flush"><?php foreach ($pages as $p): ?>
    <li class="list-group-item d-flex justify-content-between align-items-center"><span><?= e($p['title']) ?> <small class="text-body-secondary">/page/<?= e($p['slug']) ?> · <?= e($p['robots']) ?></small></span>
      <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.pages.edit', ['id' => $p['id']])) ?>">बदलें</a><?php endif; ?></li>
  <?php endforeach; ?></ul>
  <?php else: ?><div class="empty-state"><p>कोई नहीं।</p></div><?php endif; ?>
</section>
