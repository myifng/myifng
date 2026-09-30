<?php
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side listing-page">
  <div class="main-col">
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">लोकल न्यूज़</span></nav>
      <div class="lh-row"><h1 class="list-title"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> लोकल न्यूज़<?= $state ? ': ' . e($state['name']) : '' ?></h1>
        <?php if ($city): ?><a class="btn-outline" href="<?= e(route('my_city')) ?>"><i class="fa-solid fa-location-dot"></i> मेरा शहर: <?= e($city['name']) ?></a><?php else: ?><button type="button" class="btn-outline" data-open-mycity><i class="fa-solid fa-location-dot"></i> अपना शहर चुनें</button><?php endif; ?></div>
      <form class="tr-filter" method="get" action="<?= e(route('local')) ?>">
        <select name="state" aria-label="राज्य" onchange="this.form.submit()"><?php foreach ($states as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], $state['id'] ?? '') ?>><?= e($s['name']) ?><?= $s['count'] ? ' (' . num($s['count']) . ')' : '' ?></option><?php endforeach; ?></select>
        <noscript><button class="btn" type="submit">दिखाएँ</button></noscript>
      </form>
      <?php if ($state): ?><p class="list-desc"><a href="<?= e(url($state['path'])) ?>"><?= e($state['name']) ?> की सभी ख़बरें <i class="fa-solid fa-angle-right"></i></a> · पिछले 7 दिन में <?= num($state['count']) ?> ख़बरें</p><?php endif; ?>
    </header>
    <?php if ($districts): ?>
      <div class="box loc-dir">
        <?php foreach ($districts as $d): ?>
          <div class="loc-card<?= $d['count'] ? '' : ' quiet' ?>">
            <a class="lc-name" href="<?= e(url($d['path'])) ?>"><b><?= e($d['name']) ?></b><span><?= $d['count'] ? num($d['count']) . ' ख़बरें' : 'कोई नई ख़बर नहीं' ?></span></a>
            <?php if ($d['latest']): ?><a class="lc-latest" href="<?= e(\App\Services\NewsService::url($d['latest'])) ?>"><?= e($d['latest']['title']) ?> <time><?= time_ago($d['latest']['published_at']) ?></time></a><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="box empty"><p>इस राज्य के ज़िले अभी नहीं जुड़े।</p></div><?php endif; ?>
    <p class="el-note"><i class="fa-solid fa-camera-retro"></i> आपके इलाक़े में कुछ हुआ? <a href="<?= e(route('send_news')) ?>">हमें ख़बर भेजें</a>।</p>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
