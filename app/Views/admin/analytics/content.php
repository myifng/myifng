<?php
use App\Controllers\Admin\AnalyticsController as AC;
$this->layout('layouts/admin');
[$head, $icon] = AC::DIMS[$dim];
$title = $head . ' · एनालिटिक्स';
$qs = '?' . http_build_query(array_filter(['range' => $r['key'], 'from' => $r['key'] === 'custom' ? $r['from'] : null, 'to' => $r['key'] === 'custom' ? $r['to'] : null]));
$max = max(1, (int) ($items->items[0]['views'] ?? 1));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.analytics.index')) ?>">एनालिटिक्स</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($head) ?></li></ol></nav>
    <h1><i class="fa-solid <?= $icon ?> me-2"></i><?= e($head) ?> का प्रदर्शन</h1>
    <p><?= e($r['label']) ?> · कुल <?= num($sum['views']) ?> व्यू · <?= num($items->total) ?> <?= $dim === 'news' ? 'ख़बरें' : 'पंक्तियाँ' ?></p>
  </div>
  <?php if (can('analytics.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.analytics.export', ['dim' => $dim]) . $qs) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV</a><?php endif; ?>
</div>
<?= $this->insert('admin/analytics/_nav', ['active' => $dim, 'r' => $r]) ?>
<?= $this->insert('admin/analytics/_range', ['r' => $r, 'action' => route('admin.analytics.content', ['dim' => $dim])]) ?>
<section class="panel">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th style="width:48px">#</th><th><?= $dim === 'news' ? 'ख़बर' : 'नाम' ?></th><th class="text-end">व्यू</th><th class="text-end">विज़िटर</th><?php if ($dim === 'news'): ?><th class="text-end d-none d-lg-table-cell">शेयर</th><th class="text-end d-none d-lg-table-cell">कुल व्यू</th><?php endif; ?><th class="d-none d-md-table-cell" style="width:22%">हिस्सा</th></tr></thead>
    <tbody>
      <?php foreach ($items->items as $i => $x): $p = $sum['views'] ? round($x['views'] * 100 / $sum['views'], 1) : 0; ?>
        <tr>
          <td class="text-body-secondary"><?= num($items->from() + $i) ?></td>
          <td><?php if ($dim === 'news'): ?><a class="fw-semibold" href="<?= e(route('admin.analytics.news', ['id' => $x['id']])) ?>"><?= e($x['title']) ?></a><div class="small text-body-secondary"><?= e(trim(($x['category'] ?? '') . ' · ' . ($x['reporter'] ?? '') . ' · ' . ($x['published_at'] ? hindi_date($x['published_at']) : ''), ' ·')) ?></div>
            <?php else: ?><span class="fw-semibold"><?= e((string) $x['name']) ?></span><?php endif; ?></td>
          <td class="text-end fw-semibold"><?= num((int) $x['views']) ?></td>
          <td class="text-end"><?= num((int) $x['visitors']) ?></td>
          <?php if ($dim === 'news'): ?><td class="text-end d-none d-lg-table-cell"><?= num($x['shares']) ?></td><td class="text-end d-none d-lg-table-cell text-body-secondary"><?= num((int) $x['total_views']) ?></td><?php endif; ?>
          <td class="d-none d-md-table-cell"><div class="bl-bar" title="<?= e((string) $p) ?>%"><i style="width:<?= (int) round($x['views'] * 100 / $max) ?>%"></i></div><small class="text-body-secondary"><?= e((string) $p) ?>%</small></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items->items): ?><tr><td colspan="7" class="text-body-secondary p-4">इस अवधि में डेटा नहीं।</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
<?php if ($dim === 'reporter'): ?><p class="small text-body-secondary mt-2">रिपोर्टर के नाम पर उनकी ख़बरों के व्यू। कितनी ख़बरें भेजीं/मंज़ूर हुईं, यह <a href="<?= e(route('admin.reports.index')) ?>">न्यूज़रूम रिपोर्ट</a> में।</p><?php endif; ?>
