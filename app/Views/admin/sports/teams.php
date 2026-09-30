<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$title = 'टीमें';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.index')) ?>">खेल केंद्र</a></li><li class="breadcrumb-item active" aria-current="page">टीमें</li></ol></nav>
    <h1>टीमें</h1>
  </div>
</div>
<?= $this->insert('admin/sports/_nav', ['active' => 'teams']) ?>
<div class="row g-3">
  <div class="col-xl-8"><section class="panel"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>टीम</th><th>खेल</th><th class="text-end">खिलाड़ी</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items as $t): ?>
      <tr><td><span class="party-dot" style="background:<?= e($t['color']) ?>"></span> <b><?= e($t['short_name']) ?></b> <?= e($t['name']) ?><?= $t['country'] ? ' <small class="text-body-secondary">' . e($t['country']) . '</small>' : '' ?></td>
        <td><?= e(SS::SPORTS[$t['sport']][0]) ?></td><td class="text-end"><a href="<?= e(route('admin.sports.team', ['id' => $t['id']])) ?>"><?= num((int) $t['players']) ?></a></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.sports.team', ['id' => $t['id']])) ?>"><i class="fa-solid fa-users me-1"></i>खिलाड़ी</a>
          <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" data-party-edit='<?= e(json_encode(['id' => $t['id'], 'name' => $t['name'], 'short_name' => $t['short_name'], 'color' => $t['color'], 'sport' => $t['sport'], 'country' => $t['country']], JSON_UNESCAPED_UNICODE)) ?>' title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></button>
          <?= delete_button(route('admin.sports.teams.delete', ['id' => $t['id']]), 'टीम और उसके खिलाड़ी हट जाएँगे।') ?>
        </td></tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-people-group"></i><p>कोई टीम नहीं।</p></div></td></tr><?php endif; ?></tbody>
  </table></div></section></div>
  <div class="col-xl-4"><section class="panel"><div class="panel-head"><h2 data-party-title data-new-label="नई टीम">नई टीम</h2></div>
    <form class="panel-body" method="post" action="<?= e(route('admin.sports.teams.save')) ?>" novalidate data-party-form>
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) old('id', '')) ?>">
      <?= field('text', 'name', 'टीम का नाम', '', ['required' => true]) ?>
      <div class="row g-2"><div class="col-6"><?= field('text', 'short_name', 'छोटा नाम', '', ['required' => true, 'attrs' => ['maxlength' => 12]]) ?></div><div class="col-6"><?= field('color', 'color', 'रंग', '#1f5fbf') ?></div></div>
      <div class="row g-2"><div class="col-6"><?= field('select', 'sport', 'खेल', 'cricket', ['options' => array_map(static fn($s) => $s[0], SS::SPORTS)]) ?></div><div class="col-6"><?= field('text', 'country', 'देश / शहर', '') ?></div></div>
      <div class="d-flex gap-2"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button><button class="btn btn-outline-secondary" type="reset" data-party-reset>नई</button></div>
    </form>
  </section></div>
</div>
