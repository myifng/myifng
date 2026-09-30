<div class="box">
  <?= block_head($title ?: 'खेल: स्कोर', route('sports.index')) ?>
  <div class="sc-strip"><?php foreach ($items as $m): ?><?= $this->insert('front/sports/_card', ['m' => $m]) ?><?php endforeach; ?></div>
</div>
