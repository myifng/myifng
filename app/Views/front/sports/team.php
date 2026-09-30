<?php
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side">
  <article class="box article">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <h1 class="page-title"><span class="sc-logo lg"><?= $t['logo'] ? media_img($t['logo'], 'thumb', $t['name']) : '<span style="background:' . e($t['color']) . '">' . e(mb_substr($t['name'], 0, 1)) . '</span>' ?></span> <?= e($t['name']) ?></h1>
    <?php if ($upcoming): ?><section><h2>आने वाले / लाइव मैच</h2><div class="sc-grid"><?php foreach ($upcoming as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($recent): ?><section><h2>हाल के नतीजे</h2><div class="sc-grid"><?php foreach ($recent as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($players): ?><section class="sp-squads"><h2>खिलाड़ी</h2><ul class="sp-players"><?php foreach ($players as $p): ?><li><?= $p['jersey'] ? '<span class="sp-j">' . e($p['jersey']) . '</span>' : '' ?><b><?= e($p['name']) ?></b><?= $p['is_captain'] ? ' <small>(कप्तान)</small>' : '' ?><?= $p['role'] ? '<small class="muted">' . e($p['role']) . '</small>' : '' ?></li><?php endforeach; ?></ul></section><?php endif; ?>
    <?php if (!$upcoming && !$recent && !$players): ?><p class="empty">अभी कोई जानकारी नहीं।</p><?php endif; ?>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
