<?php
/** टैली: $e, $tally, $progress, $compact (होम ब्लॉक) */
use App\Services\ElectionService as ES;
$seats = max(1, (int) $progress['seats']);
$maj = (int) $e['majority'];
$top = array_slice($tally, 0, !empty($compact) ? 5 : 12);
?>
<div class="el-tally"<?= $e['status'] === 'counting' ? ' data-el-live="' . e(route('elections.live', ['slug' => $e['slug']])) . '"' : '' ?>>
  <div class="el-prog">
    <?php if ($e['status'] === 'counting'): ?><span class="live-dot">लाइव</span><?php endif; ?>
    <span><b data-el="trends"><?= num($progress['trends']) ?></b>/<?= num($seats) ?> सीटों के रुझान · घोषित <b data-el="declared"><?= num($progress['declared']) ?></b></span>
    <?php if ($maj): ?><span>बहुमत: <b><?= num($maj) ?></b></span><?php endif; ?>
  </div>
  <div class="el-bar" role="img" aria-label="पार्टी-वार सीटें">
    <?php foreach ($tally as $p): if (!$p['total']) continue; ?><i data-el-seg="<?= e($p['short_name']) ?>" style="width:<?= e((string) round($p['total'] * 100 / $seats, 2)) ?>%;background:<?= e($p['color']) ?>" title="<?= e($p['short_name'] . ': ' . $p['total']) ?>"></i><?php endforeach; ?>
    <?php if ($maj): ?><span class="el-maj" style="left:<?= e((string) round($maj * 100 / $seats, 2)) ?>%" aria-hidden="true"></span><?php endif; ?>
  </div>
  <?php if ($top): ?>
  <table class="el-table">
    <thead><tr><th>पार्टी</th><th>जीते</th><th>आगे</th><th>कुल</th><?php if (empty($compact)): ?><th>वोट %</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($top as $p): ?>
      <tr data-el-party="<?= e($p['short_name']) ?>"><td><span class="party-dot" style="background:<?= e($p['color']) ?>"></span> <b><?= e($p['short_name']) ?></b><?php if (empty($compact)): ?> <small><?= e($p['name']) ?></small><?php endif; ?></td>
        <td data-k="won"><?= num($p['won']) ?></td><td data-k="leading"><?= num($p['leading']) ?></td><td class="el-total" data-k="total"><?= num($p['total']) ?></td><?php if (empty($compact)): ?><td data-k="share"><?= e((string) $p['share']) ?>%</td><?php endif; ?></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php else: ?><p class="el-empty">रुझान आने पर यहाँ पार्टी-वार सीटें दिखेंगी।</p><?php endif; ?>
</div>
