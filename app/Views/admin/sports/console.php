<?php
use App\Services\SportsService as SS;
$this->layout('layouts/admin');
$title = 'लाइव कंसोल: ' . ($m['team1_short'] ?? '') . ' बनाम ' . ($m['team2_short'] ?? '');
[$sl, $sc] = SS::MATCH_STATUSES[$m['status']];
$canEdit = can('sports.edit');
$winOpts = ['' => '—', 'draw' => 'ड्रॉ / टाई'];
if ($m['team1_id']) { $winOpts[(string) $m['team1_id']] = (string) $m['team1']; }
if ($m['team2_id']) { $winOpts[(string) $m['team2_id']] = (string) $m['team2']; }
$winNow = $m['is_draw'] ? 'draw' : (string) ($m['winner_id'] ?? '');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.sports.index')) ?>">खेल केंद्र</a></li><li class="breadcrumb-item active" aria-current="page">लाइव कंसोल</li></ol></nav>
    <h1><?= e(SS::title($m)) ?></h1>
    <p><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span> <?= e((string) $m['tournament']) ?> · <?= hindi_date($m['start_at'], true) ?><?= $m['venue'] ? ' · ' . e($m['venue']) : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= e(SS::url($m)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर</a>
    <?php if ($canEdit): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.sports.matches.edit', ['id' => $m['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> मैच बदलें</a><?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-5">
    <form class="panel" method="post" action="<?= e(route('admin.sports.score', ['id' => $m['id']])) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="panel-head"><h2>स्कोर और स्थिति</h2></div>
      <div class="panel-body">
        <?= field('select', 'status', 'स्थिति', $m['status'], ['options' => array_map(static fn($s) => $s[0], SS::MATCH_STATUSES)]) ?>
        <div class="row g-2">
          <div class="col-6"><?= field('text', 'score1', (string) ($m['team1_short'] ?: 'टीम 1'), $m['score1'] ?? '', ['placeholder' => $m['sport'] === 'cricket' ? '186/4 (18.2)' : '2', 'class' => 'form-control-lg fw-bold']) ?></div>
          <div class="col-6"><?= field('text', 'score2', (string) ($m['team2_short'] ?: 'टीम 2'), $m['score2'] ?? '', ['placeholder' => $m['sport'] === 'cricket' ? '—' : '1', 'class' => 'form-control-lg fw-bold']) ?></div>
        </div>
        <?= field('text', 'status_text', 'एक लाइन (जैसे: भारत को 12 गेंदों में 18 रन चाहिए)', $m['status_text'] ?? '') ?>
        <?= field('text', 'toss', 'टॉस', $m['toss'] ?? '') ?>
        <details<?= in_array($m['status'], ['completed', 'abandoned'], true) ? ' open' : '' ?>><summary class="fw-semibold mb-2">नतीजा</summary>
          <?= field('text', 'result', 'नतीजा', $m['result'] ?? '', ['placeholder' => 'जैसे: भारत 6 विकेट से जीता']) ?>
          <div class="row g-2"><div class="col-6"><?= field('select', 'winner', 'विजेता', $winNow, ['options' => $winOpts]) ?></div><div class="col-6"><?= field('text', 'potm', 'मैन ऑफ़ द मैच', $m['potm'] ?? '') ?></div></div>
        </details>
        <hr>
        <span class="form-label d-block">साथ में कमेंट्री (वैकल्पिक)</span>
        <div class="row g-2"><div class="col-4"><input class="form-control" name="c_marker" placeholder="<?= $m['sport'] === 'cricket' ? 'ओवर 18.2' : "मिनट 67'" ?>" aria-label="ओवर/मिनट"></div>
          <div class="col-8"><select class="form-select" name="c_type" aria-label="प्रकार"><?php foreach (SS::COMM as $k => [$l]): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div></div>
        <textarea class="form-control mt-2" name="c_text" rows="2" maxlength="1000" placeholder="क्या हुआ…" aria-label="कमेंट्री"></textarea>
        <?php if ($canEdit): ?><button class="btn btn-brand w-100 mt-3" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> स्कोर अपडेट करें</button><?php endif; ?>
        <p class="small text-body-secondary mt-2 mb-0">"पूरा" करने पर पॉइंट्स टेबल अपने आप बनती है। API से: <code>POST /api/v1/matches/<?= (int) $m['id'] ?></code></p>
      </div>
    </form>
  </div>
  <div class="col-xl-7">
    <section class="panel" data-comm-console data-url="<?= e(route('admin.sports.comment', ['id' => $m['id']])) ?>" data-del="<?= e(route('admin.sports.comment.delete', ['id' => $m['id'], 'cid' => 0])) ?>">
      <div class="panel-head"><h2>कमेंट्री</h2><span class="small text-body-secondary">Enter = जोड़ें</span></div>
      <?php if ($canEdit): ?>
      <form class="panel-body border-bottom comm-quick" data-comm-form>
        <div class="d-flex gap-2 flex-wrap">
          <input class="form-control" style="max-width:110px" name="marker" placeholder="<?= $m['sport'] === 'cricket' ? '18.3' : "68'" ?>" aria-label="ओवर/मिनट">
          <?php foreach (($m['sport'] === 'cricket' ? ['info', 'four', 'six', 'wicket', 'highlight'] : ['info', 'goal', 'yellow', 'red', 'sub', 'highlight']) as $i => $k): ?>
            <label class="comm-type"><input type="radio" name="type" value="<?= e($k) ?>"<?= $i === 0 ? ' checked' : '' ?>><span class="<?= e(SS::COMM[$k][1]) ?>"><?= e(SS::COMM[$k][0]) ?></span></label>
          <?php endforeach; ?>
        </div>
        <div class="d-flex gap-2 mt-2"><input class="form-control" name="text" maxlength="1000" placeholder="जैसे: बुमराह की यॉर्कर, स्टंप उखड़े!" aria-label="कमेंट्री" required><button class="btn btn-primary" type="submit">जोड़ें</button></div>
      </form>
      <?php endif; ?>
      <ul class="comm-list" data-comm-list>
        <?php foreach ($comments as $c): ?>
          <li data-id="<?= (int) $c['id'] ?>" class="<?= e(SS::COMM[$c['type']][1] ?? '') ?>"><span class="cm-mk"><?= e((string) $c['marker']) ?></span><div><?php if ($c['type'] !== 'info'): ?><b><?= e(SS::COMM[$c['type']][0] ?? '') ?></b> <?php endif; ?><?= e($c['text']) ?><small><?= date('H:i', strtotime($c['created_at'])) ?></small></div>
            <?php if ($canEdit): ?><button type="button" class="btn btn-sm btn-link text-danger p-0" data-comm-del aria-label="हटाएँ" title="हटाएँ"><i class="fa-solid fa-xmark"></i></button><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if (!$comments): ?><li class="text-body-secondary" data-empty>अभी कोई कमेंट्री नहीं।</li><?php endif; ?>
      </ul>
    </section>
  </div>
</div>
