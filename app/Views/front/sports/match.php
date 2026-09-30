<?php
use App\Services\SportsService as SS;
$this->layout('layouts/front');
$w = (int) ($m['winner_id'] ?? 0);
$lastId = $comm ? (int) $comm[0]['id'] : 0;
$logo = static fn($l, $n, $c) => $l ? media_img($l, 'thumb', $n) : '<span style="background:' . e((string) $c) . '">' . e(mb_substr((string) $n, 0, 1)) . '</span>';
?>
<div class="wrap page-wrap with-side">
  <article class="box article sp-match"<?= $isLive ? ' data-sc-match="' . e(route('sports.live', ['id' => $m['id']])) . '" data-after="' . $lastId . '"' : '' ?>>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <h1 class="page-title"><?= e(SS::title($m)) ?></h1>
    <p class="el-meta"><?= e((string) $m['stage']) ?><?= $m['stage'] ? ' · ' : '' ?><?= hindi_date($m['start_at'], true) ?><?= $m['venue'] ? ' · <i class="fa-solid fa-location-dot"></i> ' . e($m['venue']) : '' ?></p>
    <div class="sp-board">
      <div class="sp-status" data-sc="status"><?= $isLive ? '<span class="live-dot">' . e(SS::MATCH_STATUSES[$m['status']][0]) . '</span>' : '<span class="el-st">' . e(SS::MATCH_STATUSES[$m['status']][0]) . '</span>' ?></div>
      <?php foreach ([1, 2] as $i): $tid = (int) $m['team' . $i . '_id']; ?>
        <div class="sp-row<?= $w && $w === $tid ? ' won' : '' ?>">
          <span class="sc-logo lg"><?= $logo($m['team' . $i . '_logo'], $m['team' . $i], $m['team' . $i . '_color']) ?></span>
          <a href="<?= e(route('sports.team', ['slug' => (string) $m['team' . $i . '_slug']])) ?>"><b><?= e((string) $m['team' . $i]) ?></b></a>
          <span class="sp-score" data-sc="score<?= $i ?>"><?= e((string) $m['score' . $i]) ?></span>
        </div>
      <?php endforeach; ?>
      <p class="sp-line" data-sc="line"><?= e((string) ($m['result'] ?: $m['status_text'])) ?></p>
      <?php if ($m['toss'] || $m['potm']): ?><p class="sp-extra"><?= $m['toss'] ? 'टॉस: ' . e($m['toss']) : '' ?><?= $m['toss'] && $m['potm'] ? ' · ' : '' ?><?= $m['potm'] ? 'मैन ऑफ़ द मैच: <b>' . e($m['potm']) . '</b>' : '' ?></p><?php endif; ?>
    </div>
    <?= $this->insert('partials/front/share', ['shareUrl' => SS::url($m), 'shareTitle' => SS::title($m)]) ?>
    <?php if ($report): ?><section class="fc-related"><h2>मैच रिपोर्ट</h2><?= news_card($report, 'wide', ['h' => 'h3']) ?></section><?php endif; ?>
    <?php if ($comm || $isLive): ?>
      <section class="sp-comm"><h2>कमेंट्री <?= $isLive ? '<small class="muted">(अपने आप अपडेट)</small>' : '' ?></h2>
        <ul class="comm-feed" data-sc="comm">
          <?php foreach ($comm as $c): [$cl, $cc] = SS::COMM[$c['type']] ?? SS::COMM['info']; ?>
            <li class="<?= e($cc) ?>"><span class="cm-mk"><?= e((string) $c['marker']) ?></span><div><?php if ($c['type'] !== 'info'): ?><b class="cm-tag"><?= e($cl) ?></b> <?php endif; ?><?= e($c['text']) ?></div></li>
          <?php endforeach; ?>
          <?php if (!$comm): ?><li class="muted" data-empty>कमेंट्री जल्द शुरू होगी।</li><?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>
    <?php if ($squad1 || $squad2): ?>
      <section class="sp-squads"><h2>टीमें</h2><div class="sp-sq">
        <?php foreach ([[$m['team1'], $squad1], [$m['team2'], $squad2]] as [$tn, $sq]): if (!$sq) continue; ?>
          <div><h3><?= e((string) $tn) ?></h3><ul><?php foreach ($sq as $p): ?><li><?= e($p['name']) ?><?= $p['is_captain'] ? ' <small>(कप्तान)</small>' : '' ?><?= $p['role'] ? ' <small class="muted">' . e($p['role']) . '</small>' : '' ?></li><?php endforeach; ?></ul></div>
        <?php endforeach; ?>
      </div></section>
    <?php endif; ?>
    <?php if ($more): ?><section><h2>इस टूर्नामेंट के और मैच</h2><div class="sc-grid"><?php foreach ($more as $x): ?><?= $this->insert('front/sports/_card', ['m' => $x]) ?><?php endforeach; ?></div></section><?php endif; ?>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
