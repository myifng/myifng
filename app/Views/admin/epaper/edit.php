<?php
use App\Models\EpaperIssue;
use App\Services\MediaService;
$this->layout('layouts/admin');
$title = $edition['name'] . ' · ' . hindi_date($issue['issue_date']);
$published = $issue['status'] === 'published';
$scheduled = $published && strtotime((string) $issue['publish_at']) > time();
$canEdit = can('epaper.edit');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.epaper.index')) ?>">ई-पेपर</a></li><li class="breadcrumb-item active" aria-current="page"><?= hindi_date($issue['issue_date']) ?></li></ol></nav>
    <h1><?= e($edition['name']) ?> · <?= hindi_date($issue['issue_date'], false, true) ?></h1>
    <p><span class="badge-status text-bg-<?= $published ? ($scheduled ? 'info' : 'success') : 'secondary' ?>"><i class="dot"></i><?= $published ? ($scheduled ? 'शेड्यूल: ' . hindi_date($issue['publish_at'], true) : 'प्रकाशित') : 'ड्राफ़्ट' ?></span>
      · <span data-ep-count><?= num($issue['page_count']) ?></span> पेज<?= $issue['access'] === 'premium' ? ' · <i class="fa-solid fa-crown text-warning"></i> प्रीमियम' : '' ?><?= $issue['title'] ? ' · ' . e($issue['title']) : '' ?></p>
  </div>
  <?php if ($published && !$scheduled): ?><a class="btn btn-outline-secondary" href="<?= e(route('epaper.issue', ['edition' => $edition['slug'], 'date' => $issue['issue_date']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>वेबसाइट पर</a><?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <?php if ($canEdit): ?>
    <section class="panel" data-ep data-page-url="<?= e(route('admin.epaper.pages.store', ['id' => $issue['id']])) ?>" data-pdf-url="<?= e(route('admin.epaper.pdf', ['id' => $issue['id']])) ?>"
      data-order-url="<?= e(route('admin.epaper.pages.order', ['id' => $issue['id']])) ?>" data-pdfjs="<?= e(asset('vendor/pdfjs/pdf.min.js')) ?>" data-pdfjs-worker="<?= e(asset('vendor/pdfjs/pdf.worker.min.js')) ?>"
      data-max-pdf="<?= (int) $maxPdf ?>">
      <div class="panel-head"><h2><i class="fa-solid fa-file-pdf me-2 text-danger"></i>पेज बनाएँ</h2></div>
      <div class="panel-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="ep-drop" data-ep-pdf-zone>
              <i class="fa-solid fa-file-pdf"></i><b>PDF चुनें या यहाँ छोड़ें</b>
              <small>हर पेज अपने आप इमेज बनेगा (आपके ब्राउज़र में)। अधिकतम <?= num($maxPdf) ?> MB</small>
              <input type="file" accept="application/pdf,.pdf" hidden data-ep-pdf>
            </label>
            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="epKeepPdf" data-ep-keep checked><label class="form-check-label small" for="epKeepPdf">PDF भी सेव करें (पाठक डाउनलोड कर सकें)</label></div>
          </div>
          <div class="col-md-6">
            <label class="ep-drop alt">
              <i class="fa-regular fa-images"></i><b>या पेज की इमेज (JPG/PNG)</b>
              <small>कई एक साथ चुनें; फ़ाइल के नाम के क्रम में जुड़ेंगी</small>
              <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-ep-images>
            </label>
          </div>
        </div>
        <div class="ep-progress mt-3" data-ep-progress hidden>
          <div class="progress" role="progressbar" aria-label="प्रगति"><div class="progress-bar progress-bar-striped progress-bar-animated" data-ep-bar style="width:0%"></div></div>
          <p class="small mt-1 mb-0" data-ep-status role="status" aria-live="polite"></p>
        </div>
        <?php if ($pages): ?><p class="small text-body-secondary mt-2 mb-0"><i class="fa-solid fa-circle-info me-1"></i>नए पेज आख़िर में जुड़ेंगे। पूरा अंक बदलना हो तो पहले “सारे पेज हटाएँ”।</p><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="panel mt-3">
      <div class="panel-head"><h2>पेज</h2>
        <?php if ($canEdit && $pages): ?><form method="post" action="<?= e(route('admin.epaper.pages.clear', ['id' => $issue['id']])) ?>" data-confirm="सारे पेज और उनके हॉटस्पॉट हट जाएँगे; अंक ड्राफ़्ट हो जाएगा।"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit">सारे पेज हटाएँ</button></form><?php endif; ?>
      </div>
      <div class="panel-body">
        <ol class="ep-pages" data-ep-pages>
          <?php foreach ($pages as $p): ?>
            <li class="ep-page" data-id="<?= (int) $p['id'] ?>">
              <a class="ep-thumb" href="<?= e(upload_url($p['image'])) ?>" target="_blank" rel="noopener"><img src="<?= e(upload_url($p['thumb'] ?: $p['image'])) ?>" alt="पेज <?= (int) $p['page_no'] ?>" loading="lazy"><span class="ep-no"><?= (int) $p['page_no'] ?></span></a>
              <?php if ($canEdit): ?>
                <input class="form-control form-control-sm" value="<?= e($p['label']) ?>" placeholder="पेज का नाम" maxlength="100" aria-label="पेज <?= (int) $p['page_no'] ?> का नाम" data-ep-label="<?= e(route('admin.epaper.pages.update', ['id' => $issue['id'], 'pid' => $p['id']])) ?>">
                <div class="ep-tools">
                  <button type="button" class="btn btn-sm btn-icon btn-light" data-ep-move="-1" aria-label="पहले"><i class="fa-solid fa-arrow-left"></i></button>
                  <button type="button" class="btn btn-sm btn-icon btn-light" data-ep-move="1" aria-label="बाद में"><i class="fa-solid fa-arrow-right"></i></button>
                  <a class="btn btn-sm btn-light" href="<?= e(route('admin.epaper.hotspots', ['id' => $issue['id'], 'pid' => $p['id']])) ?>" title="हॉटस्पॉट: पेज के हिस्से को ख़बर से जोड़ें"><i class="fa-solid fa-vector-square"></i> <?= num($p['hotspots']) ?></a>
                  <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-ep-delete="<?= e(route('admin.epaper.pages.destroy', ['id' => $issue['id'], 'pid' => $p['id']])) ?>" aria-label="पेज हटाएँ"><i class="fa-solid fa-trash"></i></button>
                </div>
              <?php elseif ($p['label']): ?><span class="small"><?= e($p['label']) ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="empty-state py-3" data-ep-empty<?= $pages ? ' hidden' : '' ?>><i class="fa-regular fa-file"></i><p class="mb-0">अभी कोई पेज नहीं। ऊपर से PDF चुनें।</p></div>
        <template data-ep-template>
          <li class="ep-page" data-id="">
            <a class="ep-thumb" href="#" target="_blank" rel="noopener"><img src="" alt=""><span class="ep-no"></span></a>
            <input class="form-control form-control-sm" placeholder="पेज का नाम" maxlength="100" aria-label="पेज का नाम" data-ep-label="">
            <div class="ep-tools">
              <button type="button" class="btn btn-sm btn-icon btn-light" data-ep-move="-1" aria-label="पहले"><i class="fa-solid fa-arrow-left"></i></button>
              <button type="button" class="btn btn-sm btn-icon btn-light" data-ep-move="1" aria-label="बाद में"><i class="fa-solid fa-arrow-right"></i></button>
              <a class="btn btn-sm btn-light" href="#" data-ep-hs><i class="fa-solid fa-vector-square"></i> 0</a>
              <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-ep-delete="" aria-label="पेज हटाएँ"><i class="fa-solid fa-trash"></i></button>
            </div>
          </li>
        </template>
      </div>
    </section>
  </div>

  <div class="col-xl-4">
    <section class="panel sticky-xl">
      <div class="panel-head"><h2>अंक की जानकारी</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e(route('admin.epaper.update', ['id' => $issue['id']])) ?>" novalidate data-unsaved>
          <?= csrf_field() ?><?= method_field('PUT') ?>
          <?= field('select', 'edition_id', 'संस्करण', $issue['edition_id'], ['options' => $editions]) ?>
          <?= field('date', 'issue_date', 'तारीख़', $issue['issue_date']) ?>
          <?= field('text', 'title', 'शीर्षक (वैकल्पिक)', $issue['title'] ?? '', ['attrs' => ['maxlength' => 190]]) ?>
          <?= field('select', 'access', 'पहुँच', $issue['access'], ['options' => EpaperIssue::ACCESS]) ?>
          <?php if (can('epaper.publish')): ?>
            <?= field('select', 'status', 'स्थिति', $issue['status'], ['options' => ['draft' => 'ड्राफ़्ट', 'published' => 'प्रकाशित']]) ?>
            <?= field('datetime-local', 'publish_at', 'प्रकाशन का समय', $issue['publish_at'] ? date('Y-m-d\TH:i', strtotime($issue['publish_at'])) : '', ['help' => 'ख़ाली = अभी; जैसे सुबह 5 बजे के लिए पहले से शेड्यूल करें']) ?>
          <?php else: ?>
            <input type="hidden" name="status" value="<?= e($issue['status']) ?>">
            <p class="small text-body-secondary">प्रकाशित करने की अनुमति संपादक के पास है।</p>
          <?php endif; ?>
          <?php if ($canEdit): ?><button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><?php endif; ?>
        </form>
        <hr>
        <h3 class="h6">PDF</h3>
        <?php if ($issue['pdf']): ?>
          <p class="small mb-2"><a href="<?= e(upload_url($issue['pdf'])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf text-danger me-1"></i>PDF देखें</a> · <?= e(MediaService::humanSize((int) $issue['pdf_size'])) ?></p>
          <?php if ($canEdit): ?><form method="post" action="<?= e(route('admin.epaper.pdf.destroy', ['id' => $issue['id']])) ?>" data-confirm="PDF हटेगी; पेज बने रहेंगे।"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="btn btn-sm btn-outline-danger" type="submit">PDF हटाएँ</button></form><?php endif; ?>
        <?php else: ?><p class="small text-body-secondary mb-0">PDF सेव नहीं है (पाठक डाउनलोड नहीं कर पाएँगे)।</p><?php endif; ?>
        <p class="small text-body-secondary mt-3 mb-0">सर्वर की अपलोड सीमा: <?= num($serverMb) ?> MB (php.ini)।</p>
      </div>
    </section>
    <?php if (can('epaper.delete')): ?>
      <div class="danger-zone mt-3">
        <div><b>अंक हटाएँ</b><p class="mb-0 small">सारे पेज, हॉटस्पॉट और PDF हमेशा के लिए हट जाएँगे।</p></div>
        <?= delete_button(route('admin.epaper.destroy', ['id' => $issue['id']]), 'यह अंक और उसकी सारी फ़ाइलें हमेशा के लिए हट जाएँगी।', 'हटाएँ', 'btn btn-outline-danger') ?>
      </div>
    <?php endif; ?>
  </div>
</div>
