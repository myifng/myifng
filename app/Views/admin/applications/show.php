<?php
use App\Models\Reporter;
use App\Models\ReporterApplication;
$this->layout('layouts/admin');
$title = 'आवेदन ' . $a['app_no'];
[$sl, $sc] = ReporterApplication::STATUSES[$a['status']];
$closed = in_array($a['status'], ['approved'], true);
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.applications.index')) ?>">रिपोर्टर आवेदन</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($a['app_no']) ?></li></ol></nav>
    <h1><?= e($a['full_name']) ?> <span class="badge-status text-bg-<?= e($sc) ?> fs-6"><i class="dot"></i><?= e($sl) ?></span></h1>
    <p class="font-monospace"><?= e($a['app_no']) ?> · जमा <?= hindi_date($a['created_at'], true) ?> · IP <?= e($a['ip'] ?? '—') ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($reporter): ?><a class="btn btn-success" href="<?= e(route('admin.reporters.show', ['id' => $reporter['id']])) ?>"><i class="fa-solid fa-id-card me-1"></i> रिपोर्टर <?= e($reporter['reporter_code']) ?></a>
    <?php elseif (can('applications.approve') && !in_array($a['status'], ['rejected'], true)): ?><a class="btn btn-brand" href="<?= e(route('admin.applications.approve', ['id' => $a['id']])) ?>"><i class="fa-solid fa-circle-check me-1"></i> मंज़ूर करें</a><?php endif; ?>
  </div>
