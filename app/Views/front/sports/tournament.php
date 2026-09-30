<?php
use App\Services\SportsService as SS;
$this->layout('layouts/front');
$cricket = $t['sport'] === 'cricket';
?>
<div class="wrap page-wrap sp-page">
  <header class="box">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <h1 class="page-title"><?= e($t['name']) ?> <?= $t['season'] ? '<small class="el-sub">' . e($t['season']) . '</small>' : '' ?></h1>
    <p class="el-meta"><span class="el-st"><?= e(SS::TOUR_STATUSES[$t['status']]) ?></span> <i class="fa-solid <?= e(SS::SPORTS[$t['sport']][1]) ?>"></i> <?= e(SS::SPORTS[$t['sport']][0]) ?><?= $t['start_date'] ? ' · ' . hindi_date($t['start_date']) . ($t['end_date'] ? ' – ' . hindi_date($t['end_date']) : '') : '' ?></p>
    <?php if ($t['description']): ?><p><?= e($t['description']) ?></p><?php endif; ?>
  </header>
  <?php if ($live): ?><section class="box"><?= block_head('लाइव') ?><div class="sc-grid"><?php foreach ($live as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($groups): ?><section class="box"><?= block_head('पॉइंट्स टेबल') ?>
    <?php foreach ($groups as $g => $rows): ?><?php if ($g !== ''): ?><h3 class="pt-group">ग्रुप <?= e($g) ?></h3><?php endif; ?><?= $this->insert('front/sports/_table', ['rows' => $rows, 'cricket' => $cricket]) ?><?php endforeach; ?>
    <?php if ($cricket): ?><p class="el-note">NRR = नेट रन रेट</p><?php endif; ?></section><?php endif; ?>
  <?php if ($upcoming): ?><section class="box"><?= block_head('शेड्यूल') ?><div class="sc-grid"><?php foreach ($upcoming as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($recent): ?><section class="box"><?= block_head('नतीजे') ?><div class="sc-grid"><?php foreach ($recent as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($teams): ?><section class="box"><?= block_head('टीमें') ?><nav class="chips"><?php foreach ($teams as $tm): ?><a href="<?= e(route('sports.team', ['slug' => $tm['slug']])) ?>"><span class="party-dot" style="background:<?= e($tm['color']) ?>"></span> <?= e($tm['name']) ?></a><?php endforeach; ?></nav></section><?php endif; ?>
  <?php if ($news): ?><section class="box"><?= block_head('ख़बरें') ?><div class="cgrid cols-3"><?php foreach ($news as $n): ?><?= news_card($n, 'card') ?><?php endforeach; ?></div></section><?php endif; ?>
</div>
