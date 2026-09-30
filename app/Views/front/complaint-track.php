<?php
use App\Services\FormService;
$this->layout('layouts/front');
?>
<div class="wrap narrow">
  <div class="box auth-box">
    <h1 class="list-title"><i class="fa-solid fa-magnifying-glass"></i> शिकायत की स्थिति</h1>
    <p class="list-desc">शिकायत नंबर और शिकायत दर्ज करते समय दिया गया मोबाइल नंबर या ईमेल लिखें।</p>
    <?php if ($error): ?><div class="notice notice-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($found): [$sl, $sc] = $statuses[$found['status']] ?? [$found['status'], 'secondary']; ?>
      <div class="track-card" role="status">
        <div class="track-top"><b><?= e($found['ref_no']) ?></b><span class="track-badge s-<?= e($found['status']) ?>"><?= e($sl) ?></span></div>
        <p class="muted">दर्ज: <?= e(hindi_date($found['created_at'], true)) ?> · अपडेट: <?= e(hindi_date($found['updated_at'], true)) ?></p>
        <?php if ($found['response']): ?><div class="track-resp"><b>हमारा जवाब</b><p><?= nl2br(e($found['response'])) ?></p></div><?php endif; ?>
        <?php if ($found['resolution']): ?><div class="track-resp"><b>निपटारा</b><p><?= nl2br(e($found['resolution'])) ?></p></div><?php endif; ?>
        <?php if (!$found['response'] && !$found['resolution']): ?><p>आपकी शिकायत पर काम चल रहा है। जवाब आने पर यहाँ दिखेगा और ईमेल भी आएगा।</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= e(route('complaint.track.post')) ?>" class="fgrid mt"><?= csrf_field() ?>
      <?= ff('text', 'ref', 'शिकायत नंबर', ['required' => true, 'value' => $ref, 'attrs' => ['placeholder' => 'CMP-2026-00001', 'maxlength' => 20, 'autocapitalize' => 'characters']]) ?>
      <?= ff('text', 'contact', 'मोबाइल या ईमेल', ['required' => true, 'attrs' => ['maxlength' => 190]]) ?>
      <div class="ff-wide"><button class="btn" type="submit">स्थिति देखें</button> <a class="small-link" href="<?= e(route('complaint')) ?>">नई शिकायत दर्ज करें</a></div>
    </form>
  </div>
</div>
