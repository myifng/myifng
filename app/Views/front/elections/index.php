<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side listing-page">
  <div class="main-col">
    <header class="list-head box">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">चुनाव</span></nav>
      <h1 class="list-title"><i class="fa-solid fa-check-to-slot" aria-hidden="true"></i> चुनाव केंद्र</h1>
    </header>
    <?php if ($featured): ?>
      <section class="box el-feature">
        <div class="bhead"><h2><a href="<?= e(ES::url($featured)) ?>"><?= e($featured['name']) ?></a></h2><a class="more" href="<?= e(ES::url($featured)) ?>">पूरे नतीजे <i class="fa-solid fa-angle-right"></i></a></div>
        <?= $this->insert('front/elections/_tally', ['e' => $featured, 'tally' => $tally, 'progress' => $progress]) ?>
      </section>
    <?php endif; ?>
    <div class="box el-list">
      <?php foreach ($items as $x): [$sl] = ES::STATUSES[$x['status']]; ?>
        <a class="el-item" href="<?= e(ES::url($x)) ?>">
          <span class="el-st el-st-<?= e($x['status']) ?>"><?= e($sl) ?></span>
          <b><?= e($x['name']) ?></b>
          <small><?= e(ES::TYPES[$x['type']]) ?> · <?= (int) $x['year'] ?><?= $x['state'] ? ' · ' . e($x['state']) : '' ?><?= $x['total_seats'] ? ' · ' . num((int) $x['total_seats']) . ' सीटें' : '' ?><?= $x['counting_date'] ? ' · मतगणना ' . hindi_date($x['counting_date']) : '' ?></small>
        </a>
      <?php endforeach; ?>
      <?php if (!$items): ?><p class="empty">अभी कोई चुनाव नहीं।</p><?php endif; ?>
    </div>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
