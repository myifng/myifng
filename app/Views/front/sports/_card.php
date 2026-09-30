<?php
use App\Services\SportsService as SS;
/** $m मैच (MATCH_COLS) */
$st = $m['status'];
$isLive = in_array($st, ['live', 'break'], true);
$w = (int) ($m['winner_id'] ?? 0);
$logo = static fn($l, $n, $c) => $l ? media_img($l, 'thumb', $n) : '<span style="background:' . e((string) $c) . '">' . e(mb_substr((string) $n, 0, 1)) . '</span>';
?>
<a class="sc-card<?= $isLive ? ' is-live' : '' ?>" href="<?= e(SS::url($m)) ?>">
  <span class="sc-top"><?php if ($isLive): ?><span class="live-dot"><?= e(SS::MATCH_STATUSES[$st][0]) ?></span><?php else: ?><span class="sc-st"><?= e(SS::MATCH_STATUSES[$st][0]) ?></span><?php endif; ?>
    <small><?= e(trim(($m['tournament'] ?? '') . ($m['title'] ? ' · ' . $m['title'] : ''), ' ·')) ?></small></span>
  <?php foreach ([1, 2] as $i): $tid = (int) $m['team' . $i . '_id']; ?>
    <span class="sc-team<?= $w && $w === $tid ? ' won' : '' ?>"><span class="sc-logo"><?= $logo($m['team' . $i . '_logo'], $m['team' . $i], $m['team' . $i . '_color']) ?></span><b><?= e((string) ($m['team' . $i . '_short'] ?? '?')) ?></b><span class="sc-score"><?= e((string) $m['score' . $i]) ?></span></span>
  <?php endforeach; ?>
  <span class="sc-foot"><?= $m['result'] ? e($m['result']) : ($m['status_text'] ? e($m['status_text']) : ($st === 'scheduled' ? '<i class="fa-regular fa-clock"></i> ' . hindi_date($m['start_at'], true) : '')) ?></span>
</a>
