<?php
$this->layout('layouts/admin');
[$label, , $serious, $hint] = $issue;
$title = 'SEO: ' . $label;
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.seo.index')) ?>">SEO</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($label) ?></li></ol></nav>
    <h1><?= e($label) ?> <span class="text-body-secondary fs-5">(<?= num($items->total) ?>)</span></h1>
    <p><?= e($hint) ?></p>
  </div>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => 'index']) ?>
<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="issue" aria-label="कमी" onchange="this.form.submit()">
      <?php foreach ($issues as $k => $i): ?><option value="<?= e($k) ?>"<?= selected($k, $key) ?>><?= e($i[0]) ?> (<?= num($counts[$k] ?? 0) ?>)</option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-dark" type="submit">दिखाएँ</button></noscript>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>ख़बर</th><th>SEO शीर्षक / विवरण</th><th>शब्द</th><th>प्रकाशित</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items->items as $n): $st = $n['meta_title'] ?: $n['title']; $sd = $n['meta_description'] ?: $n['summary']; ?>
      <tr>
        <td><b><?= e($n['title']) ?></b><div class="small text-body-secondary"><?= e($n['cat'] ?? 'बिना श्रेणी') ?><?= str_starts_with((string) $n['robots'], 'noindex') ? ' · noindex' : '' ?></div></td>
        <td class="small"><span class="<?= mb_strlen($st) > 70 ? 'text-danger' : '' ?>"><?= e(\App\Helpers\Str::limit($st, 90)) ?> <span class="text-body-secondary">(<?= mb_strlen($st) ?>)</span></span>
          <div class="text-body-secondary"><?= $sd ? e(\App\Helpers\Str::limit((string) $sd, 110)) . ' (' . mb_strlen((string) $sd) . ')' : '<span class="text-danger">विवरण नहीं</span>' ?></div></td>
        <td class="<?= $n['word_count'] < 150 ? 'text-danger' : '' ?>"><?= num($n['word_count']) ?></td>
        <td class="small text-nowrap"><?= e(hindi_date($n['published_at'])) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(\App\Services\NewsService::url($n)) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
          <?php if (can('news.edit')): ?><a class="btn btn-sm btn-brand" href="<?= e(route('admin.news.edit', ['id' => $n['id']])) ?>">ठीक करें</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>इस कमी वाली कोई ख़बर नहीं।</p></div><?php endif; ?>
</section>
