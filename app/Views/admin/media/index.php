<?php
use App\Models\Media;
use App\Services\MediaService;
$this->layout('layouts/admin');
$title = 'मीडिया लाइब्रेरी';
$trash = $filters['trash'];
$q = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['kind' => $filters['kind'], 'folder' => $filters['folder'], 'month' => $filters['month'], 'q' => $filters['q'], 'view' => $trash ? 'trash' : ''], $over)));
$monthNames = [1 => 'जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितंबर', 'अक्टूबर', 'नवंबर', 'दिसंबर'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">मीडिया लाइब्रेरी</li></ol></nav>
    <h1>मीडिया लाइब्रेरी</h1>
    <p><?= num($counts['alls']) ?> फ़ाइलें · <?= e(MediaService::humanSize($counts['bytes'])) ?> · मूल फ़ाइल सुरक्षित रहती है, वेबसाइट पर छोटे/WebP आकार दिखते हैं</p>
  </div>
  <?php if (can('media.create') && !$trash): ?><button class="btn btn-brand" type="button" data-bs-toggle="collapse" data-bs-target="#uploadBox" aria-expanded="false" aria-controls="uploadBox"><i class="fa-solid fa-cloud-arrow-up me-1"></i> अपलोड</button><?php endif; ?>
</div>

<?php if (can('media.create') && !$trash): ?>
<div class="collapse<?= $counts['alls'] === 0 ? ' show' : '' ?>" id="uploadBox">
  <section class="panel mb-3">
    <div class="dropzone" data-uploader data-url="<?= e(route('admin.media.store')) ?>" data-folder="<?= (int) $filters['folder'] ?>">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <p class="mb-1"><b>फ़ाइलें यहाँ खींचकर छोड़ें</b> या</p>
      <label class="btn btn-dark btn-sm">फ़ाइलें चुनें<input type="file" multiple hidden accept="<?= e(MediaService::accept()) ?>" data-uploader-input></label>
      <p class="small text-body-secondary mt-2 mb-0">इमेज (JPG, PNG, WebP, GIF) <?= MediaService::MAX_MB['image'] ?> MB · वीडियो (MP4, WebM) <?= MediaService::MAX_MB['video'] ?> MB · ऑडियो (MP3, M4A) · दस्तावेज़ (PDF, DOCX, XLSX) <?= MediaService::MAX_MB['document'] ?> MB · सर्वर की सीमा <?= e(ini_get('upload_max_filesize')) ?></p>
      <ul class="upload-queue" data-uploader-queue aria-live="polite"></ul>
    </div>
  </section>
</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-xl-3 order-2 order-xl-1">
    <section class="panel">
      <div class="panel-head"><h2>फ़ोल्डर</h2></div>
      <nav class="folder-list">
        <a href="<?= e($q(['folder' => ''])) ?>" class="<?= $filters['folder'] === '' ? 'active' : '' ?>"><i class="fa-solid fa-photo-film fa-fw"></i> सभी फ़ाइलें</a>
        <a href="<?= e($q(['folder' => 'none'])) ?>" class="<?= $filters['folder'] === 'none' ? 'active' : '' ?>"><i class="fa-regular fa-folder fa-fw"></i> बिना फ़ोल्डर</a>
        <?php foreach ($folders as $f): ?>
          <a href="<?= e($q(['folder' => $f['id']])) ?>" class="<?= $filters['folder'] === (string) $f['id'] ? 'active' : '' ?>"><i class="fa-solid fa-folder fa-fw"></i> <?= e($f['name']) ?> <span><?= num($f['files']) ?></span></a>
        <?php endforeach; ?>
        <a href="<?= e(route('admin.media.index')) ?>?view=trash" class="<?= $trash ? 'active' : '' ?>"><i class="fa-regular fa-trash-can fa-fw"></i> ट्रैश <span><?= num($counts['trash']) ?></span></a>
      </nav>
      <?php if (can('media.manage')): ?>
        <form class="panel-body border-top d-flex gap-2" method="post" action="<?= e(route('admin.media.folders.store')) ?>">
          <?= csrf_field() ?>
          <input class="form-control form-control-sm" name="name" maxlength="100" placeholder="नया फ़ोल्डर" aria-label="नए फ़ोल्डर का नाम" required>
          <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="फ़ोल्डर बनाएँ"><i class="fa-solid fa-folder-plus"></i></button>
        </form>
        <?php if ((int) $filters['folder'] > 0 && ($cur = array_values(array_filter($folders, fn($f) => (string) $f['id'] === $filters['folder']))[0] ?? null)): ?>
          <div class="panel-body border-top">
            <form class="d-flex gap-2 mb-2" method="post" action="<?= e(route('admin.media.folders.update', ['id' => $cur['id']])) ?>"><?= csrf_field() ?><?= method_field('PUT') ?>
              <input class="form-control form-control-sm" name="name" value="<?= e($cur['name']) ?>" maxlength="100" aria-label="फ़ोल्डर का नया नाम" required>
              <button class="btn btn-sm btn-outline-secondary" type="submit">नाम बदलें</button>
            </form>
            <?= delete_button(route('admin.media.folders.destroy', ['id' => $cur['id']]), 'फ़ोल्डर “' . $cur['name'] . '” हटेगा; फ़ाइलें “बिना फ़ोल्डर” में चली जाएँगी।', 'फ़ोल्डर हटाएँ', 'btn btn-sm btn-outline-danger') ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="col-xl-9 order-1 order-xl-2">
    <section class="panel">
      <div class="panel-tabs">
        <?php foreach (['' => 'सभी', 'image' => 'इमेज', 'video' => 'वीडियो', 'audio' => 'ऑडियो', 'document' => 'दस्तावेज़'] as $k => $l): ?>
          <a href="<?= e($q(['kind' => $k])) ?>" class="<?= $filters['kind'] === $k ? 'active' : '' ?>"><?= e($l) ?><?php if (!$trash): ?> <span><?= num($counts[$k === '' ? 'alls' : $k]) ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      </div>
      <form class="filter-bar" method="get">
        <?php foreach (['kind', 'folder'] as $h): ?><input type="hidden" name="<?= $h ?>" value="<?= e($filters[$h]) ?>"><?php endforeach; ?>
        <?php if ($trash): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
        <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="शीर्षक, alt, कैप्शन, कीवर्ड, क्रेडिट, फ़ाइल नाम" aria-label="मीडिया खोजें"></div>
        <select class="form-select w-auto" name="month" aria-label="महीना">
          <option value="">सभी महीने</option>
          <?php foreach ($months as $m): [$y, $mo] = explode('-', $m); ?><option value="<?= e($m) ?>"<?= selected($m, $filters['month']) ?>><?= e($monthNames[(int) $mo] . ' ' . $y) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-dark" type="submit">खोजें</button>
      </form>

      <?php if ($media->items): ?>
      <form method="post" action="<?= e(route('admin.media.bulk')) ?>" data-bulk-form data-bulk-noun="फ़ाइलें">
        <?= csrf_field() ?>
        <div class="bulk-bar" hidden>
          <span data-bulk-count>0 चुने गए</span>
          <select class="form-select form-select-sm w-auto" name="action" aria-label="बल्क काम" required>
            <option value="">काम चुनें…</option>
            <?php if (!$trash): ?>
              <?php if (can('media.edit')): ?><option value="move">फ़ोल्डर में ले जाएँ</option><?php endif; ?>
              <?php if (can('media.delete')): ?><option value="trash">ट्रैश में डालें</option><?php endif; ?>
            <?php else: ?>
              <?php if (can('media.delete')): ?><option value="restore">वापस लाएँ</option><?php endif; ?>
              <?php if (can('media.manage')): ?><option value="delete">स्थायी रूप से हटाएँ</option><?php endif; ?>
            <?php endif; ?>
          </select>
          <?php if (!$trash): ?>
          <select class="form-select form-select-sm w-auto" name="folder_id" aria-label="फ़ोल्डर" data-show-for="move" hidden>
            <option value="0">बिना फ़ोल्डर</option>
            <?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option><?php endforeach; ?>
          </select>
          <?php endif; ?>
          <button class="btn btn-sm btn-dark" type="submit">लागू करें</button>
        </div>
        <div class="d-flex align-items-center gap-2 px-3 pt-3 small"><input class="form-check-input mt-0" type="checkbox" data-check-all id="checkAll"><label for="checkAll">इस पेज की सभी चुनें</label></div>
        <ul class="media-grid">
          <?php foreach ($media->items as $m): ?>
            <li class="media-card<?= $m['kind'] !== 'image' ? ' is-file' : '' ?>">
              <input class="form-check-input media-check" type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="चुनें: <?= e($m['title'] ?: $m['original_name']) ?>">
              <a href="<?= e(route('admin.media.edit', ['id' => $m['id']])) ?>" class="media-thumb">
                <?php if ($m['kind'] === 'image'): ?>
                  <img src="<?= e(media_url($m, 'thumb')) ?>" alt="<?= e($m['alt'] ?? '') ?>" loading="lazy">
                <?php else: ?>
                  <i class="fa-solid <?= e(Media::ICONS[$m['kind']]) ?>"></i><span><?= e(strtoupper(pathinfo($m['file'], PATHINFO_EXTENSION))) ?></span>
                <?php endif; ?>
                <?php if ($m['kind'] === 'image' && !$m['alt']): ?><span class="media-flag" title="Alt टेक्स्ट नहीं भरा">alt?</span><?php endif; ?>
              </a>
              <div class="media-meta">
                <b title="<?= e($m['title'] ?: $m['original_name']) ?>"><?= e($m['title'] ?: $m['original_name']) ?></b>
                <span><?= $m['width'] ? (int) $m['width'] . '×' . (int) $m['height'] . ' · ' : '' ?><?= e(MediaService::humanSize((int) $m['size'])) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </form>
      <?= $this->insert('partials/admin/pagination', ['p' => $media]) ?>
      <?php else: ?>
        <div class="empty-state"><i class="fa-solid fa-photo-film"></i><p><?= $trash ? 'ट्रैश ख़ाली है।' : ($filters['q'] !== '' ? 'कुछ नहीं मिला।' : 'यहाँ अभी कोई फ़ाइल नहीं है। ऊपर “अपलोड” से जोड़ें।') ?></p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
