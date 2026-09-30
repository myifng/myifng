<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/admin');
$title = $e['name'];
[$sl, $sc] = ES::STATUSES[$e['status']];
$maj = (int) $e['majority'];
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.elections.index')) ?>">चुनाव केंद्र</a></li><li class="breadcrumb-item active" aria-current="page">काउंटिंग डेस्क</li></ol></nav>
    <h1><?= e($e['name']) ?></h1>
    <p><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span> · <?= num($progress['seats']) ?> सीटें · रुझान <?= num($progress['trends']) ?> · घोषित <?= num($progress['declared']) ?><?= $maj ? ' · बहुमत ' . num($maj) : '' ?><?= $e['synced_at'] ? ' · API अपडेट ' . time_ago($e['synced_at']) : '' ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($e['status'] !== 'draft'): ?><a class="btn btn-outline-secondary" href="<?= e(ES::url($e)) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> वेबसाइट पर</a><?php endif; ?>
    <?php if (can('elections.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.elections.edit', ['id' => $e['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> चुनाव बदलें</a><?php endif; ?>
  </div>
</div>
<?= $this->insert('admin/elections/_nav', ['active' => 'index']) ?>

<div class="row g-3 mb-3">
  <div class="col-xl-8">
    <section class="panel h-100"><div class="panel-head"><h2>टैली (जीते + आगे)</h2></div>
      <?php if ($tally): ?><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>पार्टी</th><th class="text-end">जीते</th><th class="text-end">आगे</th><th class="text-end">कुल</th><th class="text-end">वोट %</th></tr></thead>
        <tbody><?php foreach ($tally as $p): ?><tr><td><span class="party-dot" style="background:<?= e($p['color']) ?>"></span> <b><?= e($p['short_name']) ?></b> <small class="text-body-secondary"><?= e((string) $p['alliance']) ?></small></td>
          <td class="text-end"><?= num($p['won']) ?></td><td class="text-end"><?= num($p['leading']) ?></td><td class="text-end fw-bold<?= $maj && $p['total'] >= $maj ? ' text-success' : '' ?>"><?= num($p['total']) ?></td><td class="text-end"><?= e((string) $p['share']) ?>%</td></tr><?php endforeach; ?></tbody>
      </table></div><?php else: ?><div class="panel-body small text-body-secondary">अभी कोई रुझान नहीं। सीट खोलकर वोट भरें या JSON इम्पोर्ट करें।</div><?php endif; ?>
    </section>
  </div>
  <div class="col-xl-4 d-grid gap-3 align-content-start">
    <?php if (can('elections.edit')): ?>
    <section class="panel"><div class="panel-head"><h2>सीट जोड़ें</h2></div>
      <form class="panel-body" method="post" action="<?= e(route('admin.elections.seats.add', ['id' => $e['id']])) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="row g-2"><div class="col-8"><?= field('text', 'name', 'सीट का नाम', '', ['required' => true, 'fresh' => false]) ?></div><div class="col-4"><?= field('number', 'number', 'नंबर', '') ?></div></div>
        <div class="row g-2"><div class="col-5"><?= field('select', 'reserved', 'आरक्षण', 'gen', ['options' => ES::RESERVED]) ?></div>
          <div class="col-7"><?= field('select', 'district_id', 'ज़िला', '', ['options' => array_column($districts, 'name', 'id'), 'empty' => '—']) ?></div></div>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-plus me-1"></i> जोड़ें</button>
      </form>
    </section>
    <section class="panel"><div class="panel-head"><h2>CSV / JSON इम्पोर्ट</h2></div>
      <div class="panel-body">
        <details><summary class="fw-semibold">CSV: सीटें और उम्मीदवार</summary>
          <form method="post" action="<?= e(route('admin.elections.import', ['id' => $e['id']])) ?>" enctype="multipart/form-data" class="mt-2">
            <?= csrf_field() ?>
            <p class="small text-body-secondary mb-1">कॉलम: <code>seat_no,seat,district,reserved,candidate,party,age,gender,education,incumbent,key</code> · party = छोटा नाम (BJP), gender = m/f/o, incumbent/key = 1</p>
            <textarea class="form-control font-monospace small mb-2" name="csv" rows="4" placeholder="seat_no,seat,district,reserved,candidate,party&#10;322,गोरखपुर शहर,गोरखपुर,gen,रमेश कुमार,BJP"></textarea>
            <input class="form-control form-control-sm mb-2" type="file" name="csv_file" accept=".csv,text/csv" aria-label="CSV फ़ाइल">
            <button class="btn btn-sm btn-outline-primary" type="submit">इम्पोर्ट करें</button>
          </form>
        </details>
        <details class="mt-2"><summary class="fw-semibold">JSON: वोट (एजेंसी फ़ाइल)</summary>
          <form method="post" action="<?= e(route('admin.elections.import_json', ['id' => $e['id']])) ?>" class="mt-2">
            <?= csrf_field() ?>
            <textarea class="form-control font-monospace small mb-2" name="json" rows="5" placeholder='{"seats":[{"seat":"322","status":"counting","rounds":4,"total_rounds":22,"candidates":[{"name":"रमेश कुमार","votes":15230}]}]}'></textarea>
            <button class="btn btn-sm btn-outline-primary" type="submit">वोट अपडेट करें</button>
          </form>
          <p class="small text-body-secondary mt-2 mb-0">यही JSON API से भी: <code>POST /api/v1/elections/<?= e($e['slug']) ?>/results</code> (सेटिंग → डेटा API)।</p>
        </details>
      </div>
    </section>
    <?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-head"><h2>सीटें (<?= num(count($seats)) ?>)</h2></div>
  <form class="panel-body border-bottom" method="get"><div class="row g-2">
    <div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="सीट या उम्मीदवार" aria-label="खोजें"></div>
    <div class="col-6 col-md-3"><select class="form-select" name="status" aria-label="स्थिति"><option value="">सभी स्थितियाँ</option><?php foreach (ES::RESULT as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $f['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="party" aria-label="पार्टी"><option value="">आगे: सभी</option><?php foreach ($parties as $p): ?><option value="<?= (int) $p['id'] ?>"<?= selected($p['id'], $f['party']) ?>><?= e($p['short_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" type="submit">छाँटें</button></div>
  </div></form>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>#</th><th>सीट</th><th>आगे / जीते</th><th class="d-none d-md-table-cell">दूसरे नंबर</th><th class="text-end">अंतर</th><th class="d-none d-lg-table-cell">स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($seats as $s): ?>
      <tr>
        <td class="text-body-secondary"><?= $s['number'] ? (int) $s['number'] : '—' ?></td>
        <td><a class="fw-semibold text-reset" href="<?= e(route('admin.elections.seat', ['id' => $e['id'], 'seat' => $s['seat_id']])) ?>"><?= e($s['seat']) ?></a>
          <div class="small text-body-secondary"><?= e((string) $s['district']) ?><?= $s['reserved'] !== 'gen' ? ' · ' . e(ES::RESERVED[$s['reserved']]) : '' ?> · <?= num($cands[(int) $s['seat_id']] ?? 0) ?> उम्मीदवार</div></td>
        <td><?php if ($s['leader']): ?><span class="party-dot" style="background:<?= e((string) $s['leader_color']) ?>"></span> <?= e($s['leader']) ?> <small class="text-body-secondary"><?= e((string) $s['leader_party']) ?></small><?php else: ?><span class="text-body-secondary">—</span><?php endif; ?></td>
        <td class="d-none d-md-table-cell small"><?= $s['runner'] ? e($s['runner']) . ' <span class="text-body-secondary">' . e((string) $s['runner_party']) . '</span>' : '—' ?></td>
        <td class="text-end"><?= $s['leader'] ? num((int) $s['margin']) : '—' ?></td>
        <td class="d-none d-lg-table-cell"><span class="badge <?= $s['status'] === 'declared' ? 'text-bg-success' : ($s['status'] === 'counting' ? 'text-bg-warning' : 'text-bg-light border') ?>"><?= e(ES::RESULT[$s['status']]) ?></span><?= $s['total_rounds'] ? ' <small class="text-body-secondary">' . (int) $s['rounds'] . '/' . (int) $s['total_rounds'] . '</small>' : '' ?></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.elections.seat', ['id' => $e['id'], 'seat' => $s['seat_id']])) ?>"><i class="fa-solid fa-pen-to-square me-1"></i>वोट</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$seats): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-map"></i><p>कोई सीट नहीं। ऊपर से जोड़ें या CSV इम्पोर्ट करें।</p></div></td></tr><?php endif; ?></tbody>
  </table></div>
</section>
