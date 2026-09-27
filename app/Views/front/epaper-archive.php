<?php
$this->layout('layouts/front');
$first = strtotime($month . '-01');
$days = (int) date('t', $first);
$lead = (int) date('N', $first) - 1; // सोमवार से
$today = date('Y-m-d');
?>
<div class="wrap page-wrap">
  <header class="list-head box">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <a href="<?= e(route('epaper')) ?>">ई-पेपर</a> <span aria-hidden="true">›</span> <span aria-current="page">आर्काइव</span></nav>
    <h1 class="list-title"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e($edition['name']) ?>: पुराने अंक</h1>
    <?php if (count($editions) > 1): ?><nav class="chips" aria-label="संस्करण"><?php foreach ($editions as $ed): ?><a href="<?= e(route('epaper.archive', ['edition' => $ed['slug']])) ?>?month=<?= e($month) ?>"<?= $ed['slug'] === $edition['slug'] ? ' aria-current="page"' : '' ?>><?= e($ed['name']) ?></a><?php endforeach; ?></nav><?php endif; ?>
  </header>
  <section class="box">
    <div class="epa-head">
      <?= $prevMonth ? '<a class="btn-outline" href="?month=' . e($prevMonth) . '"><i class="fa-solid fa-angle-left"></i> पिछला महीना</a>' : '<span></span>' ?>
      <h2><?= e(explode(' ', hindi_date($month . '-01'), 2)[1] ?? $month) ?></h2>
      <?= $nextMonth ? '<a class="btn-outline" href="?month=' . e($nextMonth) . '">अगला महीना <i class="fa-solid fa-angle-right"></i></a>' : '<span></span>' ?>
    </div>
    <div class="epa-cal" role="grid" aria-label="कैलेंडर">
      <?php foreach (['सोम', 'मंगल', 'बुध', 'गुरु', 'शुक्र', 'शनि', 'रवि'] as $d): ?><span class="epa-dow" role="columnheader"><?= e($d) ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < $lead; $i++): ?><span class="epa-day empty"></span><?php endfor; ?>
      <?php for ($d = 1; $d <= $days; $d++): $date = sprintf('%s-%02d', $month, $d); $iss = $byDate[$date] ?? null; ?>
        <?php if ($iss): ?>
          <a class="epa-day has" href="<?= e(route('epaper.issue', ['edition' => $edition['slug'], 'date' => $date])) ?>" aria-label="<?= e(hindi_date($date)) ?> का ई-पेपर">
            <?= $iss['cover'] ? '<img src="' . e(upload_url($iss['cover'])) . '" alt="" loading="lazy">' : '' ?><b><?= $d ?></b><?= $iss['access'] === 'premium' ? '<i class="fa-solid fa-crown"></i>' : '' ?></a>
        <?php else: ?><span class="epa-day<?= $date === $today ? ' today' : '' ?>"><b><?= $d ?></b></span><?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php if (!$byDate): ?><p class="small-note">इस महीने का कोई अंक नहीं है।</p><?php endif; ?>
  </section>
</div>
