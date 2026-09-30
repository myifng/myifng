<?php
use App\Services\FormService;
$this->layout('layouts/admin');
$title = $s['ref_no'];
[$sl, $sc] = $t[3][$s['status']] ?? [$s['status'], 'secondary'];
$canEdit = FormService::can($s['type'], 'edit');
$noteIcons = ['note' => 'fa-note-sticky', 'reply' => 'fa-reply', 'status' => 'fa-arrows-rotate', 'system' => 'fa-gear'];
$size = static fn(int $b) => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.inbox', ['type' => $s['type']])) ?>"><?= e($t[0]) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= e($s['ref_no']) ?></li></ol></nav>
    <h1><?= e($s['ref_no']) ?> <span class="badge text-bg-<?= $sc ?> fs-6 align-middle"><?= e($sl) ?></span></h1>
    <p><?= e($s['form_title']) ?><?= $s['job_title'] ? ' · पद: <a href="' . e(route('careers.show', ['slug' => $s['job_slug']])) . '" target="_blank" rel="noopener">' . e($s['job_title']) . '</a>' : '' ?> · <?= e(hindi_date($s['created_at'], true)) ?><?= $others ? ' · <a href="' . e(route('admin.inbox', ['type' => $s['type']])) . '?status=all&amp;q=' . e(rawurlencode((string) ($s['email'] ?: $s['mobile']))) . '">इस व्यक्ति के ' . $others . ' और</a>' : '' ?></p>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel"><div class="panel-head"><h2>जानकारी</h2></div>
      <dl class="sub-data">
        <?php foreach ($s['data'] as $k => [$label, $val]): ?>
          <dt><?= e($label) ?></dt>
          <dd><?php if (is_array($val)): ?><?= e(implode(', ', $val)) ?>
            <?php elseif (in_array($k, ['email'], true)): ?><a href="mailto:<?= e($val) ?>"><?= e($val) ?></a>
            <?php elseif ($k === 'mobile'): ?><a href="tel:<?= e($val) ?>"><?= e($val) ?></a> · <a href="https://wa.me/91<?= e(substr(preg_replace('/\D/', '', $val), -10)) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php elseif (preg_match('~^https?://~', (string) $val)): ?><a href="<?= e($val) ?>" target="_blank" rel="noopener nofollow"><?= e($val) ?></a>
            <?php else: ?><?= nl2br(e((string) $val)) ?><?php endif; ?></dd>
        <?php endforeach; ?>
      </dl>
    </section>
    <?php if ($s['files']): ?>
    <section class="panel mt-3"><div class="panel-head"><h2>फ़ाइलें</h2><span class="small text-body-secondary">निजी; हर बार देखना ऑडिट में दर्ज</span></div><div class="panel-body sub-files">
      <?php foreach ($s['files'] as $k => $f): $u = route('admin.submissions.file', ['id' => $s['id'], 'key' => $k]); ?>
        <div class="sub-file">
          <?php if (str_starts_with((string) $f['mime'], 'image/')): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener"><img src="<?= e($u) ?>" alt="<?= e($f['label']) ?>" loading="lazy"></a>
          <?php elseif (str_starts_with((string) $f['mime'], 'video/')): ?><video src="<?= e($u) ?>" controls preload="metadata"></video>
          <?php else: ?><a class="sub-doc" href="<?= e($u) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-file-lines"></i></a><?php endif; ?>
          <div class="small"><b><?= e($f['label']) ?></b><br><?= e($f['name']) ?> · <?= e($size((int) $f['size'])) ?> · <a href="<?= e($u) ?>?download=1">डाउनलोड</a></div>
        </div>
      <?php endforeach; ?>
    </div></section>
    <?php endif; ?>
    <?php if ($s['type'] === 'news_tip' && $s['status'] !== 'converted' && FormService::can('news_tip', 'approve')): ?>
    <section class="panel mt-3"><div class="panel-head"><h2><i class="fa-solid fa-wand-magic-sparkles me-2 text-body-secondary"></i>इस टिप से बनाएँ</h2></div><div class="panel-body">
      <form method="post" action="<?= e(route('admin.submissions.convert', ['id' => $s['id']])) ?>" class="row g-2 align-items-end"><?= csrf_field() ?>
        <div class="col-12"><label class="form-label small" for="cvTitle">शीर्षक</label><input class="form-control" id="cvTitle" name="title" maxlength="190" value="<?= e(\App\Helpers\Str::limit((string) ($s['data']['description'][1] ?? ''), 90)) ?>"></div>
        <div class="col-md-4"><label class="form-label small" for="cvCat">श्रेणी</label><select class="form-select" id="cvCat" name="category_id"><option value="">—</option><?php foreach ($categories as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
        <?php if (can('assignments.create')): ?><div class="col-md-4"><label class="form-label small" for="cvRep">रिपोर्टर (असाइनमेंट के लिए)</label><select class="form-select" id="cvRep" name="reporter_id"><option value="">चुनें…</option><?php foreach ($reporters as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit" name="to" value="assignment">असाइनमेंट</button></div><?php endif; ?>
        <?php if (can('news.create')): ?><div class="col-md-2"><button class="btn btn-brand w-100" type="submit" name="to" value="news">ड्राफ़्ट ख़बर</button></div><?php endif; ?>
      </form>
      <p class="form-text mb-0">"मेरा नाम प्रकाशित न करें" चुना हो तो स्रोत में नाम नहीं जाता।</p>
    </div></section>
    <?php elseif ($s['converted']): [$ct, $cid] = explode(':', $s['converted']) + [1 => 0]; ?>
      <div class="alert alert-success mt-3">इस टिप से <?= $ct === 'news' ? '<a href="' . e(route('admin.news.edit', ['id' => $cid])) . '">ड्राफ़्ट ख़बर #' . (int) $cid . '</a>' : '<a href="' . e(route('admin.assignments.edit', ['id' => $cid])) . '">असाइनमेंट #' . (int) $cid . '</a>' ?> बना।</div>
    <?php endif; ?>
    <?php if ($s['email'] && $canEdit): ?>
    <section class="panel mt-3"><div class="panel-head"><h2><i class="fa-solid fa-reply me-2 text-body-secondary"></i>ईमेल से जवाब</h2><span class="small text-body-secondary"><?= e($s['email']) ?></span></div><div class="panel-body">
      <form method="post" action="<?= e(route('admin.submissions.reply', ['id' => $s['id']])) ?>" novalidate><?= csrf_field() ?>
        <input class="form-control mb-2" name="subject" maxlength="190" value="Re: <?= e($s['form_title']) ?> (<?= e($s['ref_no']) ?>)" aria-label="विषय">
        <textarea class="form-control mb-2" name="body" rows="5" maxlength="10000" required placeholder="नमस्ते <?= e((string) $s['name']) ?>, …" aria-label="जवाब"></textarea>
        <button class="btn btn-brand" type="submit" data-confirm-then="जवाब <?= e($s['email']) ?> पर भेजा जाएगा।"><i class="fa-solid fa-paper-plane me-1"></i> भेजें</button>
      </form></div></section>
    <?php endif; ?>
    <section class="panel mt-3"><div class="panel-head"><h2>टाइमलाइन</h2></div><div class="panel-body">
      <?php if (FormService::can($s['type'], 'view')): ?>
      <form method="post" action="<?= e(route('admin.submissions.note', ['id' => $s['id']])) ?>" class="d-flex gap-2 mb-3"><?= csrf_field() ?>
        <input class="form-control" name="body" maxlength="5000" required placeholder="अंदरूनी नोट (भेजने वाले को नहीं दिखता)" aria-label="नोट"><button class="btn btn-outline-secondary" type="submit">जोड़ें</button></form>
      <?php endif; ?>
      <ul class="sub-timeline">
        <?php foreach ($notes as $n): ?><li class="t-<?= e($n['type']) ?>"><i class="fa-solid <?= $noteIcons[$n['type']] ?>"></i><div><div class="small text-body-secondary"><?= e((string) ($n['name'] ?? 'सिस्टम')) ?> · <?= e(hindi_date($n['created_at'], true)) ?></div><div><?= nl2br(e($n['body'])) ?></div></div></li><?php endforeach; ?>
        <li class="t-system"><i class="fa-solid fa-inbox"></i><div><div class="small text-body-secondary"><?= e(hindi_date($s['created_at'], true)) ?></div><div>फ़ॉर्म मिला<?= $s['page_url'] ? ' (' . e($s['page_url']) . ')' : '' ?></div></div></li>
      </ul>
    </div></section>
  </div>
  <div class="col-xl-4">
    <?php if ($canEdit): ?>
    <section class="panel sticky-xl"><div class="panel-body">
      <form method="post" action="<?= e(route('admin.submissions.update', ['id' => $s['id']])) ?>"><?= csrf_field() ?>
        <?= field('select', 'status', 'स्थिति', $s['status'], ['options' => array_map(static fn($x) => $x[0], $t[3]), 'fresh' => true]) ?>
        <?= field('select', 'assigned_to', $s['type'] === 'complaint' ? 'सौंपा गया अधिकारी' : 'किसे सौंपा', (string) ($s['assigned_to'] ?? ''), ['options' => $staff, 'empty' => '— कोई नहीं —', 'fresh' => true]) ?>
        <?php if ($s['type'] === 'complaint'): ?>
          <?= field('textarea', 'response', 'जवाब (शिकायतकर्ता को ट्रैकिंग पर दिखेगा)', (string) $s['response'], ['rows' => 4, 'fresh' => true]) ?>
          <?= field('textarea', 'resolution', 'निपटारा (संक्षेप में)', (string) $s['resolution'], ['rows' => 2, 'fresh' => true]) ?>
          <?php if ($s['email']): ?><label class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="notify_applicant" value="1" checked> शिकायतकर्ता को ईमेल से बताएँ</label><?php endif; ?>
        <?php endif; ?>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
      </form>
      <hr><dl class="small mb-0 sub-meta"><dt>IP (हैश)</dt><dd class="font-monospace"><?= e(substr((string) $s['ip_hash'], 0, 12)) ?>…</dd><dt>ब्राउज़र</dt><dd><?= e(\App\Helpers\Str::limit((string) $s['user_agent'], 70)) ?></dd><dt>अपडेट</dt><dd><?= e(hindi_date($s['updated_at'], true)) ?></dd></dl>
    </div></section>
    <?php endif; ?>
  </div>
</div>
