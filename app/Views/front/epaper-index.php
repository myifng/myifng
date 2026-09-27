<?php $this->layout('layouts/front'); $empty ??= null; ?>
<div class="wrap page-wrap">
  <header class="list-head box">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><a href="<?= e(url()) ?>">होम</a> <span aria-hidden="true">›</span> <span aria-current="page">ई-पेपर</span></nav>
    <h1 class="list-title"><i class="fa-solid fa-book-open" aria-hidden="true"></i> ई-पेपर</h1>
    <p class="list-desc">अख़बार जैसा पढ़ें: अपना संस्करण चुनें।</p>
  </header>
  <?php if ($cards): ?>
    <section class="box"><div class="epi-grid">
      <?php foreach ($cards as ['edition' => $e, 'issue' => $i]): ?>
        <a class="epi-card" href="<?= e(\App\Services\EpaperService::url($e, $i)) ?>">
          <span class="epi-cover"><?= $i['cover'] ? '<img src="' . e(upload_url($i['cover'])) . '" alt="' . e($e['name']) . ' ई-पेपर" loading="lazy">' : '' ?></span>
          <b><?= e($e['name']) ?></b><span><?= hindi_date($i['issue_date']) ?> · <?= num($i['page_count']) ?> पेज</span>
        </a>
      <?php endforeach; ?>
    </div></section>
  <?php else: ?>
    <div class="box empty"><p><?= $empty ? e($empty['name']) . ' का कोई अंक अभी उपलब्ध नहीं है।' : 'ई-पेपर जल्द उपलब्ध होगा।' ?></p><a class="more" href="<?= e(url()) ?>">होमपेज पर जाएँ <i class="fa-solid fa-angle-right"></i></a></div>
  <?php endif; ?>
</div>
