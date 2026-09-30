<?php
use App\Services\FactCheckService as FC;
$this->layout('layouts/front');
?>
<div class="wrap page-wrap with-side listing-page">
  <div class="main-col">
    <header class="list-head box fc-head">
      <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">फ़ैक्ट चेक</span></nav>
      <h1 class="list-title"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> फ़ैक्ट चेक</h1>
      <p class="list-desc">वायरल दावों, तस्वीरों और वीडियो की जाँच। कुछ संदिग्ध दिखे तो <a href="<?= e(route('send_news')) ?>">हमें भेजें</a>।</p>
      <nav class="chips" aria-label="फ़ैसले से छाँटें">
        <a href="<?= e(route('factcheck.index')) ?>"<?= !$verdict ? ' aria-current="page"' : '' ?>>सभी <small><?= num(array_sum($counts)) ?></small></a>
        <?php foreach (FC::VERDICTS as $k => [$l, $cls, $ic]): if (empty($counts[$k])) continue; ?><a class="<?= e($cls) ?>" href="<?= e(route('factcheck.index')) ?>?verdict=<?= e($k) ?>"<?= $verdict === $k ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($ic) ?>" aria-hidden="true"></i> <?= e($l) ?> <small><?= num($counts[$k]) ?></small></a><?php endforeach; ?>
      </nav>
    </header>
    <?php if ($items->items): ?>
      <div class="box fc-grid"><?php foreach ($items->items as $f): ?><?= $this->insert('front/fact-check/_card', ['f' => $f]) ?><?php endforeach; ?></div>
      <?= $this->insert('partials/front/pager', ['p' => $items]) ?>
    <?php else: ?>
      <div class="box empty"><p>अभी कोई फ़ैक्ट चेक नहीं।</p></div>
    <?php endif; ?>
  </div>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
