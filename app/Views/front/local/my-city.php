<?php
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side listing-page" data-mycity-page>
  <div class="main-col">
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <a href="<?= e(route('local')) ?>">लोकल</a> <span aria-hidden="true">›</span> <span aria-current="page">मेरा शहर</span></nav>
      <div class="lh-row"><h1 class="list-title"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= $city ? e($city['name']) . ': आपके शहर की ख़बरें' : 'मेरा शहर' ?></h1>
        <button type="button" class="btn-outline" data-open-mycity><i class="fa-solid fa-pen"></i> <?= $city ? 'शहर बदलें' : 'शहर चुनें' ?></button></div>
      <?php if ($city && $city['path']): ?><p class="list-desc"><a href="<?= e(url($city['path'])) ?>"><?= e($city['name']) ?> की सभी ख़बरें <i class="fa-solid fa-angle-right"></i></a></p><?php endif; ?>
    </header>
    <?php if (!$city): ?>
      <div class="box mc-pick">
        <p>अपना शहर या ज़िला चुनें। फिर होमपेज और यहाँ आपके इलाक़े की ख़बरें सबसे पहले दिखेंगी। (यह जानकारी सिर्फ़ आपके ब्राउज़र में रहती है।)</p>
        <?php if ($popularCities): ?><nav class="chips" aria-label="लोकप्रिय शहर"><?php foreach ($popularCities as $c): ?><button type="button" class="chip-btn" data-set-city="<?= (int) $c['id'] ?>" data-city-name="<?= e($c['name']) ?>"><?= e($c['name']) ?></button><?php endforeach; ?></nav><?php endif; ?>
        <button type="button" class="btn" data-open-mycity><i class="fa-solid fa-magnifying-glass"></i> शहर खोजें</button>
      </div>
    <?php else: ?>
      <?php if ($items): ?>
        <div class="box list-lead"><?= news_card($items[0], 'wide', ['h' => 'h2']) ?></div>
        <?php if (count($items) > 1): ?><div class="box listing"><?php foreach (array_slice($items, 1) as $n): ?><?= news_card($n, 'wide', ['h' => 'h2']) ?><?php endforeach; ?></div><?php endif; ?>
      <?php else: ?><div class="box empty"><p><?= e($city['name']) ?> की अभी कोई ख़बर नहीं। <a href="<?= e(route('send_news')) ?>">आप भेजें</a>।</p></div><?php endif; ?>
      <?php if ($nearbyNews): ?><section class="box"><?= block_head('आसपास की ख़बरें') ?><div class="latest cols"><?php foreach ($nearbyNews as $n): ?><?= news_card($n, 'list') ?><?php endforeach; ?></div></section><?php endif; ?>
      <?php if ($nearby): ?><section class="box"><?= block_head('आसपास के इलाक़े') ?><nav class="chips"><?php foreach ($nearby as $l): ?><a href="<?= e(url($l['path'])) ?>"><?= $l['parent'] ? '<i class="fa-solid fa-arrow-up"></i> ' : '' ?><?= e($l['name']) ?></a><?php endforeach; ?></nav></section><?php endif; ?>
    <?php endif; ?>
  </div>
  <aside class="side">
    <?php if ($popular): ?><section class="box"><?= block_head(($city['name'] ?? '') . ' में लोकप्रिय') ?><ol class="ranked"><?php foreach ($popular as $n): ?><li><?= news_card($n, 'link') ?></li><?php endforeach; ?></ol></section><?php endif; ?>
    <?php if ($reporters): ?><?= $this->insert('front/local/_reporters', ['reporters' => $reporters]) ?><?php endif; ?>
    <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
  </aside>
</div>
