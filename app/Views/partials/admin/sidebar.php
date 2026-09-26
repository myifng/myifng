<?php
/** साइडबार: मॉड्यूल रजिस्ट्री से, सिर्फ़ तैयार और अनुमति वाले मॉड्यूल */
$phase = (int) config('app.phase', 1);
$router = app('router');
$menu = [];
foreach (config('modules.modules') as $key => $m) {
    if ($m['phase'] <= $phase && !empty($m['route']) && $router->has($m['route']) && can($key . '.view')) {
        $menu[$m['group']][$key] = $m;
    }
}
$logo = setting('logo_dark') ?: setting('logo');
$words = preg_split('/\s+/u', (string) setting('site_name', 'News'), 2);
?>
<aside class="sidebar" id="sidebar" aria-label="एडमिन मेनू">
  <div class="sidebar-brand">
    <a href="<?= e(route('admin.dashboard')) ?>" class="brand-mark">
      <?php if ($logo): ?>
        <img src="<?= e(upload_url($logo)) ?>" alt="<?= e(setting('site_name')) ?>">
      <?php else: ?>
        <span class="bm-a"><?= e($words[0]) ?></span><?php if (!empty($words[1])): ?><span class="bm-b"><?= e($words[1]) ?></span><?php endif; ?>
      <?php endif; ?>
    </a>
    <span class="brand-sub">न्यूज़रूम</span>
  </div>
  <nav class="sidebar-nav">
    <?php foreach (config('modules.groups') as $gKey => $gLabel):
        if (empty($menu[$gKey])) continue; ?>
      <div class="nav-group">
        <div class="nav-group-label"><?= e($gLabel) ?></div>
        <?php foreach ($menu[$gKey] as $key => $m):
            $active = is_route($m['route']); ?>
          <a class="nav-item<?= $active ? ' active' : '' ?>" href="<?= e(route($m['route'])) ?>"<?= $active ? ' aria-current="page"' : '' ?> data-nav-label="<?= e($m['label']) ?>">
            <i class="fa-solid <?= e($m['icon']) ?> fa-fw"></i><span><?= e($m['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    <a href="<?= e(url()) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square fa-fw"></i> वेबसाइट देखें</a>
  </div>
</aside>
