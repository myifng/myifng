<?php
use App\Models\Comment;
use App\Models\Reader;
$this->layout('layouts/admin');
$title = $r['name'];
$btn = static fn(string $a, string $l, string $cls = 'btn-outline-secondary', string $confirm = '') => '<form method="post" action="' . e(route('admin.readers.update', ['id' => $r['id']])) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . '<button class="btn ' . $cls . '" type="submit" name="action" value="' . $a . '">' . $l . '</button></form>';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.readers.index')) ?>">पाठक</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($r['name']) ?></li></ol></nav>
    <h1><?= e($r['name']) ?></h1>
    <p><?= e($r['email']) ?><?= $r['mobile'] ? ' · ' . e($r['mobile']) : '' ?> · <span class="badge text-bg-light"><?= e(Reader::STATUSES[$r['status']]) ?></span></p>
  </div>
  <?php if (can('readers.edit')): ?><div class="d-flex gap-2 flex-wrap">
    <?= $r['status'] === 'pending' ? $btn('verify', 'सत्यापित करें') : '' ?>
    <?= $r['status'] === 'blocked' ? $btn('unblock', 'ब्लॉक हटाएँ') : $btn('block', '<i class="fa-solid fa-ban me-1"></i>ब्लॉक', 'btn-outline-danger', 'पाठक लॉगिन नहीं कर सकेगा।') ?>
    <?= $btn('trust', $r['trusted'] ? 'भरोसेमंद हटाएँ' : '<i class="fa-solid fa-shield-heart me-1"></i>भरोसेमंद बनाएँ') ?>
  </div><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-4"><section class="panel"><div class="panel-body small">
    <p><b>शहर:</b> <?= e((string) ($city ?? '—')) ?></p>
    <p><b>जुड़े:</b> <?= e(hindi_date($r['created_at'], true)) ?></p>
    <p><b>आख़िरी लॉगिन:</b> <?= $r['last_login_at'] ? e(hindi_date($r['last_login_at'], true)) : '—' ?></p>
    <p><b>सेव ख़बरें:</b> <?= num($saved) ?> · <b>इतिहास:</b> <?= (int) $r['history_enabled'] ? 'चालू' : 'बंद' ?></p>
    <p class="mb-0"><b>न्यूज़लेटर:</b> <?= e((string) ($sub ?? 'नहीं')) ?></p>
  </div></section>
  <section class="panel mt-3"><div class="panel-head"><h2>फ़ॉलो</h2></div><div class="panel-body small"><?= $follows ? e(implode(' · ', $follows)) : 'कुछ नहीं' ?></div></section></div>
  <div class="col-lg-8"><section class="panel"><div class="panel-head"><h2>टिप्पणियाँ</h2></div>
    <?php if ($comments): ?><ul class="list-group list-group-flush"><?php foreach ($comments as $c): ?><li class="list-group-item small"><span class="badge text-bg-<?= Comment::BADGE[$c['status']] ?>"><?= e(Comment::STATUSES[$c['status']]) ?></span> <?= e(\App\Helpers\Str::limit($c['body'], 160)) ?><div class="text-body-secondary"><?= e($c['title']) ?> · <?= e(hindi_date($c['created_at'], true)) ?></div></li><?php endforeach; ?></ul>
    <?php else: ?><div class="empty-state"><p>कोई टिप्पणी नहीं।</p></div><?php endif; ?></section></div>
</div>
<?php if (can('readers.delete')): ?>
<div class="danger-zone mt-3"><div><b>खाता हटाएँ</b><p class="mb-0 small">सेव, फ़ॉलो, इतिहास हटेंगे; टिप्पणियाँ "पूर्व पाठक" नाम से रहेंगी।</p></div><?= delete_button(route('admin.readers.destroy', ['id' => $r['id']]), 'पाठक खाता हमेशा के लिए हट जाएगा।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
