<?php
$this->layout('layouts/admin');
$title = 'ब्रेकिंग बदलें';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.breaking.index')) ?>">ब्रेकिंग</a></li><li class="breadcrumb-item active" aria-current="page">बदलें</li></ol></nav>
    <h1>ब्रेकिंग बदलें</h1>
    <p>बनाया: <?= hindi_date($item['created_at'], true) ?></p>
  </div>
</div>
<div class="row"><div class="col-xl-7"><section class="panel"><div class="panel-body"><?= $this->insert('admin/breaking/_form', ['item' => $item]) ?></div></section></div></div>
