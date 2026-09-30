<?php /** पॉइंट्स टेबल: $rows, $cricket */ ?>
<div class="table-scroll"><table class="pt-table">
  <thead><tr><th>#</th><th>टीम</th><th>खेले</th><th>जीते</th><th>हारे</th><th class="hide-sm">ड्रॉ</th><th class="hide-sm">बेनतीजा</th><th><?= $cricket ? 'NRR' : 'GD' ?></th><th>अंक</th></tr></thead>
  <tbody><?php foreach ($rows as $i => $s): ?><tr><td><?= num($i + 1) ?></td><td><a href="<?= e(route('sports.team', ['slug' => $s['slug']])) ?>"><span class="party-dot" style="background:<?= e($s['color']) ?>"></span> <b><?= e($s['short_name']) ?></b> <span class="hide-sm"><?= e($s['name']) ?></span></a></td>
    <td><?= (int) $s['played'] ?></td><td><?= (int) $s['won'] ?></td><td><?= (int) $s['lost'] ?></td><td class="hide-sm"><?= (int) $s['drawn'] ?></td><td class="hide-sm"><?= (int) $s['no_result'] ?></td>
    <td><?= $cricket ? ($s['nrr'] !== null ? e(sprintf('%+.3f', (float) $s['nrr'])) : '—') : ($s['gd'] !== null ? e(sprintf('%+d', (int) $s['gd'])) : '—') ?></td><td><b><?= (int) $s['points'] ?></b></td></tr><?php endforeach; ?></tbody>
</table></div>
