<?php
use App\Models\Redirect;
$this->layout('layouts/admin');
$title = 'रीडायरेक्ट';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.seo.index')) ?>">SEO</a></li><li class="breadcrumb-item active" aria-current="page">रीडायरेक्ट</li></ol></nav>
    <h1>रीडायरेक्ट मैनेजर</h1>
    <p>पुराने पते को नए पर भेजें। स्लग बदलने पर 301 अपने आप बनता है ("अपने आप")। <?= num($stats['c'] ?? 0) ?> नियम · <?= num($stats['h'] ?? 0) ?> हिट।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.redirects.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a>
    <?php if (can('redirects.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.redirects.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया रीडायरेक्ट</a><?php endif; ?>
  </div>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => 'redirects']) ?>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <section class="panel h-100"><div class="panel-body">
      <h2 class="h6"><i class="fa-solid fa-vial me-1 text-body-secondary"></i> यह पता कहाँ जाएगा?</h2>
      <form class="d-flex gap-2" method="get"><input class="form-control font-monospace" type="text" name="test" value="<?= e($test['input'] ?? '') ?>" placeholder="/news/purani-khabar या पूरा URL" aria-label="जाँचने का पता"><button class="btn btn-dark" type="submit">जाँचें</button></form>
      <?php if ($test): $m = $test['manual'] ?? $test['any']; ?>
        <div class="alert <?= $m ? 'alert-success' : 'alert-light border' ?> small mt-2 mb-0">
          <code><?= e($test['path']) ?></code> →
          <?php if (!$m): ?>कोई रीडायरेक्ट नहीं (पेज मौजूद हो तो वही खुलेगा, वरना 404)।
          <?php elseif ($m['code'] === 410): ?><b>410</b> (हमेशा के लिए हटा दिया)
          <?php else: ?><b><?= (int) $m['code'] ?></b> <a href="<?= e($m['url']) ?>" target="_blank" rel="noopener"><?= e($m['url']) ?></a><?= !$test['manual'] ? ' <span class="text-body-secondary">(अपने आप वाला: सिर्फ़ तब, जब पुराना पता 404 हो)</span>' : '' ?><?php endif; ?>
        </div>
      <?php endif; ?>
    </div></section>
  </div>
  <?php if (can('redirects.create')): ?>
  <div class="col-lg-5">
    <section class="panel h-100"><div class="panel-body">
      <h2 class="h6"><i class="fa-solid fa-file-import me-1 text-body-secondary"></i> CSV से जोड़ें</h2>
      <form method="post" action="<?= e(route('admin.redirects.import')) ?>" enctype="multipart/form-data" class="d-flex gap-2"><?= csrf_field() ?>
        <input class="form-control" type="file" name="csv" accept=".csv,.txt" required aria-label="CSV फ़ाइल"><button class="btn btn-outline-secondary" type="submit">इंपोर्ट</button></form>
      <div class="form-text">कॉलम: <code>source,target,code</code> — जैसे <code>/old-page,/page/about-us,301</code>। पुराने-नए पते के आख़िर में <code>/*</code> = पूरा फ़ोल्डर।</div>
    </div></section>
  </div>
  <?php endif; ?>
</div>

<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="type" aria-label="कैसे बना"><option value="">सभी</option><option value="manual"<?= selected('manual', $type) ?>>हाथ से</option><option value="auto"<?= selected('auto', $type) ?>>अपने आप (<?= num($stats['a'] ?? 0) ?>)</option></select>
    <select class="form-select w-auto" name="code" aria-label="प्रकार"><option value="">हर प्रकार</option><?php foreach (Redirect::CODES as $k => $l): ?><option value="<?= $k ?>"<?= selected((string) $k, (string) $code) ?>><?= $k ?></option><?php endforeach; ?></select>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="पुराना/नया पता या नोट" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>पुराना पता</th><th>नया पता</th><th>प्रकार</th><th>हिट</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items->items as $r): ?>
      <tr class="<?= $r['status'] !== 'active' ? 'is-off' : '' ?>">
        <td class="font-monospace small text-break"><?= e($r['source']) ?><?= $r['match_type'] === 'prefix' ? '/*' : '' ?>
          <div class="text-body-secondary" style="font-family:var(--bs-body-font-family)"><?= $r['is_auto'] ? '<i class="fa-solid fa-wand-magic-sparkles"></i> अपने आप' . ($r['entity'] ? ' · ' . e($r['entity']) : '') : 'हाथ से' . ($r['creator'] ? ' · ' . e($r['creator']) : '') ?><?= $r['note'] && !$r['is_auto'] ? ' · ' . e($r['note']) : '' ?></div></td>
        <td class="font-monospace small text-break"><?= $r['code'] == 410 ? '<span class="text-body-secondary">— (हटाया गया)</span>' : e((string) $r['target']) ?></td>
        <td><span class="badge text-bg-<?= $r['code'] == 301 ? 'success' : ($r['code'] == 410 ? 'dark' : 'warning') ?>"><?= (int) $r['code'] ?></span></td>
        <td class="text-nowrap"><?= num($r['hits']) ?><?= $r['last_hit_at'] ? '<div class="small text-body-secondary">' . e(hindi_date($r['last_hit_at'], true)) . '</div>' : '' ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="text-end text-nowrap">
          <?php if (can('redirects.edit')): ?>
            <form class="d-inline" method="post" action="<?= e(route('admin.redirects.toggle', ['id' => $r['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" aria-label="<?= $r['status'] === 'active' ? 'बंद करें' : 'चालू करें' ?>" title="<?= $r['status'] === 'active' ? 'बंद करें' : 'चालू करें' ?>"><i class="fa-solid <?= $r['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i></button></form>
            <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.redirects.edit', ['id' => $r['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a>
          <?php endif; ?>
          <?php if (can('redirects.delete')): ?><?= delete_button(route('admin.redirects.destroy', ['id' => $r['id']]), $r['source'] . ' का रीडायरेक्ट हट जाएगा; पुराना पता फिर 404 देगा।') ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-diamond-turn-right"></i><p>कोई रीडायरेक्ट नहीं।</p></div><?php endif; ?>
</section>
