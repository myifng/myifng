<?php
use App\Services\BackupService as BS;
$this->layout('layouts/admin');
$title = 'बैकअप';
$sched = ['off' => 'बंद', 'daily' => 'रोज़', 'weekly' => 'हर हफ़्ते'][setting('backup_schedule', 'off')] ?? 'बंद';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">बैकअप</li></ol></nav>
    <h1>बैकअप</h1>
    <p>डेटाबेस और अपलोड फ़ाइलों का बैकअप। अपने-आप बैकअप: <b><?= e($sched) ?></b> · रखने की संख्या: <b><?= (int) setting('backup_keep', '7') ?></b>
      <?php if (can('backups.manage') && can('settings.view')): ?> · <a href="<?= e(route('admin.settings', ['tab' => 'backups'])) ?>">बदलें</a><?php endif; ?></p>
  </div>
</div>
<?= $this->insert('admin/system/_nav', ['active' => 'backups']) ?>

<div class="row g-3 mb-3">
  <?php foreach ([['fa-database', 'डेटाबेस का आकार', BS::size($dbSize)], ['fa-images', 'अपलोड फ़ाइलें', BS::size($uploads)], ['fa-box-archive', 'सारे बैकअप', BS::size($total)], ['fa-hard-drive', 'डिस्क में ख़ाली', BS::size((int) $free)]] as [$ic, $l, $v]): ?>
    <div class="col-6 col-xl-3"><div class="panel h-100"><div class="panel-body kpi"><span><i class="fa-solid <?= $ic ?> me-1"></i><?= e($l) ?></span><b><?= e($v) ?></b></div></div></div>
  <?php endforeach; ?>
</div>

<?php if (can('backups.create')): ?>
<section class="panel mb-3">
  <div class="panel-head"><h2>नया बैकअप</h2></div>
  <form class="panel-body" method="post" action="<?= e(route('admin.backups.store')) ?>" data-backup-form>
    <?= csrf_field() ?>
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <span class="form-label d-block">प्रकार</span>
        <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="बैकअप का प्रकार">
          <?php foreach (BS::TYPES as $k => [$l, $ic]): ?>
            <label class="bk-type"><input type="radio" name="type" value="<?= e($k) ?>"<?= $k === 'db' ? ' checked' : '' ?><?= $k !== 'db' && !$zip ? ' disabled' : '' ?>><span><i class="fa-solid <?= e($ic) ?>"></i> <?= e($l) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-4"><label class="form-label" for="bk-note">नोट (वैकल्पिक)</label><input class="form-control" id="bk-note" name="note" maxlength="255" placeholder="जैसे: अपडेट से पहले"></div>
      <div class="col-md-3 d-grid"><button class="btn btn-brand" type="submit" data-busy="बन रहा है…"><i class="fa-solid fa-play me-1"></i> बैकअप बनाएँ</button></div>
    </div>
    <?php if (!$zip): ?><p class="small text-danger mt-2 mb-0">सर्वर पर PHP का zip extension नहीं है, इसलिए सिर्फ़ डेटाबेस बैकअप बन सकता है।</p><?php endif; ?>
    <p class="small text-body-secondary mt-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-1"></i>बैकअप में पूरा डेटा (यूज़र, पाठक, KYC) होता है। इसे सुरक्षित जगह रखें और किसी से साझा न करें। बड़ी साइट पर फ़ाइल बैकअप में कुछ मिनट लग सकते हैं।</p>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2>बैकअप का इतिहास</h2></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>बैकअप</th><th>आकार</th><th class="d-none d-md-table-cell">किसने</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody>
    <?php foreach ($items as $b): ?>
      <tr>
        <td><b><i class="fa-solid <?= e(BS::TYPES[$b['type']][1]) ?> me-1 text-body-secondary"></i><?= e(BS::TYPES[$b['type']][0]) ?></b>
          <div class="small text-body-secondary"><?= hindi_date($b['created_at'], true) ?><?= $b['note'] ? ' · ' . e($b['note']) : '' ?></div>
          <?php if ($b['checksum']): ?><div class="small text-body-secondary font-monospace text-truncate" style="max-width:280px" title="SHA-256">SHA-256: <?= e(substr($b['checksum'], 0, 16)) ?>…</div><?php endif; ?></td>
        <td class="text-nowrap"><?= $b['size'] ? e(BS::size((int) $b['size'])) : '—' ?></td>
        <td class="d-none d-md-table-cell small"><?= $b['trigger'] === 'schedule' ? '<i class="fa-solid fa-clock"></i> अपने-आप' : e((string) $b['by_name']) ?></td>
        <td><?php if ($b['status'] === 'done'): ?><span class="badge text-bg-success">तैयार</span><?php elseif ($b['status'] === 'running'): ?><span class="badge text-bg-warning">चल रहा</span><?php else: ?><span class="badge text-bg-danger" title="<?= e((string) $b['error']) ?>">असफल</span><div class="small text-danger"><?= e(mb_substr((string) $b['error'], 0, 80)) ?></div><?php endif; ?></td>
        <td class="text-end text-nowrap">
          <?php if ($b['status'] === 'done' && can('backups.view')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.backups.download', ['id' => $b['id']])) ?>"><i class="fa-solid fa-download me-1"></i>डाउनलोड</a><?php endif; ?>
          <?php if (can('backups.delete')): ?><?= delete_button(route('admin.backups.destroy', ['id' => $b['id']]), 'यह बैकअप हमेशा के लिए हट जाएगा।') ?><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-database"></i><p>अभी कोई बैकअप नहीं। पहला बैकअप ऊपर से बनाएँ।</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<section class="panel mt-3"><div class="panel-body small">
  <b><i class="fa-solid fa-rotate-left me-1"></i>रीस्टोर कैसे करें:</b>
  <ol class="mb-0 mt-1">
    <li>डेटाबेस: hosting के phpMyAdmin → अपना डेटाबेस → Import → <code>.sql.gz</code> फ़ाइल चुनें (पुरानी टेबल बदल जाएँगी)।</li>
    <li>फ़ाइलें: ZIP खोलकर <code>uploads/</code> को <code>public/uploads/</code> में और <code>private/</code> को <code>storage/private/</code> में रखें।</li>
    <li>"पूरा" बैकअप में <code>database.sql.gz</code> भी उसी ZIP के अंदर है। रीस्टोर के बाद एडमिन → सिस्टम → "सारा कैश साफ़ करें"।</li>
  </ol>
</div></section>
