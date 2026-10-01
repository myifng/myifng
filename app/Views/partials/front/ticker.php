<?php
/** ब्रेकिंग/ताज़ा पट्टी + ट्रेंडिंग पट्टी (सेटिंग → कंटेंट फ़ीचर) */
use App\Services\BreakingService;
use App\Services\NewsQuery;
if (!app('router')->has('news.show')) return;
$t = setting('breaking_ticker', '1') === '1' ? BreakingService::ticker(12) : ['items' => []];
$trend = setting('trending_bar', '1') === '1' ? NewsQuery::trending(10) : [];
$speed = ['slow' => 70, 'medium' => 45, 'fast' => 28][setting('ticker_speed', 'medium')] ?? 45;
?>
<?php if ($t['items']): ?>
<div class="ticker<?= $t['breaking'] ? ' is-breaking' : '' ?>" role="region" aria-label="<?= $t['breaking'] ? 'ब्रेकिंग न्यूज़ पट्टी' : 'ताज़ा ख़बरों की पट्टी' ?>">
  <div class="wrap">
    <span class="ticker-label"><?= $t['breaking'] ? 'ब्रेकिंग' : 'ताज़ा' ?></span>
    <div class="ticker-track"><div class="ticker-run" style="animation-duration:<?= (int) $speed ?>s">
      <?php foreach ($t['items'] as $n):
        $cls = trim(($n['priority'] >= 3 ? 'urgent ' : '') . ($n['type'] === 'flash' ? 'flash' : ''));
        $tag = $n['url'] ? 'a' : 'span'; ?>
        <<?= $tag ?><?= $n['url'] ? ' href="' . e($n['url']) . '"' : '' ?><?= $cls ? ' class="' . $cls . '"' : '' ?>><?= $n['type'] === 'flash' ? '<b>फ़्लैश:</b> ' : '' ?><?= e($n['title']) ?></<?= $tag ?>>
      <?php endforeach; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>
<?php if ($trend): ?>
<nav class="trending" aria-label="ट्रेंडिंग">
  <div class="wrap"><b><i class="fa-solid fa-fire" aria-hidden="true"></i> ट्रेंडिंग</b>
    <?php foreach ($trend as $x): ?><a href="<?= e(route($x['kind'], ['slug' => $x['slug']])) ?>"><?= $x['kind'] === 'tag' ? '#' : '' ?><?= e($x['name']) ?></a><?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>
