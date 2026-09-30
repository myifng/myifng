<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/front');
$lead = $candidates[0] ?? null;
$hasVotes = $lead && (int) $lead['votes'] > 0;
$max = max(1, (int) ($lead['votes'] ?? 1));
?>
<div class="wrap page-wrap with-side">
  <article class="box article el-seat"<?= $live ? ' data-el-reload="60"' : '' ?>>
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <h1 class="page-title"><?= e($s['name']) ?> <small class="el-sub"><?= $s['number'] ? 'सीट नं. ' . (int) $s['number'] . ' · ' : '' ?><?= e((string) $s['district']) ?><?= $s['reserved'] !== 'gen' ? ' · ' . e(ES::RESERVED[$s['reserved']]) : '' ?></small></h1>
    <p class="el-meta">
      <?php if ($live): ?><span class="live-dot">लाइव</span><?php endif; ?>
      <span class="el-st el-st-<?= $s['status'] === 'declared' ? 'declared' : ($s['status'] === 'counting' ? 'counting' : 'upcoming') ?>"><?= e(ES::RESULT[$s['status']]) ?></span>
      <?= $s['total_rounds'] ? ' राउंड ' . (int) $s['rounds'] . '/' . (int) $s['total_rounds'] : '' ?>
      <?= $s['total_votes'] ? ' · कुल वोट ' . num((int) $s['total_votes']) : '' ?>
      <?= $s['electors'] && $s['total_votes'] ? ' · मतदान ' . e((string) round($s['total_votes'] * 100 / $s['electors'], 1)) . '%' : '' ?>
      · अपडेट <?= hindi_date($s['result_at'], true) ?>
    </p>
    <?php if ($hasVotes): ?>
      <div class="el-verdict" style="--c:<?= e((string) ($lead['party_color'] ?: '#888')) ?>">
        <b><?= e($lead['name']) ?></b> (<?= e((string) ($lead['party_short'] ?: 'निर्दलीय')) ?>) <?= $s['status'] === 'declared' ? 'जीते' : 'आगे' ?>,
        अंतर <b><?= num((int) $s['margin']) ?></b> वोट
      </div>
    <?php endif; ?>
    <ol class="el-cands">
      <?php foreach ($candidates as $i => $c): $isLead = $hasVotes && $i === 0; ?>
        <li class="<?= $c['withdrawn'] ? 'withdrawn' : '' ?>" style="--c:<?= e((string) ($c['party_color'] ?: '#888')) ?>">
          <span class="el-ph"><?= $c['photo'] ? media_img($c['photo'], 'thumb', $c['name']) : '<i class="fa-solid fa-user"></i>' ?></span>
          <div class="el-cinfo">
            <b><?= e($c['name']) ?></b> <?php if ($isLead): ?><span class="el-res el-<?= $s['status'] === 'declared' ? 'win' : 'lead' ?>"><?= $s['status'] === 'declared' ? 'जीते' : 'आगे' ?></span><?php endif; ?><?= $c['is_incumbent'] ? ' <small class="muted">(मौजूदा)</small>' : '' ?><?= $c['withdrawn'] ? ' <small class="muted">(नाम वापस)</small>' : '' ?>
            <small><?= e((string) ($c['party'] ?: 'निर्दलीय')) ?><?= $c['age'] ? ' · ' . (int) $c['age'] . ' वर्ष' : '' ?><?= $c['education'] ? ' · ' . e($c['education']) : '' ?></small>
            <?php if ($hasVotes && !$c['withdrawn']): ?><div class="bl-bar"><i style="width:<?= e((string) round($c['votes'] * 100 / $max, 1)) ?>%;background:var(--c)"></i></div><?php endif; ?>
          </div>
          <?php if ($hasVotes && !$c['withdrawn']): ?><div class="el-votes"><b><?= num((int) $c['votes']) ?></b><small><?= e((string) $c['share']) ?>%</small></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (!$candidates): ?><p class="empty">उम्मीदवारों की सूची जल्द।</p><?php endif; ?>

    <?php if ($history): ?>
      <section><h2>पिछले नतीजे</h2><div class="table-scroll"><table class="el-seats">
        <thead><tr><th>चुनाव</th><th>विजेता</th><th class="hide-sm">दूसरे नंबर</th><th>अंतर</th></tr></thead>
        <tbody><?php foreach ($history as $h): ?><tr><td><a href="<?= e(route('elections.show', ['slug' => $h['slug']])) ?>"><?= (int) $h['year'] ?></a></td>
          <td><span class="party-tag" style="--c:<?= e((string) $h['winner_color']) ?>"><?= e((string) $h['winner_party']) ?></span> <?= e((string) $h['winner']) ?></td>
          <td class="hide-sm"><?= e((string) $h['runner']) ?> <small class="muted"><?= e((string) $h['runner_party']) ?></small></td><td><?= num((int) $h['margin']) ?></td></tr><?php endforeach; ?></tbody>
      </table></div></section>
    <?php endif; ?>
    <?php if ($nearby): ?><section><h2><?= e((string) $s['district']) ?> की दूसरी सीटें</h2><nav class="chips"><?php foreach ($nearby as $n): ?><a href="<?= e(route('elections.seat', ['slug' => $e['slug'], 'seat' => $n['slug']])) ?>"><?= e($n['name']) ?></a><?php endforeach; ?></nav></section><?php endif; ?>
    <p class="el-note"><a href="<?= e(ES::url($e)) ?>"><i class="fa-solid fa-angle-left"></i> <?= e($e['name']) ?>: सभी सीटें</a><?= $s['district_path'] ? ' · <a href="' . e(url($s['district_path'])) . '">' . e((string) $s['district']) . ' की ख़बरें</a>' : '' ?></p>
  </article>
  <?= $this->insert('partials/front/sidebar', ['side' => $side]) ?>
</div>
