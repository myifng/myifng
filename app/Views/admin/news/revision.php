<?php
$this->layout('layouts/admin');
$title = 'वर्ज़न v' . $rev['version'] . ': ' . $news['title'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.news.index')) ?>">ख़बरें</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.news.history', ['id' => $news['id']])) ?>">#<?= (int) $news['id'] ?> हिस्ट्री</a></li><li class="breadcrumb-item active" aria-current="page">v<?= (int) $rev['version'] ?></li></ol></nav>
    <h1>वर्ज़न v<?= (int) $rev['version'] ?> <?= $base ? '<small class="text-body-secondary fs-5">बनाम v' . (int) $base['version'] . '</small>' : '<small class="text-body-secondary fs-5">(पहला वर्ज़न)</small>' ?></h1>
    <p><?= e($user ?? '—') ?> · <?= hindi_date($rev['created_at'], true) ?><?= $rev['reason'] ? ' · कारण: ' . e($rev['reason']) : '' ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="small text-nowrap" for="withV">इससे तुलना:</label>
      <select class="form-select form-select-sm" id="withV" name="with" onchange="this.form.submit()">
        <?php foreach ($versions as $v): if ((int) $v['id'] === (int) $rev['id']) continue; ?><option value="<?= (int) $v['id'] ?>"<?= selected($v['id'], $base['id'] ?? 0) ?>>v<?= (int) $v['version'] ?> · <?= hindi_date($v['created_at'], true) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($canRestore): ?>
      <form method="post" action="<?= e(route('admin.news.revision.restore', ['id' => $news['id'], 'rid' => $rev['id']])) ?>" data-confirm="मौजूदा सामग्री की जगह v<?= (int) $rev['version'] ?> की सामग्री आ जाएगी (नया वर्ज़न बनेगा, कुछ मिटेगा नहीं)।"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="fa-solid fa-rotate-left me-1"></i> यह वर्ज़न वापस लाएँ</button></form>
    <?php endif; ?>
  </div>
</div>

<section class="panel diff-view">
  <div class="panel-body">
    <p class="small text-body-secondary"><span class="diff-key del">हटाया गया</span> <span class="diff-key ins">जोड़ा गया</span></p>
    <h2 class="h5">शीर्षक</h2><p class="diff-chg fw-bold"><?= $titleDiff ?></p>
    <?php if ($rev['summary'] || ($base['summary'] ?? '')): ?><h2 class="h6 mt-3">सार</h2><p class="diff-chg"><?= $summaryDiff ?></p><?php endif; ?>
    <?php if ($fieldDiff): ?>
      <h2 class="h6 mt-3">बदले हुए खाने</h2>
      <table class="table table-sm small"><thead><tr><th>खाना</th><th>पहले</th><th>अब</th></tr></thead><tbody>
      <?php foreach ($fieldDiff as $label => [$o, $n]): ?><tr><td><?= e($label) ?></td><td><del><?= e($o ?: '—') ?></del></td><td><ins><?= e($n ?: '—') ?></ins></td></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <h2 class="h6 mt-3">लेख</h2>
    <div class="diff-body"><?= $contentDiff ?: '<p class="text-body-secondary">लेख ख़ाली है।</p>' ?></div>
  </div>
</section>
