<?php $this->layout('layouts/front'); ?>
<div class="wrap narrow">
  <div class="box auth-box msg-box">
    <i class="fa-solid fa-circle-check msg-icon" aria-hidden="true"></i>
    <h1 class="list-title">धन्यवाद!</h1>
    <p><?= e((string) ($d['message'] ?: 'आपका फ़ॉर्म मिल गया।')) ?></p>
    <?php if ($d['ref']): ?><p class="ref-box">संदर्भ नंबर<b><?= e($d['ref']) ?></b><small>इसे संभालकर रखें<?= $d['type'] === 'complaint' ? '; इससे शिकायत की स्थिति देख सकते हैं' : '' ?>।</small></p><?php endif; ?>
    <div class="auth-actions" style="justify-content:center">
      <?php if ($d['type'] === 'complaint' && setting('complaint_tracking', '1') === '1'): ?><a class="btn btn-light" href="<?= e(route('complaint.track')) ?>?ref=<?= e($d['ref']) ?>">स्थिति देखें</a><?php endif; ?>
      <a class="btn" href="<?= e(url()) ?>">होम पेज पर जाएँ</a>
    </div>
  </div>
</div>
