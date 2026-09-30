<?php
use App\Models\NotFound;
$this->layout('layouts/admin');
$title = '404 मॉनिटर';
$canEdit = can('seo.edit');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.seo.index')) ?>">SEO</a></li><li class="breadcrumb-item active" aria-current="page">404</li></ol></nav>
    <h1>404 मॉनिटर</h1>
    <p>वेबसाइट पर जो पते नहीं मिले। पुराने/बदले पतों को सही पेज पर रीडायरेक्ट करें; "अंदरूनी लिंक" = आपकी ही साइट के किसी पेज से टूटा लिंक।</p>
  </div>
  <?php if ($canEdit): ?>
  <form method="post" action="<?= e(route('admin.seo.404.action')) ?>" data-confirm="ठीक किए/अनदेखे और 30 दिन से पुराने 404 हट जाएँगे।"><?= csrf_field() ?><input type="hidden" name="action" value="purge">
    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-broom me-1"></i> पुराने साफ़ करें</button></form>
  <?php endif; ?>
</div>
<?= $this->insert('admin/seo/_nav', ['active' => '404']) ?>
<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी</option><?php foreach (NotFound::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $status) ?>><?= e($l) ?> (<?= num($tally[$k] ?? 0) ?>)</option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="view" aria-label="कौन-से"><option value="">सब हिट</option><option value="people"<?= selected('people', $view) ?>>सिर्फ़ पाठक (बॉट नहीं)</option><option value="internal"<?= selected('internal', $view) ?>>अंदरूनी टूटे लिंक</option></select>
    <select class="form-select w-auto" name="sort" aria-label="क्रम"><option value="">सबसे ज़्यादा हिट</option><option value="recent"<?= selected('recent', $sort) ?>>हाल के</option></select>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="पते में खोजें" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">दिखाएँ</button>
  </form>
  <?php if ($items->items): ?>
  <form method="post" action="<?= e(route('admin.seo.404.action')) ?>" id="nfBulk"><?= csrf_field() ?></form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><?php if ($canEdit): ?><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all="nf" aria-label="सभी चुनें"></th><?php endif; ?><th>पता</th><th>हिट</th><th>कहाँ से</th><th>पहली / आख़िरी बार</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items->items as $r): ?>
      <tr class="<?= $r['status'] !== 'new' ? 'is-off' : '' ?>">
        <?php if ($canEdit): ?><td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="nfBulk" data-check="nf" aria-label="चुनें"></td><?php endif; ?>
        <td class="text-break"><a class="font-monospace small" href="<?= e(url(ltrim($r['path'], '/'))) ?>" target="_blank" rel="noopener nofollow"><?= e($r['path']) ?></a>
          <?= $r['is_internal'] ? ' <span class="badge text-bg-warning">अंदरूनी लिंक</span>' : '' ?><?= $r['status'] !== 'new' ? ' <span class="badge text-bg-light">' . e(NotFound::STATUSES[$r['status']]) . '</span>' : '' ?>
          <?php if ($r['redirect']): ?><div class="small text-success"><i class="fa-solid fa-diamond-turn-right"></i> रीडायरेक्ट है: <?= e($r['redirect']) ?></div><?php endif; ?></td>
        <td class="text-nowrap"><b><?= num($r['hits']) ?></b><?= $r['bot_hits'] ? '<div class="small text-body-secondary">+' . num($r['bot_hits']) . ' बॉट</div>' : '' ?></td>
        <td class="small text-break" style="max-width:260px"><?= $r['referrer'] ? '<a href="' . e($r['referrer']) . '" target="_blank" rel="noopener nofollow">' . e(\App\Helpers\Str::limit($r['referrer'], 70)) . '</a>' : '<span class="text-body-secondary">सीधे / पता नहीं</span>' ?></td>
        <td class="small text-nowrap"><?= e(hindi_date($r['first_seen'])) ?><div class="text-body-secondary"><?= e(hindi_date($r['last_seen'], true)) ?></div></td>
        <td class="text-end text-nowrap">
          <?php if (can('redirects.create') && !$r['redirect']): ?><a class="btn btn-sm btn-brand" href="<?= e(route('admin.redirects.create')) ?>?source=<?= e(rawurlencode($r['path'])) ?>&amp;nf=<?= (int) $r['id'] ?>&amp;return=404">रीडायरेक्ट</a><?php endif; ?>
          <?php if ($canEdit): ?><form class="d-inline" method="post" action="<?= e(route('admin.seo.404.action')) ?>"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="<?= $r['status'] === 'ignored' ? 'restore' : 'ignore' ?>"><?= $r['status'] === 'ignored' ? 'वापस' : 'अनदेखा' ?></button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if ($canEdit): ?>
  <div class="bulk-bar"><span class="small text-body-secondary">चुने हुए:</span>
    <button class="btn btn-sm btn-outline-secondary" type="submit" form="nfBulk" name="action" value="ignore">अनदेखा करें</button>
    <button class="btn btn-sm btn-outline-secondary" type="submit" form="nfBulk" name="action" value="restore">वापस लाएँ</button>
    <button class="btn btn-sm btn-outline-danger" type="submit" form="nfBulk" name="action" value="delete">हटाएँ</button></div>
  <?php endif; ?>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-circle-check"></i><p>कोई 404 नहीं। बढ़िया!</p></div><?php endif; ?>
</section>
