<?php
use App\Models\Media;
use App\Services\MediaService;
$this->layout('layouts/admin');
$title = ($m['title'] ?: $m['original_name']) . ' · मीडिया';
$variants = (array) json_decode((string) $m['variants'], true);
$trashed = $m['deleted_at'] !== null;
$sizeLabels = ['large' => 'बड़ी', 'medium' => 'मध्यम', 'thumb' => 'थंबनेल'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.media.index')) ?>">मीडिया लाइब्रेरी</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($m['title'] ?: $m['original_name']) ?></li></ol></nav>
    <h1><?= e($m['title'] ?: $m['original_name']) ?><?= $trashed ? ' <span class="badge text-bg-danger fs-6">ट्रैश में</span>' : '' ?></h1>
    <p><?= e(Media::KINDS[$m['kind']]) ?> · <?= e(MediaService::humanSize((int) $m['size'])) ?><?= $m['width'] ? ' · ' . (int) $m['width'] . '×' . (int) $m['height'] . 'px' : '' ?> · अपलोड <?= hindi_date($m['created_at'], true) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($trashed): ?>
      <?php if (can('media.delete')): ?><form method="post" action="<?= e(route('admin.media.restore', ['id' => $m['id']])) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-rotate-left me-1"></i> वापस लाएँ</button></form><?php endif; ?>
      <?php if (can('media.manage')): ?><?= delete_button(route('admin.media.destroy', ['id' => $m['id']]), 'फ़ाइल और उसके सभी आकार सर्वर से हमेशा के लिए हट जाएँगे। जहाँ लगी है वहाँ टूटी इमेज दिखेगी।', 'स्थायी रूप से हटाएँ', 'btn btn-danger') ?><?php endif; ?>
    <?php elseif (can('media.delete')): ?>
      <form method="post" action="<?= e(route('admin.media.trash', ['id' => $m['id']])) ?>" data-confirm="फ़ाइल ट्रैश में जाएगी।"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit"><i class="fa-regular fa-trash-can me-1"></i> ट्रैश</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="panel">
      <div class="media-preview">
        <?php if ($m['kind'] === 'image'): ?>
          <img src="<?= e(media_url($m, 'large')) ?>?v=<?= e(strtotime($m['updated_at'])) ?>" alt="<?= e($m['alt'] ?? '') ?>">
        <?php elseif ($m['kind'] === 'video'): ?>
          <video src="<?= e(upload_url($m['file'])) ?>" controls preload="metadata"></video>
        <?php elseif ($m['kind'] === 'audio'): ?>
          <audio src="<?= e(upload_url($m['file'])) ?>" controls preload="metadata"></audio>
        <?php else: ?>
          <div class="text-center p-5"><i class="fa-solid fa-file-lines fa-4x text-body-secondary"></i><p class="mt-2 mb-0"><?= e($m['original_name']) ?></p></div>
        <?php endif; ?>
      </div>
      <div class="panel-body border-top">
        <div class="copy-row"><label class="form-label small mb-1" for="urlOrig">फ़ाइल का पता</label>
          <div class="input-group input-group-sm"><input class="form-control font-monospace" id="urlOrig" value="<?= e(upload_url($m['file'])) ?>" readonly><button class="btn btn-outline-secondary" type="button" data-copy="#urlOrig">कॉपी</button><a class="btn btn-outline-secondary" href="<?= e(upload_url($m['file'])) ?>" target="_blank" rel="noopener">खोलें</a></div>
        </div>
        <?php if ($variants): ?>
          <table class="table table-sm small mt-3 mb-0">
            <thead><tr><th>आकार</th><th>माप</th><th>फ़ाइलें</th></tr></thead>
            <tbody>
            <?php foreach ($variants as $k => $v): ?>
              <tr><td><?= e($sizeLabels[$k] ?? $k) ?></td><td><?= (int) $v['w'] ?>×<?= (int) $v['h'] ?></td>
                <td><a href="<?= e(upload_url($v['file'])) ?>" target="_blank" rel="noopener"><?= e(strtoupper(pathinfo($v['file'], PATHINFO_EXTENSION))) ?></a><?= !empty($v['webp']) ? ' · <a href="' . e(upload_url($v['webp'])) . '" target="_blank" rel="noopener">WebP</a>' : '' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php elseif ($m['kind'] === 'image'): ?>
          <p class="small text-body-secondary mt-3 mb-0">इस इमेज के छोटे आकार नहीं बने (GIF, या सर्वर पर GD नहीं); वेबसाइट पर मूल फ़ाइल दिखेगी।</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel mt-3">
      <div class="panel-head"><h2>कहाँ इस्तेमाल हुई</h2></div>
      <div class="panel-body">
        <?php if ($usage): ?>
          <ul class="mb-0"><?php foreach ($usage as [$type, $name, $link]): ?><li><?= e($type) ?>: <a href="<?= e($link) ?>"><?= e($name) ?></a></li><?php endforeach; ?></ul>
        <?php else: ?>
          <p class="text-body-secondary small mb-0">अभी किसी श्रेणी, टॉपिक या पेज में नहीं लगी। (ख़बरों की जाँच Phase 4 से)</p>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <div class="col-xl-5">
    <section class="panel">
      <div class="panel-head"><h2>विवरण</h2></div>
      <form class="panel-body" method="post" action="<?= e(route('admin.media.update', ['id' => $m['id']])) ?>" novalidate>
        <?= csrf_field() ?><?= method_field('PUT') ?>
        <?php $ro = !can('media.edit') || $trashed ? ['disabled' => true] : []; ?>
        <?= field('text', 'title', 'शीर्षक', $m['title'], ['attrs' => ['maxlength' => 190] + $ro]) ?>
        <?php if ($m['kind'] === 'image'): ?><?= field('text', 'alt', 'Alt टेक्स्ट', $m['alt'], ['help' => 'तस्वीर में क्या है, एक वाक्य में। दृष्टिबाधित पाठकों और Google इमेज सर्च के लिए ज़रूरी।', 'attrs' => ['maxlength' => 255] + $ro]) ?><?php endif; ?>
        <?= field('textarea', 'caption', 'कैप्शन', $m['caption'], ['rows' => 2, 'attrs' => ['maxlength' => 500] + $ro]) ?>
        <div class="row">
          <div class="col-md-6"><?= field('text', 'credit', 'क्रेडिट / फ़ोटो', $m['credit'], ['placeholder' => 'फ़ोटो: संवाददाता / PTI', 'attrs' => ['maxlength' => 150] + $ro]) ?></div>
          <div class="col-md-6"><?= field('select', 'folder_id', 'फ़ोल्डर', $m['folder_id'], ['options' => array_column($folders, 'name', 'id'), 'empty' => 'बिना फ़ोल्डर', 'attrs' => $ro]) ?></div>
        </div>
        <?= field('text', 'keywords', 'कीवर्ड (खोज के लिए)', $m['keywords'], ['placeholder' => 'कॉमा से अलग', 'attrs' => ['maxlength' => 255] + $ro]) ?>
        <p class="small text-body-secondary">मूल नाम: <?= e($m['original_name']) ?> · <?= e($m['mime']) ?> · अपलोड: <?= e($m['uploaded_by'] ? (string) db()->value('SELECT name FROM {p}users WHERE id = ?', [$m['uploaded_by']]) : '—') ?></p>
        <?php if (!$ro): ?><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><?php endif; ?>
      </form>
    </section>

    <?php if (can('media.edit') && !$trashed): ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2>फ़ाइल बदलें</h2></div>
      <form class="panel-body" method="post" action="<?= e(route('admin.media.replace', ['id' => $m['id']])) ?>" enctype="multipart/form-data" novalidate data-confirm="फ़ाइल बदल जाएगी; जहाँ-जहाँ यह लगी है वहाँ नई दिखेगी। पुरानी फ़ाइल सर्वर पर सुरक्षित रखी जाएगी।">
        <?= csrf_field() ?>
        <?= field('file', 'file', 'नई ' . Media::KINDS[$m['kind']], null, ['attrs' => ['accept' => MediaService::accept($m['kind'])], 'help' => 'उसी प्रकार की फ़ाइल। एक्सटेंशन वही रहे (jpg → jpg) तो URL नहीं बदलता।']) ?>
        <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i> बदलें</button>
      </form>
      <?php if ($m['kind'] === 'image'): ?>
      <form class="panel-body border-top d-flex align-items-center justify-content-between gap-2" method="post" action="<?= e(route('admin.media.regenerate', ['id' => $m['id']])) ?>">
        <?= csrf_field() ?>
        <span class="small text-body-secondary">वॉटरमार्क या आकार की सेटिंग बदली? मौजूदा सेटिंग से छोटे आकार दोबारा बनाएँ।</span>
        <button class="btn btn-sm btn-outline-secondary text-nowrap" type="submit"><i class="fa-solid fa-arrows-rotate me-1"></i> दोबारा बनाएँ</button>
      </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>
