<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$isNew = $t === null;
$title = $isNew ? 'नया टूर्नामेंट' : $t['name'];
$cricket = !$isNew && $t['sport'] === 'cricket';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.tournaments')) ?>">टूर्नामेंट</a></li><li class="breadcrumb-item active" aria-current="page"><?= $isNew ? 'नया' : 'बदलें' ?></li></ol></nav>
    <h1><?= e($title) ?></h1>
  </div>
  <?php if (!$isNew): ?><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(route('admin.sports.index')) ?>?tab=all&amp;tournament=<?= (int) $t['id'] ?>"><i class="fa-solid fa-calendar-days me-1"></i> मैच</a>
    <?php if ($t['status'] !== 'draft'): ?><a class="btn btn-outline-secondary" href="<?= e(route('sports.tournament', ['slug' => $t['slug']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर</a><?php endif; ?></div><?php endif; ?>
</div>
<form method="post" action="<?= e($isNew ? route('admin.sports.tournaments.store') : route('admin.sports.tournaments.update', ['id' => $t['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <div class="row g-3">
    <div class="col-xl-8"><section class="panel"><div class="panel-body">
      <?= field('text', 'name', 'नाम', $t['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: भारत बनाम ऑस्ट्रेलिया T20 सीरीज़ / यूपी टी20 लीग']) ?>
      <div class="row g-2">
        <div class="col-md-4"><?= field('select', 'sport', 'खेल', $t['sport'] ?? 'cricket', ['options' => array_map(static fn($s) => $s[0], SS::SPORTS)]) ?></div>
        <div class="col-md-4"><?= field('date', 'start_date', 'शुरू', $t['start_date'] ?? '') ?></div>
        <div class="col-md-4"><?= field('date', 'end_date', 'ख़त्म', $t['end_date'] ?? '') ?></div>
      </div>
      <?= field('textarea', 'description', 'परिचय', $t['description'] ?? '', ['rows' => 3]) ?>
      <?= media_field('logo', 'लोगो', $t['logo'] ?? '') ?>
    </div></section></div>
    <div class="col-xl-4"><section class="panel sticky-xl"><div class="panel-body">
      <?= field('select', 'status', 'स्थिति', $t['status'] ?? 'upcoming', ['options' => SS::TOUR_STATUSES]) ?>
      <?= field('text', 'season', 'सीज़न', $t['season'] ?? '', ['placeholder' => '2026']) ?>
      <div class="row g-2"><div class="col-6"><?= field('number', 'points_win', 'जीत के अंक', $t['points_win'] ?? 2) ?></div><div class="col-6"><?= field('number', 'points_draw', 'ड्रॉ/बेनतीजा', $t['points_draw'] ?? 1) ?></div></div>
      <?= field('select', 'topic_id', 'ख़बरें (टॉपिक)', $t['topic_id'] ?? '', ['options' => array_column($topics, 'name', 'id'), 'empty' => '—']) ?>
      <?= field('switch', 'is_featured', 'मुख्य टूर्नामेंट', $t['is_featured'] ?? 0) ?>
      <?php if (!$isNew): ?><?= field('text', 'slug', 'पता (स्लग)', $t['slug']) ?><?php endif; ?>
      <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
    </div></section></div>
  </div>
</form>

<?php if (!$isNew): ?>
<section class="panel mt-3">
  <div class="panel-head"><h2>पॉइंट्स टेबल</h2>
    <?php if (can('sports.edit')): ?><form method="post" action="<?= e(route('admin.sports.standings', ['id' => $t['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary" name="recalc" value="1" data-confirm-then="खेले/जीते/हारे/अंक पूरे हुए मैचों से दोबारा गिने जाएँगे। NRR/गोल-अंतर वैसे ही रहेंगे।"><i class="fa-solid fa-calculator me-1"></i> नतीजों से गिनें</button></form><?php endif; ?>
  </div>
  <form method="post" action="<?= e(route('admin.sports.standings', ['id' => $t['id']])) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0 standings-edit">
      <thead><tr><th>टीम</th><th>ग्रुप</th><th>खेले</th><th>जीते</th><th>हारे</th><th>ड्रॉ</th><th>बेनतीजा</th><th>अंक</th><th><?= $cricket ? 'NRR' : 'गोल अंतर' ?></th><th>क्रम</th><th>हटाएँ</th></tr></thead>
      <tbody><?php foreach ($standings as $s): $k = 's[' . (int) $s['team_id'] . ']'; ?>
        <tr><td class="fw-semibold text-nowrap"><?= e($s['short_name']) ?></td>
          <td><input class="form-control form-control-sm" name="<?= $k ?>[group_name]" value="<?= e($s['group_name']) ?>" aria-label="ग्रुप" style="width:60px"></td>
          <?php foreach (['played', 'won', 'lost', 'drawn', 'no_result', 'points'] as $f): ?><td><input class="form-control form-control-sm" type="number" name="<?= $k ?>[<?= $f ?>]" value="<?= (int) $s[$f] ?>" aria-label="<?= $f ?>" style="width:64px"></td><?php endforeach; ?>
          <td><?php if ($cricket): ?><input class="form-control form-control-sm" name="<?= $k ?>[nrr]" value="<?= e((string) $s['nrr']) ?>" aria-label="NRR" style="width:80px"><?php else: ?><input class="form-control form-control-sm" name="<?= $k ?>[gd]" value="<?= e((string) $s['gd']) ?>" aria-label="गोल अंतर" style="width:70px"><?php endif; ?></td>
          <td><input class="form-control form-control-sm" type="number" name="<?= $k ?>[sort_order]" value="<?= (int) $s['sort_order'] ?>" aria-label="क्रम" style="width:60px"></td>
          <td><input class="form-check-input" type="checkbox" name="<?= $k ?>[remove]" value="1" aria-label="हटाएँ"></td></tr>
      <?php endforeach; ?>
      <?php if (!$standings): ?><tr><td colspan="11" class="text-body-secondary">टेबल ख़ाली। नीचे से टीम जोड़ें या "नतीजों से गिनें"।</td></tr><?php endif; ?></tbody>
    </table></div>
    <?php if (can('sports.edit')): ?>
    <div class="panel-body d-flex flex-wrap gap-2 align-items-end border-top">
      <div><label class="form-label small mb-1" for="add_team">टीम जोड़ें</label><select class="form-select form-select-sm" id="add_team" name="add_team"><option value="">—</option><?php foreach ($teams as $tm): ?><option value="<?= (int) $tm['id'] ?>"><?= e($tm['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="form-label small mb-1" for="add_group">ग्रुप</label><input class="form-control form-control-sm" id="add_group" name="add_group" style="width:80px"></div>
      <button class="btn btn-sm btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> टेबल सेव करें</button>
    </div>
    <?php endif; ?>
  </form>
</section>
<?php if (can('sports.delete')): ?>
<div class="danger-zone mt-3"><div><b>टूर्नामेंट हटाएँ</b><p class="mb-0 small">मैच बचे रहेंगे (बिना टूर्नामेंट के)।</p></div><?= delete_button(route('admin.sports.tournaments.destroy', ['id' => $t['id']]), 'टूर्नामेंट और पॉइंट्स टेबल हट जाएगी।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
<?php endif; ?>
