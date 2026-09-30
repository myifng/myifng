<?php
$this->layout('layouts/front');
$q = static fn(array $o) => route('trending') . '?' . http_build_query(array_filter($o + ['tab' => $tab, 'days' => $days !== '7' ? $days : null, 'cat' => $cat, 'loc' => $loc], static fn($v) => $v !== null && $v !== '' && $v !== 'trending'));
?>
<div class="wrap page-wrap with-side listing-page trending-page">
  <div class="main-col">
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page"><?= e(\App\Controllers\Front\TrendingController::TABS[$tab][0]) ?></span></nav>
      <h1 class="list-title"><i class="fa-solid <?= e(\App\Controllers\Front\TrendingController::TABS[$tab][1]) ?>" aria-hidden="true"></i> <?= e($heading) ?></h1>
      <nav class="chips" aria-label="सूची">
        <?php foreach (\App\Controllers\Front\TrendingController::TABS as $k => [$label, $ic]): ?><a href="<?= e($q(['tab' => $k])) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($ic) ?>" aria-hidden="true"></i> <?= e($label) ?></a><?php endforeach; ?>
      </nav>
      <form class="tr-filter" method="get" action="<?= e(route('trending')) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <?php if ($tab !== 'trending'): ?>
          <select name="days" aria-label="अवधि"><?php foreach (\App\Controllers\Front\TrendingController::DAYS as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $days) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <select name="cat" aria-label="श्रेणी"><option value="">सभी श्रेणियाँ</option><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $cat) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <select name="loc" aria-label="शहर"><option value="">सभी शहर</option><?php foreach ($cities as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $loc) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <button class="btn" type="submit">दिखाएँ</button>
      </form>
      <?php if ($tags): ?><nav class="chips tr-tags" aria-label="ट्रेंडिंग विषय"><?php foreach ($tags as $t): ?><a href="<?= e(route($t['kind'], ['slug' => $t['slug']])) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
    </header>
    <?php if ($items): ?>
      <ol class="box ranked-list">
        <?php foreach ($items as $i => $n): ?>
          <li><span class="rk" aria-hidden="true"><?= num($i + 1) ?></span><?= news_card($n, 'wide', ['h' => 'h2']) ?>
            <?php if (!empty($n['pinned'])): ?><span class="rk-note"><i class="fa-solid fa-thumbtack"></i> संपादक की पसंद</span><?php elseif (isset($n['share_count'])): ?><span class="rk-note"><i class="fa-solid fa-share-nodes"></i> <?= num($n['share_count']) ?> शेयर</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="box empty"><p><?= $tab === 'shared' ? 'इस अवधि में अभी शेयर का डेटा नहीं है।' : 'अभी यहाँ कोई ख़बर नहीं है।' ?></p><a class="more" href="<?= e(route('latest')) ?>">ताज़ा ख़बरें पढ़ें <i class="fa-solid fa-angle-right"></i></a></div>
    <?php endif; ?>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