</div>
<?php if ($duplicates): ?><div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i> इसी मोबाइल/ईमेल से दूसरे आवेदन: <?php foreach ($duplicates as $d): ?><a href="<?= e(route('admin.applications.show', ['id' => $d['id']])) ?>"><?= e($d['app_no']) ?></a> (<?= e(ReporterApplication::STATUSES[$d['status']][0]) ?>) <?php endforeach; ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-8">
    <section class="panel">
      <div class="panel-body app-profile">
        <?php if ($photo): ?><img class="app-photo" src="<?= e($photo) ?>" alt="आवेदक की फ़ोटो"><?php endif; ?>
        <dl class="kv">
          <dt>पिता/पति</dt><dd><?= e($a['guardian_name']) ?></dd>
          <dt>जन्मतिथि</dt><dd><?= hindi_date($a['dob']) ?> (<?= (int) date_diff(date_create($a['dob']), date_create('today'))->y ?> साल)</dd>
          <dt>लिंग</dt><dd><?= e(ReporterApplication::GENDERS[$a['gender']]) ?></dd>
          <dt>मोबाइल</dt><dd><a href="tel:<?= e($a['mobile']) ?>"><?= e($a['mobile']) ?></a><?= $a['whatsapp'] ? ' · WhatsApp <a href="https://wa.me/91' . e($a['whatsapp']) . '" target="_blank" rel="noopener">' . e($a['whatsapp']) . '</a>' : '' ?></dd>
          <dt>ईमेल</dt><dd><?= e($a['email']) ?></dd>
          <dt>पता</dt><dd><?= e($a['address']) ?><?= $a['city'] ? ', ' . e($a['city']) : '' ?> - <?= e($a['pincode']) ?><br><?= e(implode(', ', array_filter([$district, $state]))) ?></dd>
          <dt>प्रकार</dt><dd><?= e(Reporter::TYPES[$a['reporter_type']] ?? $a['reporter_type']) ?></dd>
          <dt>अनुभव</dt><dd><?= (int) $a['experience_years'] ?> साल<?= $a['previous_org'] ? ' · ' . e($a['previous_org']) : '' ?></dd>
          <dt>शिक्षा</dt><dd><?= e($a['education'] ?? '—') ?></dd>
          <dt>भाषाएँ</dt><dd><?= e($a['languages'] ?? '—') ?></dd>
          <dt>अपने बारे में</dt><dd><?= nl2br(e($a['about'] ?? '—')) ?></dd>
          <dt>घोषणा</dt><dd>स्वीकार की: <?= hindi_date($a['consent_at'], true) ?></dd>
        </dl>
      </div>
    </section>
    <section class="panel mt-3">
      <div class="panel-head"><h2><i class="fa-solid fa-lock me-2 text-body-secondary"></i>दस्तावेज़ (निजी)</h2></div>
      <div class="panel-body doc-grid">
        <?php foreach (ReporterApplication::DOCUMENTS as $k => [$l, $req]): ?>
          <div class="doc-item<?= empty($docs[$k]) ? ' missing' : '' ?>">
            <i class="fa-regular <?= !empty($docs[$k]) && str_ends_with($docs[$k], '.pdf') ? 'fa-file-pdf' : 'fa-file-image' ?>"></i>
            <b><?= e($l) ?></b>
            <?php if (!empty($docs[$k])): ?>
              <span><a href="<?= e(route('admin.applications.document', ['id' => $a['id'], 'key' => $k])) ?>" target="_blank" rel="noopener">देखें</a> · <a href="<?= e(route('admin.applications.document', ['id' => $a['id'], 'key' => $k])) ?>?download=1">डाउनलोड</a></span>
            <?php else: ?><span class="text-body-secondary small"><?= $req ? 'नहीं मिला' : 'नहीं दिया' ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <div class="col-xl-4">
    <?php if (!$closed && can('applications.edit')): ?>
    <section class="panel">
      <div class="panel-head"><h2>कार्रवाई</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= e(route('admin.applications.status', ['id' => $a['id']])) ?>" class="mb-3">
          <?= csrf_field() ?>
          <label class="form-label" for="st">नई स्थिति</label>
          <select class="form-select mb-2" id="st" name="status" required>
            <option value="">चुनें…</option>
            <?php foreach (['under_review', 'document_pending', 'verification_pending', 'on_hold', 'rejected'] as $k): if ($k === 'rejected' && !can('applications.approve')) continue; ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' disabled' : '' ?>><?= e(ReporterApplication::STATUSES[$k][0]) ?></option><?php endforeach; ?>
          </select>
          <label class="form-label" for="msg">आवेदक के लिए संदेश</label>
          <textarea class="form-control mb-1" id="msg" name="message" rows="3" maxlength="500" placeholder="जैसे: पहचान पत्र धुँधला है, साफ़ फ़ोटो भेजें"></textarea>
          <div class="form-text mb-2">“दस्तावेज़ बाकी” और “अस्वीकार” पर ज़रूरी; आवेदक को स्थिति पेज और ईमेल पर दिखेगा।</div>
          <button class="btn btn-dark w-100" type="submit">स्थिति बदलें</button>
        </form>
        <form method="post" action="<?= e(route('admin.applications.assign', ['id' => $a['id']])) ?>" class="d-flex gap-2">
          <?= csrf_field() ?>
          <select class="form-select" name="assigned_to" aria-label="जाँच किसे सौंपें"><option value="">जाँच किसे सौंपें…</option><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected($u['id'], $a['assigned_to']) ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
          <button class="btn btn-outline-secondary" type="submit">सौंपें</button>
        </form>
      </div>
    </section>
    <?php endif; ?>
    <section class="panel<?= !$closed && can('applications.edit') ? ' mt-3' : '' ?>">
      <div class="panel-head"><h2>टिप्पणियाँ और हिस्ट्री</h2></div>
      <div class="panel-body">
        <?php if (can('applications.edit')): ?>
        <form method="post" action="<?= e(route('admin.applications.remark', ['id' => $a['id']])) ?>" class="mb-3"><?= csrf_field() ?>
          <textarea class="form-control mb-2" name="message" rows="2" maxlength="3000" placeholder="आंतरिक टिप्पणी (आवेदक को नहीं दिखेगी)" aria-label="आंतरिक टिप्पणी" required></textarea>
          <button class="btn btn-sm btn-dark" type="submit">जोड़ें</button>
        </form>
        <?php endif; ?>
        <ol class="remark-list">
          <?php foreach ($remarks as $r): ?>
            <li class="rm-<?= $r['type'] === 'note' ? 'note' : 'feedback' ?>">
              <div class="rm-head"><b><?= e($r['user'] ?? 'सिस्टम') ?></b>
                <?php if ($r['to_status']): ?><span class="small"><?= $r['from_status'] ? e(ReporterApplication::STATUSES[$r['from_status']][0] ?? $r['from_status']) . ' → ' : '' ?><b><?= e(ReporterApplication::STATUSES[$r['to_status']][0] ?? $r['to_status']) ?></b></span><?php endif; ?>
                <?php if ($r['type'] === 'correction'): ?><span class="chip-sm">आवेदक को दिखा</span><?php endif; ?>
                <time class="small text-body-secondary ms-auto"><?= time_ago($r['created_at']) ?></time></div>
              <?php if ($r['message']): ?><p><?= nl2br(e($r['message'])) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
          <li><div class="rm-head"><b><?= e($a['full_name']) ?></b> <span class="small">आवेदन जमा किया</span><time class="small text-body-secondary ms-auto"><?= time_ago($a['created_at']) ?></time></div></li>
        </ol>
      </div>
    </section>
    <?php if (can('applications.delete') && in_array($a['status'], ['rejected', 'on_hold'], true)): ?>
      <div class="danger-zone mt-3"><div><b>आवेदन हटाएँ</b><p class="mb-0 small">निजी दस्तावेज़ भी सर्वर से हट जाएँगे।</p></div><?= delete_button(route('admin.applications.destroy', ['id' => $a['id']]), 'आवेदन ' . $a['app_no'] . ' और उसके सभी दस्तावेज़ हमेशा के लिए हट जाएँगे।', 'हटाएँ', 'btn btn-outline-danger') ?></div>
    <?php endif; ?>
  </div>
</div>
