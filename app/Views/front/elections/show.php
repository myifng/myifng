<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/front');
[$sl] = ES::STATUSES[$e['status']];
$statusLabel = static fn(array $s) => $s['status'] === 'declared' ? 'जीते' : ($s['status'] === 'counting' ? 'आगे' : '');
?>
<div class="wrap page-wrap el-page">
  <header class="box el-head">
    <nav class="crumb" aria-label="ब्रेडक्रम्ब"><?php foreach ($crumbs as $i => [$n, $cu]): if (!$cu) continue; ?><?= $i ? ' <span aria-hidden="true">›</span> ' : '' ?><a href="<?= e($cu) ?>"><?= e($n) ?></a><?php endforeach; ?></nav>
    <h1 class="page-title"><?= e($e['name']) ?></h1>
    <p class="el-meta"><span class="el-st el-st-<?= e($e['status']) ?>"><?= e($sl) ?></span> <?= e(ES::TYPES[$e['type']]) ?><?= $e['state'] ? ' · ' . e($e['state']) : '' ?><?= $e['poll_dates'] ? ' · मतदान: ' . e($e['poll_dates']) : '' ?><?= $e['counting_date'] ? ' · मतगणना: ' . hindi_date($e['counting_date']) : '' ?></p>
    <?php if ($e['description']): ?><p><?= e($e['description']) ?></p><?php endif; ?>
  </header>

  <div class="el-grid">
    <section class="box"><div class="bhead"><h2>पार्टी-वार स्थिति</h2></div>
      <?= $this->insert('front/elections/_tally', ['e' => $e, 'tally' => $tally, 'progress' => $progress]) ?>
      <?php if ($progress['turnout']): ?><p class="el-note">मतदान: <b><?= e((string) $progress['turnout']) ?>%</b> (जिन सीटों का डेटा है)</p><?php endif; ?>
    </section>
    <?php if (count($alliances) > 1): ?>
    <section class="box"><div class="bhead"><h2>गठबंधन</h2></div>
      <div class="el-alliances"><?php foreach ($alliances as $a): ?><div class="el-al" style="--c:<?= e(readable_color($a['color'])) ?>"><b><?= e($a['name']) ?></b><span class="el-big"><?= num($a['total']) ?></span><small>जीते <?= num($a['won']) ?> · आगे <?= num($a['leading']) ?> · वोट <?= e((string) $a['share']) ?>%</small></div><?php endforeach; ?></div>
    </section>
    <?php endif; ?>
  </div>

  <?php if ($key): ?>
  <section class="box"><div class="bhead"><h2>बड़े चेहरे</h2></div>
    <div class="el-key"><?php foreach ($key as $c): $st = $c['status'] === 'declared' ? ((int) $c['leader_id'] === (int) $c['id'] ? ['जीते', 'win'] : ['हारे', 'lose']) : ($c['status'] === 'counting' ? ((int) $c['leader_id'] === (int) $c['id'] ? ['आगे', 'lead'] : ['पीछे', 'trail']) : ['', '']); ?>
      <a class="el-kc" href="<?= e(route('elections.seat', ['slug' => $e['slug'], 'seat' => $c['seat_slug']])) ?>" style="--c:<?= e(readable_color((string) ($c['color'] ?: '#888'))) ?>">
        <span class="el-ph"><?= $c['photo'] ? media_img($c['photo'], 'thumb', $c['name']) : '<i class="fa-solid fa-user"></i>' ?></span>
        <b><?= e($c['name']) ?></b><small><?= e((string) $c['party']) ?> · <?= e($c['seat']) ?></small>
        <?php if ($st[0]): ?><span class="el-res el-<?= $st[1] ?>"><?= $st[0] ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?></div>
  </section>
  <?php endif; ?>

  <section class="box"><div class="bhead"><h2>सीट-वार नतीजे</h2></div>
    <div class="el-filter">
      <input type="search" placeholder="सीट या उम्मीदवार खोजें" aria-label="सीट या उम्मीदवार खोजें" data-el-search>
      <?php if ($districts): ?><select aria-label="ज़िला" data-el-district><option value="">सभी ज़िले</option><?php foreach ($districts as $d): ?><option value="<?= e($d['name']) ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    </div>
    <div class="table-scroll"><table class="el-seats">
      <thead><tr><th>सीट</th><th>आगे / जीते</th><th class="hide-sm">दूसरे नंबर</th><th>अंतर</th></tr></thead>
      <tbody><?php foreach ($seats as $s): ?>
        <tr data-el-row data-district="<?= e((string) $s['district']) ?>" data-text="<?= e(mb_strtolower($s['seat'] . ' ' . $s['leader'] . ' ' . $s['runner'] . ' ' . $s['district'])) ?>">
          <td><a href="<?= e(route('elections.seat', ['slug' => $e['slug'], 'seat' => $s['slug']])) ?>"><b><?= e($s['seat']) ?></b></a><small><?= e((string) $s['district']) ?><?= $s['reserved'] !== 'gen' ? ' · ' . e(ES::RESERVED[$s['reserved']]) : '' ?></small></td>
          <td><?php if ($s['leader']): ?><span class="party-tag" style="--c:<?= e(readable_color((string) $s['leader_color'])) ?>"><?= e((string) $s['leader_party']) ?></span> <?= e($s['leader']) ?> <small class="el-res-s"><?= $statusLabel($s) ?></small><?php else: ?><span class="muted">इंतज़ार</span><?php endif; ?></td>
          <td class="hide-sm"><?= $s['runner'] ? '<span class="party-tag" style="--c:' . e(readable_color((string) $s['runner_color'])) . '">' . e((string) $s['runner_party']) . '</span> ' . e($s['runner']) : '—' ?></td>
          <td><?= $s['leader'] ? num((int) $s['margin']) : '—' ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php if (!$seats): ?><p class="empty">सीटों की सूची जल्द।</p><?php endif; ?>
  </section>

  <?php if ($news): ?><section class="box"><?= block_head('चुनाव की ख़बरें') ?><div class="cgrid cols-4"><?php foreach ($news as $n): ?><?= news_card($n, 'card') ?><?php endforeach; ?></div></section><?php endif; ?>
  <?php if ($e['source_note']): ?><p class="el-note">डेटा स्रोत: <?= e($e['source_note']) ?><?= $e['synced_at'] ? ' · अपडेट ' . hindi_date($e['synced_at'], true) : '' ?></p><?php endif; ?>
</div>
