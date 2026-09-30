<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/admin');
$title = $s['name'] . ' · ' . $e['name'];
$pOpts = array_column($parties, 'short_name', 'id');
$old = old('c');
$rows = is_array($old) && $old ? $old : $candidates;
$canEdit = can('elections.edit');
$row = static function (int|string $k, array $c) use ($pOpts): string {
    $n = 'c[' . $k . ']';
    $sel = '<select class="form-select form-select-sm" name="' . $n . '[party_id]" aria-label="पार्टी"><option value="">निर्दलीय</option>';
    foreach ($pOpts as $id => $l) {
        $sel .= '<option value="' . (int) $id . '"' . selected($id, $c['party_id'] ?? '') . '>' . e($l) . '</option>';
    }
    $sel .= '</select>';
    $g = '<select class="form-select form-select-sm" name="' . $n . '[gender]" aria-label="लिंग"><option value="">—</option>';
    foreach (['m' => 'पुरुष', 'f' => 'महिला', 'o' => 'अन्य'] as $gk => $gl) {
        $g .= '<option value="' . $gk . '"' . selected($gk, $c['gender'] ?? '') . '>' . $gl . '</option>';
    }
    $g .= '</select>';
    $cb = static fn(string $f, string $l) => '<label class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" name="' . $n . '[' . $f . ']" value="1"' . checked(!empty($c[$f])) . '> ' . $l . '</label>';
    return '<tr data-cand-row><td><input type="hidden" name="' . $n . '[id]" value="' . (int) ($c['id'] ?? 0) . '"><input type="hidden" name="' . $n . '[photo]" value="' . e((string) ($c['photo'] ?? '')) . '">'
        . '<input class="form-control form-control-sm" name="' . $n . '[name]" value="' . e((string) ($c['name'] ?? '')) . '" placeholder="उम्मीदवार का नाम" aria-label="नाम" maxlength="150">'
        . '<div class="d-flex flex-wrap gap-1 mt-1">' . $cb('is_incumbent', 'मौजूदा') . $cb('is_key', 'बड़ा चेहरा') . $cb('withdrawn', 'नाम वापस') . (!empty($c['id']) ? $cb('delete', '<span class="text-danger">हटाएँ</span>') : '') . '</div></td>'
        . '<td style="min-width:110px">' . $sel . '</td>'
        . '<td style="min-width:130px"><input class="form-control form-control-lg text-end fw-bold vote-input" name="' . $n . '[votes]" value="' . e((string) ($c['votes'] ?? '0')) . '" inputmode="numeric" pattern="[0-9]*" aria-label="वोट"></td>'
        . '<td class="d-none d-lg-table-cell" style="min-width:210px"><div class="d-flex gap-1"><input class="form-control form-control-sm" name="' . $n . '[age]" value="' . e((string) ($c['age'] ?? '')) . '" placeholder="उम्र" aria-label="उम्र" style="max-width:70px">' . $g . '</div>'
        . '<input class="form-control form-control-sm mt-1" name="' . $n . '[education]" value="' . e((string) ($c['education'] ?? '')) . '" placeholder="शिक्षा" aria-label="शिक्षा"></td></tr>';
};
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.elections.index')) ?>">चुनाव केंद्र</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.elections.show', ['id' => $e['id']])) ?>"><?= e($e['name']) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= e($s['name']) ?></li></ol></nav>
    <h1><?= $s['number'] ? (int) $s['number'] . '. ' : '' ?><?= e($s['name']) ?> <?= $s['reserved'] !== 'gen' ? '<small class="badge text-bg-light border">' . e(ES::RESERVED[$s['reserved']]) . '</small>' : '' ?></h1>
    <p>कुल वोट <?= num((int) $s['total_votes']) ?><?= $s['margin'] ? ' · अंतर ' . num((int) $s['margin']) : '' ?> · आख़िरी अपडेट <?= time_ago($s['result_at']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <?php if ($prev): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.elections.seat', ['id' => $e['id'], 'seat' => $prev['id']])) ?>"><i class="fa-solid fa-angle-left me-1"></i><?= e($prev['name']) ?></a><?php endif; ?>
    <?php if ($next): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.elections.seat', ['id' => $e['id'], 'seat' => $next['id']])) ?>"><?= e($next['name']) ?><i class="fa-solid fa-angle-right ms-1"></i></a><?php endif; ?>
  </div>
</div>
<form method="post" action="<?= e(route('admin.elections.seat.save', ['id' => $e['id'], 'seat' => $s['id']])) ?>" novalidate data-unsaved>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-xl-9"><section class="panel">
      <div class="panel-head"><h2>उम्मीदवार और वोट</h2><?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-outline-primary" data-cand-add><i class="fa-solid fa-plus me-1"></i>उम्मीदवार</button><?php endif; ?></div>
      <?php if (error('c')): ?><div class="alert alert-danger m-3 mb-0"><?= e(error('c')) ?></div><?php endif; ?>
      <div class="table-responsive"><table class="table align-middle mb-0 cand-table">
        <thead><tr><th>उम्मीदवार</th><th>पार्टी</th><th class="text-end">वोट</th><th class="d-none d-lg-table-cell">ब्योरा</th></tr></thead>
        <tbody data-cand-body><?php foreach (array_values($rows) as $i => $c): ?><?= $row($i, (array) $c) ?><?php endforeach; ?><?php if (!$rows): ?><?= $row(0, []) ?><?= $row(1, []) ?><?php endif; ?></tbody>
      </table></div>
      <template data-cand-tpl><?= $row('__i__', []) ?></template>
      <?php if ($candidates && array_sum(array_column($candidates, 'votes'))): ?>
        <div class="panel-body border-top small"><?php foreach ($candidates as $c): if ($c['withdrawn']) continue; ?><span class="me-3"><span class="party-dot" style="background:<?= e((string) ($c['party_color'] ?: '#999')) ?>"></span> <?= e($c['name']) ?>: <b><?= e((string) $c['share']) ?>%</b></span><?php endforeach; ?></div>
      <?php endif; ?>
    </section></div>
    <div class="col-xl-3"><section class="panel sticky-xl"><div class="panel-body">
      <?= field('select', 'status', 'नतीजा', $s['status'], ['options' => ES::RESULT]) ?>
      <div class="row g-2"><div class="col-6"><?= field('number', 'rounds', 'राउंड', $s['rounds']) ?></div><div class="col-6"><?= field('number', 'total_rounds', 'कुल राउंड', $s['total_rounds']) ?></div></div>
      <?= field('number', 'electors', 'कुल मतदाता (मतदान % के लिए)', $s['electors'] ?? '') ?>
      <?php if ($canEdit): ?>
        <button class="btn btn-brand w-100 mb-2" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> सेव करें</button>
        <?php if ($next): ?><button class="btn btn-outline-primary w-100" type="submit" name="go_next" value="<?= (int) $next['id'] ?>">सेव करके अगली सीट <i class="fa-solid fa-angle-right ms-1"></i></button><?php endif; ?>
      <?php endif; ?>
      <p class="small text-body-secondary mt-2 mb-0">सबसे ज़्यादा वोट वाला "आगे" माना जाता है; "घोषित" करने पर "जीते"।</p>
    </div></section>
    <?php if ($history): ?><section class="panel mt-3"><div class="panel-head"><h2>पिछले नतीजे</h2></div><ul class="list-group list-group-flush small">
      <?php foreach ($history as $h): ?><li class="list-group-item"><b><?= (int) $h['year'] ?></b> · <span class="party-dot" style="background:<?= e((string) $h['winner_color']) ?>"></span> <?= e((string) $h['winner']) ?> (<?= e((string) $h['winner_party']) ?>) · अंतर <?= num((int) $h['margin']) ?></li><?php endforeach; ?>
    </ul></section><?php endif; ?>
    </div>
  </div>
</form>
<?php if (can('elections.delete')): ?>
<div class="danger-zone mt-3"><div><b>सीट इस चुनाव से हटाएँ</b><p class="mb-0 small">उम्मीदवार और नतीजा हटेंगे; सीट पिछले चुनावों के लिए बची रहेगी।</p></div><?= delete_button(route('admin.elections.seats.remove', ['id' => $e['id'], 'seat' => $s['id']]), 'इस सीट के उम्मीदवार और नतीजा हट जाएँगे।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
<?php endif; ?>
