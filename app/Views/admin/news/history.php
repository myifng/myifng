<?php
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = 'हिस्ट्री: ' . $news['title'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.news.index')) ?>">ख़बरें</a></li><li class="breadcrumb-item active" aria-current="page">#<?= (int) $news['id'] ?> स्थिति और हिस्ट्री</li></ol></nav>
    <h1><?= e($news['title']) ?></h1>
    <p><?= NewsWorkflow::badge($news['status']) ?><?= $news['deleted_at'] ? ' <span class="badge text-bg-danger">ट्रैश में</span>' : '' ?> · <?= e($category['name'] ?? 'बिना श्रेणी') ?><?= $location ? ' · ' . e($location['name']) : '' ?> · रिपोर्टर: <?= e($reporter ?? '—') ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($canEdit): ?><a class="btn btn-brand" href="<?= e(route('admin.news.edit', ['id' => $news['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= e(route('admin.news.preview', ['id' => $news['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i> प्रीव्यू</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <?php if ($actions): ?>
    <section class="panel">
      <div class="panel-head"><h2>अगला क़दम</h2></div>
      <div class="panel-body">
        <div class="wf-actions">
          <?php foreach ($actions as $k => $a): ?>
            <button type="button" class="btn btn-outline-<?= in_array($k, ['reject', 'disable'], true) ? 'danger' : ($k === 'publish' ? 'success' : 'secondary') ?>" data-bs-toggle="collapse" data-bs-target="#wf-<?= e($k) ?>" aria-expanded="false" aria-controls="wf-<?= e($k) ?>"><i class="fa-solid <?= e($a['icon']) ?> me-1"></i><?= e($a['label']) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($actions as $k => $a): ?>
          <form class="collapse wf-form" id="wf-<?= e($k) ?>" method="post" action="<?= e(route('admin.news.transition', ['id' => $news['id']])) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($k) ?>">
            <?php if ($k === 'schedule'): ?>
              <label class="form-label" for="sch-<?= e($k) ?>">कब प्रकाशित हो?</label>
              <input class="form-control mb-2" type="datetime-local" id="sch-<?= e($k) ?>" name="scheduled_at" required min="<?= date('Y-m-d\TH:i', time() + 180) ?>">
            <?php endif; ?>
            <label class="form-label" for="rm-<?= e($k) ?>"><?= $a['remark'] ? ($k === 'reject' ? 'रिपोर्टर को क्या सुधारना है? (ज़रूरी, रिपोर्टर को दिखेगा)' : 'कारण (ज़रूरी)') : 'टिप्पणी (वैकल्पिक)' ?></label>
            <textarea class="form-control mb-2" id="rm-<?= e($k) ?>" name="remark" rows="2" maxlength="3000"<?= $a['remark'] ? ' required' : '' ?>></textarea>
            <button class="btn btn-<?= in_array($k, ['reject', 'disable'], true) ? 'danger' : 'brand' ?>" type="submit"><?= e($a['label']) ?> → <?= e(NewsWorkflow::label($a['to'])) ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="panel<?= $actions ? ' mt-3' : '' ?>">
      <div class="panel-head"><h2>वर्ज़न (<?= count($revisions) ?>)</h2></div>
      <?php if ($revisions): ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 data-table">
          <thead><tr><th>वर्ज़न</th><th>किसने / कब</th><th>कारण</th><th class="text-end"></th></tr></thead>
          <tbody>
          <?php foreach ($revisions as $i => $r): ?>
            <tr>
              <td><b>v<?= (int) $r['version'] ?></b><?= $i === 0 ? ' <span class="chip-sm">मौजूदा</span>' : '' ?><?= $r['is_correction'] ? ' <span class="chip-sm flag-breaking">सार्वजनिक सुधार</span>' : '' ?><span class="d-block small text-body-secondary"><?= e(NewsWorkflow::label($r['status'])) ?></span></td>
              <td class="small"><?= e($r['user'] ?? '—') ?><span class="d-block text-body-secondary"><?= hindi_date($r['created_at'], true) ?></span></td>
              <td class="small"><?= e($r['reason'] ?? '') ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.news.revision', ['id' => $news['id'], 'rid' => $r['id']])) ?>">तुलना</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?><div class="panel-body small text-body-secondary">कोई वर्ज़न नहीं।</div><?php endif; ?>
    </section>
  </div>

  <div class="col-xl-5">
    <section class="panel" id="remarks">
      <div class="panel-head"><h2>टिप्पणियाँ और स्थिति</h2></div>
      <div class="panel-body">
        <?php if (!$news['deleted_at']): ?>
        <form class="mb-3" method="post" action="<?= e(route('admin.news.remark', ['id' => $news['id']])) ?>">
          <?= csrf_field() ?>
          <label class="form-label" for="rmMsg">नई टिप्पणी</label>
          <textarea class="form-control mb-2" id="rmMsg" name="message" rows="2" maxlength="3000" required></textarea>
          <div class="d-flex gap-2 align-items-center">
            <?php if (can('news.approve')): ?>
              <select class="form-select form-select-sm w-auto" name="type" aria-label="किसे दिखे">
                <option value="note">आंतरिक (सिर्फ़ डेस्क)</option><option value="feedback">रिपोर्टर को भी दिखे</option>
              </select>
            <?php endif; ?>
            <button class="btn btn-sm btn-dark ms-auto" type="submit">जोड़ें</button>
          </div>
        </form>
        <?php endif; ?>
        <?= $this->insert('admin/news/_remarks', ['remarks' => $remarks]) ?>
      </div>
    </section>
  </div>
</div>
