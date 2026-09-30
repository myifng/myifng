<?php
use App\Models\Comment;
$this->layout('layouts/admin');
$title = 'टिप्पणियाँ';
$act = static function (array $c, string $to, string $label, string $cls = 'btn-outline-secondary'): string {
    return '<form class="d-inline" method="post" action="' . e(route('admin.comments.action')) . '">' . csrf_field() . '<input type="hidden" name="ids[]" value="' . (int) $c['id'] . '"><button class="btn btn-sm ' . $cls . '" type="submit" name="action" value="' . $to . '">' . $label . '</button></form>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">टिप्पणियाँ</li></ol></nav>
    <h1>टिप्पणियाँ</h1>
    <p><?= $newsTitle ? 'ख़बर: <b>' . e($newsTitle) . '</b> · <a href="' . e(route('admin.comments.index')) . '">सभी</a>' : 'पाठकों की टिप्पणियाँ जाँचें, जवाब दें, स्पैम हटाएँ।' ?></p>
  </div>
  <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(route('admin.comments.blocks')) ?>"><i class="fa-solid fa-ban me-1"></i> ब्लॉक सूची</a>
    <?php if (can('settings.manage')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.settings', ['tab' => 'audience'])) ?>"><i class="fa-solid fa-sliders me-1"></i> सेटिंग</a><?php endif; ?></div>
</div>
<nav class="sub-tabs mb-3" aria-label="स्थिति">
  <?php foreach (['pending' => 'पेंडिंग', 'approved' => 'स्वीकृत', 'spam' => 'स्पैम', 'rejected' => 'अस्वीकृत', 'all' => 'सभी'] as $k => $l): ?>
    <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(route('admin.comments.index')) ?>?status=<?= $k ?>"><?= e($l) ?> <span class="badge text-bg-light"><?= num($k === 'all' ? array_sum($tally) : ($tally[$k] ?? 0)) ?></span></a>
  <?php endforeach; ?>
</nav>
<section class="panel">
  <form class="filter-bar" method="get"><input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="टिप्पणी, नाम या ईमेल" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($items->items): ?>
  <form method="post" action="<?= e(route('admin.comments.action')) ?>" id="cmBulk"><?= csrf_field() ?></form>
  <ul class="cm-admin">
    <?php foreach ($items->items as $c): ?>
      <li class="cm-a <?= e($c['status']) ?>">
        <input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" form="cmBulk" data-check="cm" aria-label="चुनें">
        <div class="cm-a-main">
          <div class="cm-a-meta"><b><?= e($c['name']) ?></b><?= $c['staff'] ? ' <span class="badge text-bg-danger">संपादक</span>' : '' ?><?= $c['reader_id'] ? ' <span class="badge text-bg-info">पाठक खाता</span>' : '' ?>
            <?= $c['email'] && !$c['user_id'] ? '<span class="text-body-secondary small">' . e($c['email']) . '</span>' : '' ?>
            <span class="badge text-bg-<?= Comment::BADGE[$c['status']] ?>"><?= e(Comment::STATUSES[$c['status']]) ?></span>
            <span class="small text-body-secondary"><?= e(hindi_date($c['created_at'], true)) ?></span></div>
          <p class="cm-a-body"><?= $c['parent_name'] ? '<span class="text-body-secondary small">↳ ' . e($c['parent_name']) . ' को जवाब:</span> ' : '' ?><?= nl2br(e($c['body'])) ?></p>
          <div class="small">ख़बर: <a href="<?= e(url('news/' . $c['news_slug'])) ?>#comment-<?= (int) $c['id'] ?>" target="_blank" rel="noopener"><?= e(\App\Helpers\Str::limit($c['news_title'], 80)) ?></a>
            · <a href="<?= e(route('admin.comments.index')) ?>?status=all&amp;news=<?= (int) $c['news_id'] ?>">इस ख़बर की सभी</a></div>
          <div class="cm-a-actions">
            <?php if (can('comments.approve')): ?>
              <?php if ($c['status'] !== 'approved'): ?><?= $act($c, 'approved', '<i class="fa-solid fa-check"></i> स्वीकृत', 'btn-success') ?><?php endif; ?>
              <?php if ($c['status'] !== 'spam'): ?><?= $act($c, 'spam', 'स्पैम') ?><?php endif; ?>
              <?php if ($c['status'] !== 'rejected'): ?><?= $act($c, 'rejected', 'अस्वीकृत') ?><?php endif; ?>
            <?php endif; ?>
            <?php if (can('comments.edit') && !$c['user_id']): ?>
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rp<?= (int) $c['id'] ?>" aria-expanded="false"><i class="fa-solid fa-reply"></i> जवाब</button>
              <div class="dropdown d-inline"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ban"></i> ब्लॉक</button>
                <div class="dropdown-menu">
                  <?php foreach (['email' => 'यह ईमेल', 'ip' => 'यह IP', 'reader' => 'यह पाठक खाता'] as $t => $l): if (($t === 'email' && !$c['email']) || ($t === 'reader' && !$c['reader_id']) || ($t === 'ip' && !$c['ip_hash'])) continue; ?>
                    <form method="post" action="<?= e(route('admin.comments.block', ['id' => $c['id']])) ?>" data-confirm="<?= e($l) ?> ब्लॉक होगा और उसकी बाकी टिप्पणियाँ स्पैम में जाएँगी।"><?= csrf_field() ?><button class="dropdown-item" type="submit" name="type" value="<?= $t ?>"><?= e($l) ?> ब्लॉक करें</button></form>
                  <?php endforeach; ?>
                </div></div>
            <?php endif; ?>
            <?php if (can('comments.delete')): ?><form class="d-inline" method="post" action="<?= e(route('admin.comments.action')) ?>" data-confirm="टिप्पणी (और उसके जवाब) हमेशा के लिए हट जाएँगे।"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete" aria-label="हटाएँ"><i class="fa-regular fa-trash-can"></i></button></form><?php endif; ?>
          </div>
          <?php if (can('comments.edit') && !$c['user_id']): ?>
          <form class="collapse mt-2" id="rp<?= (int) $c['id'] ?>" method="post" action="<?= e(route('admin.comments.reply', ['id' => $c['id']])) ?>"><?= csrf_field() ?>
            <textarea class="form-control mb-2" name="body" rows="2" maxlength="2000" required placeholder="<?= e(setting('site_name')) ?> की ओर से जवाब" aria-label="जवाब"></textarea>
            <button class="btn btn-sm btn-brand" type="submit">जवाब प्रकाशित करें</button></form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (can('comments.approve')): ?>
  <div class="bulk-bar"><label class="small"><input class="form-check-input me-1" type="checkbox" data-check-all="cm"> सभी चुनें</label>
    <button class="btn btn-sm btn-success" type="submit" form="cmBulk" name="action" value="approved">स्वीकृत</button>
    <button class="btn btn-sm btn-outline-secondary" type="submit" form="cmBulk" name="action" value="spam">स्पैम</button>
    <button class="btn btn-sm btn-outline-secondary" type="submit" form="cmBulk" name="action" value="rejected">अस्वीकृत</button>
    <?php if (can('comments.delete')): ?><button class="btn btn-sm btn-outline-danger" type="submit" form="cmBulk" name="action" value="delete">हटाएँ</button><?php endif; ?></div>
  <?php endif; ?>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-regular fa-comments"></i><p><?= $status === 'pending' ? 'जाँच के लिए कोई टिप्पणी नहीं।' : 'कोई टिप्पणी नहीं।' ?></p></div><?php endif; ?>
</section>
