<div class="box">
  <?= block_head($title ?: 'फ़ैक्ट चेक', route('factcheck.index')) ?>
  <div class="fc-grid sm"><?php foreach ($items as $f): ?><?= $this->insert('front/fact-check/_card', ['f' => $f, 'h' => 3]) ?><?php endforeach; ?></div>
</div>
