<?php
$this->layout('layouts/admin');
$title = $t['name'] . ' · खिलाड़ी';
$rows = $players;
$rows[] = [];
$rows[] = [];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.teams')) ?>">टीमें</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($t['short_name']) ?></li></ol></nav>
    <h1><span class="party-dot" style="background:<?= e($t['color']) ?>"></span> <?= e($t['name']) ?>: खिलाड़ी</h1>
  </div>
</div>
<form class="panel" method="post" action="<?= e(route('admin.sports.team.players', ['id' => $t['id']])) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>नाम</th><th>भूमिका</th><th>जर्सी</th><th>कप्तान</th><th>हटाएँ</th></tr></thead>
    <tbody><?php foreach ($rows as $i => $p): $k = 'p[' . $i . ']'; ?>
      <tr><td><input type="hidden" name="<?= $k ?>[id]" value="<?= (int) ($p['id'] ?? 0) ?>"><input class="form-control form-control-sm" name="<?= $k ?>[name]" value="<?= e((string) ($p['name'] ?? '')) ?>" placeholder="<?= empty($p) ? 'नया खिलाड़ी' : '' ?>" aria-label="नाम"></td>
        <td><input class="form-control form-control-sm" name="<?= $k ?>[role]" value="<?= e((string) ($p['role'] ?? '')) ?>" placeholder="बल्लेबाज़ / गेंदबाज़ / गोलकीपर" aria-label="भूमिका"></td>
        <td><input class="form-control form-control-sm" name="<?= $k ?>[jersey]" value="<?= e((string) ($p['jersey'] ?? '')) ?>" style="width:70px" aria-label="जर्सी"></td>
        <td><input class="form-check-input" type="checkbox" name="<?= $k ?>[is_captain]" value="1"<?= checked(!empty($p['is_captain'])) ?> aria-label="कप्तान"></td>
        <td><?php if (!empty($p['id'])): ?><input class="form-check-input" type="checkbox" name="<?= $k ?>[delete]" value="1" aria-label="हटाएँ"><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="panel-body border-top"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button> <span class="small text-body-secondary ms-2">हर बार 2 ख़ाली पंक्तियाँ नए खिलाड़ियों के लिए।</span></div>
</form>
