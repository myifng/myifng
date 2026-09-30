<?php /** एनालिटिक्स के टैब + अवधि; $active, $r (अवधि, वैकल्पिक) */
$qs = isset($r) ? '?' . http_build_query(array_filter(['range' => $r['key'], 'from' => $r['key'] === 'custom' ? $r['from'] : null, 'to' => $r['key'] === 'custom' ? $r['to'] : null])) : '';
?>
<nav class="sub-tabs mb-3" aria-label="एनालिटिक्स के हिस्से">
  <a class="<?= $active === 'index' ? 'active' : '' ?>"<?= $active === 'index' ? ' aria-current="page"' : '' ?> href="<?= e(route('admin.analytics.index') . $qs) ?>"><i class="fa-solid fa-gauge me-1"></i>ओवरव्यू</a>
  <?php foreach (['news', 'category', 'location', 'reporter', 'item'] as $d): [$l, $ic] = \App\Controllers\Admin\AnalyticsController::DIMS[$d]; ?>
    <a class="<?= $active === $d ? 'active' : '' ?>"<?= $active === $d ? ' aria-current="page"' : '' ?> href="<?= e(route('admin.analytics.content', ['dim' => $d]) . $qs) ?>"><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></a>
  <?php endforeach; ?>
  <a class="<?= $active === 'trending' ? 'active' : '' ?>"<?= $active === 'trending' ? ' aria-current="page"' : '' ?> href="<?= e(route('admin.analytics.trending')) ?>"><i class="fa-solid fa-fire me-1"></i>ट्रेंडिंग</a>
  <?php if (can('reports.view')): ?><a href="<?= e(route('admin.reports.index')) ?>"><i class="fa-solid fa-chart-pie me-1"></i>न्यूज़रूम रिपोर्ट</a><?php endif; ?>
</nav>
