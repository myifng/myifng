<?php
$this->layout('layouts/admin');
$title = 'हॉटस्पॉट · पेज ' . $page['page_no'];
$json = json_encode(array_map(static fn($s) => ['x' => (float) $s['x'], 'y' => (float) $s['y'], 'w' => (float) $s['w'], 'h' => (float) $s['h'],
    'news_id' => $s['news_id'] ? (int) $s['news_id'] : null, 'news_title' => (string) $s['news_title'], 'url' => (string) $s['url'], 'label' => (string) $s['label']], $spots),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.epaper.index')) ?>">ई-पेपर</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.epaper.edit', ['id' => $issue['id']])) ?>"><?= hindi_date($issue['issue_date']) ?></a></li><li class="breadcrumb-item active" aria-current="page">पेज <?= (int) $page['page_no'] ?></li></ol></nav>
    <h1>हॉटस्पॉट: पेज <?= (int) $page['page_no'] ?><?= $page['label'] ? ' · ' . e($page['label']) : '' ?></h1>
    <p>पेज पर माउस/उँगली से खींचकर किसी लेख के चारों ओर आयत बनाएँ, फिर उसे वेबसाइट की ख़बर या लिंक से जोड़ें। पाठक उस हिस्से पर टैप करेगा तो ख़बर खुलेगी।</p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($nav['prev']): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.epaper.hotspots', ['id' => $issue['id'], 'pid' => $nav['prev']])) ?>"><i class="fa-solid fa-arrow-left me-1"></i>पिछला पेज</a><?php endif; ?>
    <?php if ($nav['next']): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.epaper.hotspots', ['id' => $issue['id'], 'pid' => $nav['next']])) ?>">अगला पेज<i class="fa-solid fa-arrow-right ms-1"></i></a><?php endif; ?>
  </div>
</div>
<div class="row g-3" data-hs data-save="<?= e(route('admin.epaper.hotspots.save', ['id' => $issue['id'], 'pid' => $page['id']])) ?>" data-search="<?= e(route('admin.news.search')) ?>" data-spots="<?= e($json) ?>">
  <div class="col-xl-7">
    <section class="panel"><div class="panel-body">
      <div class="hs-stage" data-hs-stage>
        <img src="<?= e(upload_url($page['image'])) ?>" alt="पेज <?= (int) $page['page_no'] ?>" draggable="false">
      </div>
    </div></section>
  </div>
  <div class="col-xl-5">
    <section class="panel sticky-xl">
      <div class="panel-head"><h2>हॉटस्पॉट <span class="badge text-bg-light" data-hs-count>0</span></h2><button type="button" class="btn btn-sm btn-brand" data-hs-save><i class="fa-solid fa-floppy-disk me-1"></i>सेव करें</button></div>
      <div class="panel-body">
        <ol class="hs-list" data-hs-list></ol>
        <p class="small text-body-secondary mb-0" data-hs-empty>अभी कोई हॉटस्पॉट नहीं। बाईं ओर पेज पर खींचकर बनाएँ।</p>
        <p class="small mt-2 mb-0" data-hs-status role="status" aria-live="polite"></p>
      </div>
    </section>
  </div>
</div>
