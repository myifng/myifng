<?php
use App\Models\ReporterApplication;
use App\Models\ReporterDocument;
use App\Services\NewsWorkflow;
$this->layout('layouts/admin');
$title = $user['name'] . ' · रिपोर्टर';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.reporters.index')) ?>">रिपोर्टर</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($r['reporter_code']) ?></li></ol></nav>
    <h1>रिपोर्टर प्रोफ़ाइल</h1>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (can('reporters.edit')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.reporters.edit', ['id' => $r['id']])) ?>"><i class="fa-solid fa-pen me-1"></i> बदलें</a><?php endif; ?>
    <?php if (can('news.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.news.index')) ?>?reporter=<?= (int) $r['user_id'] ?>"><i class="fa-regular fa-newspaper me-1"></i> ख़बरें</a><?php endif; ?>
    <?php if ($r['application_id'] && can('applications.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.applications.show', ['id' => $r['application_id']])) ?>"><i class="fa-solid fa-file-signature me-1"></i> आवेदन</a><?php endif; ?>
  </div>
</div>
<?php if ($passwordLink): ?>
  <div class="alert alert-info"><b>पासवर्ड बनाने का लिंक (सिर्फ़ अभी दिखेगा, 72 घंटे मान्य):</b>
    <div class="input-group input-group-sm mt-2"><input class="form-control font-monospace" id="pwLink" value="<?= e($passwordLink) ?>" readonly><button class="btn btn-outline-secondary" type="button" data-copy="#pwLink">कॉपी</button></div>
    <div class="small mt-1">ईमेल न पहुँचे तो यह लिंक रिपोर्टर को WhatsApp पर भेजें।</div></div>
<?php endif; ?>
<?= $this->insert('admin/reporters/_profile', get_defined_vars()) ?>
<div class="row g-3 mt-1">
  <div class="col-xl-8">
    <section class="panel">
      <div class="panel-head"><h2>ID कार्ड और पत्र</h2></div>
      <div class="panel-body">
        <?php if (can('reporters.approve')): ?>
          <form method="post" action="<?= e(route('admin.reporters.issue', ['id' => $r['id']])) ?>" class="d-flex flex-wrap gap-2 mb-3" target="_blank">
            <?= csrf_field() ?>
            <?php foreach (ReporterDocument::TYPES as $k => [$l]): ?><button class="btn btn-sm <?= $k === 'id_card' ? 'btn-brand' : 'btn-outline-secondary' ?>" type="submit" name="type" value="<?= e($k) ?>" data-no-lock<?= $r['status'] !== 'active' && $k !== 'experience' ? ' disabled' : '' ?>><i class="fa-solid fa-plus me-1"></i><?= e($l) ?></button><?php endforeach; ?>
          </form>
          <p class="small text-body-secondary">जारी करते ही नया नंबर बनता है और प्रिंट पेज नई टैब में खुलता है (ब्राउज़र से “PDF में सेव” करें)। नया ID कार्ड/अधिकार पत्र जारी होने पर पुराना अपने आप रद्द।</p>
        <?php endif; ?>
        <?php if ($documents): ?>
          <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>दस्तावेज़</th><th>नंबर</th><th>जारी</th><th>वैधता</th><th>स्थिति</th><th class="text-end"></th></tr></thead>
            <tbody><?php foreach ($documents as $d): ?>
              <tr class="<?= $d['status'] !== 'active' ? 'is-off' : '' ?>">
                <td><?= e(ReporterDocument::TYPES[$d['type']][0]) ?></td><td class="font-monospace small"><?= e($d['doc_no']) ?></td>
                <td class="small"><?= hindi_date($d['issued_at']) ?><span class="d-block text-body-secondary"><?= e($d['issuer'] ?? '') ?></span></td>
                <td class="small"><?= $d['valid_until'] ? hindi_date($d['valid_until']) : '—' ?></td>
                <td><?= $d['status'] === 'active' ? status_badge('active') : '<span class="badge-status text-bg-danger" title="' . e($d['revoked_reason'] ?? '') . '"><i class="dot"></i>रद्द</span>' ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.reporters.document', ['id' => $r['id'], 'doc' => $d['id']])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i></a>
                  <?php if ($d['status'] === 'active' && can('reporters.approve')): ?><form method="post" action="<?= e(route('admin.reporters.revoke', ['id' => $r['id'], 'doc' => $d['id']])) ?>" class="d-inline" data-confirm="<?= e($d['doc_no']) ?> रद्द होगा; सत्यापन में यह मान्य नहीं दिखेगा।"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" type="submit" title="रद्द करें" aria-label="रद्द करें"><i class="fa-solid fa-ban"></i></button></form><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?></tbody>
          </table></div>
        <?php else: ?><p class="text-body-secondary small mb-0">अभी कोई दस्तावेज़ जारी नहीं हुआ।</p><?php endif; ?>
      </div>
    </section>
    <section class="panel mt-3">
      <div class="panel-head"><h2>हाल की ख़बरें</h2></div>
      <div class="panel-body">
        <?php if ($recent): ?><ul class="list-unstyled mb-0 simple-list"><?php foreach ($recent as $n): ?><li><a href="<?= e(route('admin.news.history', ['id' => $n['id']])) ?>"><?= e($n['title']) ?></a> <?= NewsWorkflow::badge($n['status']) ?> <small class="text-body-secondary"><?= time_ago($n['updated_at']) ?><?= $n['status'] === 'published' ? ' · ' . num($n['views']) . ' व्यूज़' : '' ?></small></li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-body-secondary mb-0">अभी कोई ख़बर नहीं।</p><?php endif; ?>
      </div>
    </section>
    <section class="panel mt-3">
      <div class="panel-head"><h2>लॉगिन हिस्ट्री</h2></div>
      <div class="panel-body">
        <?php if ($logins): ?><ul class="list-unstyled mb-0 simple-list small"><?php foreach ($logins as $l): ?><li><?= hindi_date($l['created_at'], true) ?> · <?= e($l['ip'] ?? '') ?> · <?= e(device_name($l['user_agent'] ?? '')) ?> <?= status_badge((string) $l['status']) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-body-secondary mb-0">अभी तक लॉगिन नहीं किया।</p><?php endif; ?>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <?php if (can('reporters.manage')): ?>
    <section class="panel">
      <div class="panel-head"><h2>वैधता और स्थिति</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e(route('admin.reporters.renew', ['id' => $r['id']])) ?>" class="d-flex gap-2 mb-3"><?= csrf_field() ?>
          <select class="form-select" name="months" aria-label="कितने महीने"><?php foreach ([6, 12, 24] as $m): ?><option value="<?= $m ?>"<?= selected($m, (int) setting('reporter_validity_months', 12)) ?>><?= $m ?> महीने</option><?php endforeach; ?></select>
          <button class="btn btn-outline-secondary text-nowrap" type="submit"<?= in_array($r['status'], ['suspended', 'resigned'], true) ? ' disabled' : '' ?>>नवीनीकरण</button>
        </form>
        <form method="post" action="<?= e(route('admin.reporters.status', ['id' => $r['id']])) ?>"><?= csrf_field() ?>
          <select class="form-select mb-2" name="status" aria-label="नई स्थिति" required>
            <option value="">स्थिति बदलें…</option>
            <?php if ($r['status'] !== 'active'): ?><option value="active">दोबारा सक्रिय करें</option><?php endif; ?>
            <?php if ($r['status'] !== 'suspended'): ?><option value="suspended">निलंबित करें</option><?php endif; ?>
            <?php if ($r['status'] !== 'resigned'): ?><option value="resigned">इस्तीफ़ा दर्ज करें</option><?php endif; ?>
          </select>
          <textarea class="form-control mb-2" name="reason" rows="2" maxlength="300" placeholder="कारण (निलंबन/इस्तीफ़ा पर ज़रूरी)" aria-label="कारण"></textarea>
          <button class="btn btn-dark w-100" type="submit">लागू करें</button>
        </form>
      </div>
    </section>
    <?php endif; ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2>जानकारी</h2></div>
      <div class="panel-body">
        <dl class="kv small">
          <dt>मोबाइल</dt><dd><?= e($r['mobile']) ?></dd><dt>ईमेल</dt><dd><?= e($user['email']) ?></dd>
          <dt>पिता/पति</dt><dd><?= e($r['guardian_name'] ?? '—') ?></dd><dt>जन्मतिथि</dt><dd><?= $r['dob'] ? hindi_date($r['dob']) : '—' ?></dd>
          <dt>ब्लड ग्रुप</dt><dd><?= e($r['blood_group'] ?? '—') ?></dd><dt>पता</dt><dd><?= e($r['address'] ?? '—') ?></dd>
          <dt>बीट</dt><dd><?= e($beat['name'] ?? '—') ?></dd><dt>ज़िला / राज्य</dt><dd><?= e(implode(', ', array_filter([$district, $state])) ?: '—') ?></dd>
          <dt>आख़िरी लॉगिन</dt><dd><?= $user['last_login_at'] ? time_ago($user['last_login_at']) : '—' ?></dd>
        </dl>
        <?php if ($r['notes']): ?><p class="small mb-0"><b>नोट:</b> <?= nl2br(e($r['notes'])) ?></p><?php endif; ?>
      </div>
    </section>
    <?php if ($kyc): ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2><i class="fa-solid fa-lock me-2 text-body-secondary"></i>KYC दस्तावेज़</h2></div>
      <ul class="list-unstyled panel-body mb-0 simple-list"><?php foreach ($kyc as $k => $p): if (!isset(ReporterApplication::DOCUMENTS[$k])) continue; ?><li><?= e(ReporterApplication::DOCUMENTS[$k][0]) ?> · <a href="<?= e(route('admin.reporters.kyc', ['id' => $r['id'], 'key' => $k])) ?>" target="_blank" rel="noopener">देखें</a></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
    <?php if ($assignments): ?>
    <section class="panel mt-3">
      <div class="panel-head"><h2>असाइनमेंट</h2></div>
      <ul class="list-unstyled panel-body mb-0 simple-list small"><?php foreach ($assignments as $as): ?><li><?= e($as['title']) ?> · <?= e(\App\Models\Assignment::STATUSES[$as['status']][0]) ?><?= $as['deadline'] ? ' · ' . hindi_date($as['deadline']) : '' ?></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
  </div>
</div>
