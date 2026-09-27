<?php
/**
 * ई-पेपर रीडर। बिना JS: सारे पेज नीचे-नीचे। JS के साथ: एक पेज, थंबनेल, ज़ूम, स्वाइप, फ़ुलस्क्रीन।
 * प्रीमियम के बंद पेज की इमेज HTML में नहीं आती।
 */
$this->layout('layouts/front');
$total = count($pages);
$locked = $total - min($total, $free);
?>
<div class="epr-wrap">
<section class="epr" data-epr data-page="<?= (int) $pageNo ?>" data-base="<?= e($url) ?>" aria-label="ई-पेपर रीडर">
  <header class="epr-bar">
    <form class="epr-pick" action="<?= e(route('epaper.go')) ?>" method="get" data-epr-pick>
      <select name="edition" aria-label="संस्करण"><?php foreach ($editions as $ed): ?><option value="<?= e($ed['slug']) ?>"<?= selected($ed['slug'], $edition['slug']) ?>><?= e($ed['name']) ?></option><?php endforeach; ?></select>
      <input type="date" name="date" value="<?= e($issue['issue_date']) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="तारीख़">
      <button type="submit" class="epr-btn" data-epr-go>खोलें</button>
    </form>
    <div class="epr-issue">
      <?php if ($prev): ?><a class="epr-btn" href="<?= e(route('epaper.issue', ['edition' => $edition['slug'], 'date' => $prev])) ?>" title="पिछला अंक" aria-label="पिछला अंक"><i class="fa-solid fa-backward-step"></i></a><?php endif; ?>
      <h1><?= e($edition['name']) ?> <span><?= hindi_date($issue['issue_date'], false, true) ?></span></h1>
      <?php if ($next): ?><a class="epr-btn" href="<?= e(route('epaper.issue', ['edition' => $edition['slug'], 'date' => $next])) ?>" title="अगला अंक" aria-label="अगला अंक"><i class="fa-solid fa-forward-step"></i></a><?php endif; ?>
    </div>
    <div class="epr-tools">
      <button type="button" class="epr-btn" data-epr-prev aria-label="पिछला पेज"><i class="fa-solid fa-angle-left"></i></button>
      <label class="epr-pg"><span class="visually-hidden">पेज</span><select data-epr-select aria-label="पेज चुनें"><?php foreach ($pages as $p): ?><option value="<?= (int) $p['page_no'] ?>"<?= selected($p['page_no'], $pageNo) ?>><?= (int) $p['page_no'] ?><?= $p['label'] ? ' · ' . e($p['label']) : '' ?></option><?php endforeach; ?></select> / <?= num($total) ?></label>
      <button type="button" class="epr-btn" data-epr-next aria-label="अगला पेज"><i class="fa-solid fa-angle-right"></i></button>
      <span class="epr-sep"></span>
      <button type="button" class="epr-btn" data-epr-zoom="-1" aria-label="छोटा करें"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
      <button type="button" class="epr-btn" data-epr-zoom="0" aria-label="पूरा पेज"><span data-epr-zl>100%</span></button>
      <button type="button" class="epr-btn" data-epr-zoom="1" aria-label="बड़ा करें"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
      <button type="button" class="epr-btn" data-epr-full aria-label="फ़ुलस्क्रीन"><i class="fa-solid fa-expand"></i></button>
      <?php if ($download): ?><a class="epr-btn" href="<?= e(route('epaper.pdf', ['edition' => $edition['slug'], 'date' => $issue['issue_date']])) ?>" aria-label="PDF डाउनलोड" title="PDF डाउनलोड"><i class="fa-solid fa-file-arrow-down"></i></a><?php endif; ?>
      <a class="epr-btn" href="<?= e(route('epaper.archive', ['edition' => $edition['slug']])) ?>?month=<?= e(substr($issue['issue_date'], 0, 7)) ?>" aria-label="पुराने अंक" title="पुराने अंक"><i class="fa-regular fa-calendar"></i></a>
      <button type="button" class="epr-btn" data-copy-link="<?= e($url) ?>" aria-label="लिंक कॉपी करें" title="शेयर"><i class="fa-solid fa-share-nodes"></i></button>
    </div>
  </header>
  <div class="epr-body">
    <nav class="epr-thumbs" aria-label="पेज" data-epr-thumbs>
      <?php foreach ($pages as $p): $isLocked = $p['page_no'] > $free; ?>
        <a href="<?= e($url . ($p['page_no'] > 1 ? '?page=' . $p['page_no'] : '')) ?>" data-epr-thumb="<?= (int) $p['page_no'] ?>" class="<?= $isLocked ? 'locked' : '' ?>"<?= (int) $p['page_no'] === $pageNo ? ' aria-current="page"' : '' ?>>
          <?= $isLocked ? '<span class="epr-lock"><i class="fa-solid fa-lock"></i></span>' : '<img src="' . e(upload_url($p['thumb'] ?: $p['image'])) . '" alt="" loading="lazy">' ?>
          <span><?= (int) $p['page_no'] ?><?= $p['label'] ? ' ' . e($p['label']) : '' ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
 <div class="epr-stage-wrap">
    <div class="epr-stage" data-epr-stage tabindex="0" aria-label="पेज (← → से बदलें, + − से ज़ूम)">
      <?php foreach ($pages as $p): $isLocked = $p['page_no'] > $free; $near = abs($p['page_no'] - $pageNo) <= 1; ?>
        <figure class="epr-page<?= (int) $p['page_no'] === $pageNo ? ' on' : '' ?>" data-epr-page="<?= (int) $p['page_no'] ?>" id="page-<?= (int) $p['page_no'] ?>">
          <?php if ($isLocked): ?>
            <div class="epr-locked"><i class="fa-solid fa-crown"></i><b>यह पेज प्रीमियम है</b><p>इस अंक के पहले <?= num($free) ?> पेज सबके लिए खुले हैं। पूरा ई-पेपर पढ़ने की सदस्यता जल्द शुरू होगी।</p></div>
          <?php else: ?>
            <div class="epr-sheet" style="aspect-ratio:<?= (int) ($p['width'] ?: 3) ?> / <?= (int) ($p['height'] ?: 4) ?>">
              <img <?= $near ? 'src' : 'data-src' ?>="<?= e(upload_url($p['image'])) ?>" alt="<?= e($edition['name'] . ' · ' . hindi_date($issue['issue_date']) . ' · पेज ' . $p['page_no'] . ($p['label'] ? ' (' . $p['label'] . ')' : '')) ?>" width="<?= (int) $p['width'] ?>" height="<?= (int) $p['height'] ?>" draggable="false">
              <?php foreach ($spots[(int) $p['id']] ?? [] as $h): ?>
                <a class="epr-hs" href="<?= e($h['link']) ?>" style="left:<?= (float) $h['x'] ?>%;top:<?= (float) $h['y'] ?>%;width:<?= (float) $h['w'] ?>%;height:<?= (float) $h['h'] ?>%" title="<?= e($h['title']) ?>"<?= \App\Services\EmbedService::isExternal($h['link']) ? ' target="_blank" rel="noopener nofollow"' : '' ?>><span class="visually-hidden"><?= e($h['title']) ?></span></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <figcaption class="visually-hidden">पेज <?= (int) $p['page_no'] ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
    <button type="button" class="epr-arrow prev" data-epr-prev aria-label="पिछला पेज"><i class="fa-solid fa-angle-left"></i></button>
    <button type="button" class="epr-arrow next" data-epr-next aria-label="अगला पेज"><i class="fa-solid fa-angle-right"></i></button>
 </div>
  </div>
  <?php if ($locked): ?><p class="epr-note"><i class="fa-solid fa-crown"></i> प्रीमियम अंक: <?= num($total - $locked) ?> पेज खुले, <?= num($locked) ?> पेज सदस्यों के लिए।</p><?php endif; ?>
</section>
</div>
