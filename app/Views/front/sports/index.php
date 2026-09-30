<?php
use App\Services\SportsService as SS;
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side listing-page">
  <div class="main-col">
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">खेल</span></nav>
      <h1 class="list-title"><i class="fa-solid fa-trophy" aria-hidden="true"></i> खेल</h1>
      <?php if ($tournaments): ?><nav class="chips" aria-label="टूर्नामेंट"><?php foreach ($tournaments as $t): ?><a href="<?= e(route('sports.tournament', ['slug' => $t['slug']])) ?>"><i class="fa-solid <?= e(SS::SPORTS[$t['sport']][1] ?? 'fa-trophy') ?>" aria-hidden="true"></i> <?= e($t['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
    </header>
    <?php if ($live): ?><section class="box"><?= block_head('लाइव') ?><div class="sc-grid" data-sc-live><?php foreach ($live as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($upcoming): ?><section class="box"><?= block_head('आने वाले मैच') ?><div class="sc-grid"><?php foreach ($upcoming as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
    <?php if ($recent): ?><section class="box"><?= block_head('हाल के नतीजे') ?><div class="sc-grid"><?php foreach ($recent as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div></section><?php endif; ?>
    <?php if (!$live && !$upcoming && !$recent): ?><div class="box empty"><p>अभी कोई मैच नहीं।</p></div><?php endif; ?>
    <?php if ($news): ?><section class="box"><?= block_head('खेल की ख़बरें') ?><div class="cgrid cols-4"><?php foreach ($news as $n): ?><?= news_card($n, 'card') ?><?php endforeach; ?></div></section><?php endif; ?>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
