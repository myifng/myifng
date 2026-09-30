<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box msg-box">
    <i class="fa-regular fa-envelope msg-icon" aria-hidden="true"></i>
    <h1 class="list-title">न्यूज़लेटर से हटें?</h1>
    <?php if ($s['status'] === 'unsubscribed'): ?>
      <p><b><?= e($s['email']) ?></b> पहले ही अनसब्सक्राइब है।</p><a class="btn" href="<?= e(url()) ?>">होम पेज</a>
    <?php else: ?>
      <p><b><?= e($s['email']) ?></b> पर अब न्यूज़लेटर नहीं भेजा जाएगा।</p>
      <form method="post" action="<?= e(route('newsletter.unsubscribe.post', ['token' => $token])) ?>"><?= csrf_field() ?><button class="btn" type="submit">हाँ, अनसब्सक्राइब करें</button></form>
    <?php endif; ?>
  </div>
</div>
