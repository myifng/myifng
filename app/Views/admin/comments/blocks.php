<?php
use App\Models\CommentBlock;
$this->layout('layouts/admin');
$title = 'टिप्पणी ब्लॉक सूची';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.comments.index')) ?>">टिप्पणियाँ</a></li><li class="breadcrumb-item active" aria-current="page">ब्लॉक</li></ol></nav>
    <h1>ब्लॉक सूची</h1>
    <p>ब्लॉक किए ईमेल, IP (हैश) और पाठक टिप्पणी नहीं कर सकते; ब्लॉक शब्द वाली टिप्पणी स्पैम में जाती है।</p>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-4 order-lg-2"><section class="panel"><div class="panel-body">
    <h2 class="h6">शब्द जोड़ें</h2>
    <form method="post" action="<?= e(route('admin.comments.words')) ?>" class="d-flex gap-2"><?= csrf_field() ?><input class="form-control" name="value" maxlength="100" required placeholder="जैसे: गाली या स्पैम शब्द" aria-label="शब्द"><button class="btn btn-brand" type="submit">जोड़ें</button></form>
    <p class="form-text">ईमेल/IP/पाठक को टिप्पणियों की सूची से "ब्लॉक" बटन से जोड़ें।</p>
  </div></section></div>
  <div class="col-lg-8"><section class="panel">
    <?php if ($items): ?>
    <div class="table-responsive"><table class="table align-middle mb-0 data-table">
      <thead><tr><th>प्रकार</th><th>मान</th><th>नोट</th><th>कब</th><th class="text-end">काम</th></tr></thead>
      <tbody><?php foreach ($items as $b): ?><tr>
        <td><span class="badge text-bg-light"><?= e(CommentBlock::TYPES[$b['type']]) ?></span></td>
        <td class="font-monospace small text-break"><?= e($b['type'] === 'ip' ? substr($b['value'], 0, 12) . '…' : $b['value']) ?></td>
        <td class="small"><?= e((string) $b['note']) ?><?= $b['creator'] ? '<div class="text-body-secondary">' . e($b['creator']) . '</div>' : '' ?></td>
        <td class="small text-nowrap"><?= e(hindi_date($b['created_at'])) ?></td>
        <td class="text-end"><form method="post" action="<?= e(route('admin.comments.unblock', ['id' => $b['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">हटाएँ</button></form></td>
      </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php else: ?><div class="empty-state"><i class="fa-solid fa-ban"></i><p>अभी कोई ब्लॉक नहीं।</p></div><?php endif; ?>
  </section></div>
</div>
