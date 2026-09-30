<?php
$this->layout('layouts/admin');
$title = $t[0];
$icons = ['contact' => 'fa-address-book', 'news_tip' => 'fa-lightbulb', 'complaint' => 'fa-scale-balanced', 'career' => 'fa-briefcase', 'custom' => 'fa-wpforms'];
$qs = static fn(array $p) => '?' . http_build_query(array_filter($p, static fn($v) => $v !== '' && $v !== null && $v !== 0));
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($t[0]) ?></li></ol></nav>
    <h1><i class="fa-solid <?= $icons[$type] ?> me-2 text-body-secondary"></i><?= e($t[0]) ?></h1>
    <p><?= ['contact' => 'संपर्क, विज्ञापन पूछताछ और फ़ीडबैक।', 'news_tip' => 'पाठकों की भेजी ख़बरें: जाँचें, असाइनमेंट या ड्राफ़्ट ख़बर बनाएँ।', 'complaint' => 'शिकायत/ग्रीवेंस: अधिकारी को सौंपें, जवाब और निपटारा दर्ज करें।', 'career' => 'वैकेंसी पर आए आवेदन।', 'custom' => 'कस्टम फ़ॉर्म के जमा।'][$type] ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($type === 'career' && can('careers.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.careers.index')) ?>"><i class="fa-solid fa-briefcase me-1"></i> वैकेंसी</a><?php endif; ?>
    <?php if (\App\Services\FormService::can($type, $type === 'news_tip' ? 'view' : 'export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.inbox.export', ['type' => $type])) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a><?php endif; ?>
    <?php if (can('forms.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.forms.index')) ?>"><i class="fa-brands fa-wpforms me-1"></i> फ़ॉर्म</a><?php endif; ?>
  </div>
</div>
<nav class="sub-tabs mb-3" aria-label="स्थिति">
  <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(route('admin.inbox', ['type' => $type])) ?>">काम वाले</a>
  <?php foreach ($t[3] as $k => [$l]): ?><a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(route('admin.inbox', ['type' => $type]) . $qs(['status' => $k])) ?>"><?= e($l) ?> <span class="badge text-bg-light"><?= num($tally[$k] ?? 0) ?></span></a><?php endforeach; ?>
  <a class="<?= $status === 'all' ? 'active' : '' ?>" href="<?= e(route('admin.inbox', ['type' => $type])) ?>?status=all">सभी</a>
</nav>
<section class="panel">
  <form class="filter-bar" method="get"><input type="hidden" name="status" value="<?= e($status) ?>">
    <?php if (count($forms) > 1): ?><select class="form-select w-auto" name="form" aria-label="फ़ॉर्म"><option value="">हर फ़ॉर्म</option><?php foreach ($forms as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected((string) $id, (string) $formId) ?>><?= e($n) ?></option><?php endforeach; ?></select><?php endif; ?>
    <?php if ($jobs): ?><select class="form-select w-auto" name="job" aria-label="पद"><option value="">हर पद</option><?php foreach ($jobs as $id => $n): ?><option value="<?= (int) $id ?>"<?= selected((string) $id, (string) $jobId) ?>><?= e($n) ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="नंबर, नाम, ईमेल, मोबाइल या कोई शब्द" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
  </form>
  <?php if ($items->items): ?>
  <form method="post" action="<?= e(route('admin.inbox.bulk', ['type' => $type])) ?>" id="sbBulk"><?= csrf_field() ?></form>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all="sb" aria-label="सभी चुनें"></th><th>नंबर</th><th>भेजने वाला</th><th><?= $type === 'career' ? 'पद' : 'फ़ॉर्म' ?></th><th>स्थिति</th><th>कब</th></tr></thead>
    <tbody><?php foreach ($items->items as $s): [$sl, $sc] = $t[3][$s['status']] ?? [$s['status'], 'secondary']; $d = json_decode((string) $s['data'], true) ?: []; $peek = $d['description'][1] ?? ($d['message'][1] ?? ($d['cover'][1] ?? '')); ?>
      <tr class="<?= $s['status'] === 'new' ? 'fw-semibold' : '' ?>">
        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" form="sbBulk" data-check="sb" aria-label="चुनें"></td>
        <td class="text-nowrap"><a href="<?= e(route('admin.submissions.show', ['id' => $s['id']])) ?>"><?= e($s['ref_no']) ?></a><?= $s['files'] ? ' <i class="fa-solid fa-paperclip text-body-secondary" title="फ़ाइल"></i>' : '' ?></td>
        <td><?= e((string) ($s['name'] ?: '—')) ?><div class="small text-body-secondary fw-normal"><?= e(trim($s['mobile'] . ' ' . $s['email'])) ?></div><?= is_string($peek) && $peek !== '' ? '<div class="small text-body-secondary fw-normal">' . e(\App\Helpers\Str::limit($peek, 90)) . '</div>' : '' ?></td>
        <td class="small fw-normal"><?= e((string) ($type === 'career' ? ($s['job_title'] ?? '—') : $s['form_title'])) ?><?= $s['assignee'] ? '<div class="text-body-secondary"><i class="fa-solid fa-user-check"></i> ' . e($s['assignee']) . '</div>' : '' ?></td>
        <td><span class="badge text-bg-<?= $sc ?>"><?= e($sl) ?></span></td>
        <td class="small text-nowrap fw-normal"><?= e(hindi_date($s['created_at'], true)) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if (\App\Services\FormService::can($type, 'edit')): ?>
  <div class="bulk-bar"><span class="small text-body-secondary">चुने हुए:</span>
    <select class="form-select form-select-sm w-auto" name="action" form="sbBulk" aria-label="काम"><?php foreach ($t[3] as $k => [$l]): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?><?php if (\App\Services\FormService::can($type, 'delete')): ?><option value="delete">हटाएँ</option><?php endif; ?></select>
    <button class="btn btn-sm btn-outline-secondary" type="submit" form="sbBulk">लागू करें</button></div>
  <?php endif; ?>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid <?= $icons[$type] ?>"></i><p>यहाँ अभी कुछ नहीं।</p></div><?php endif; ?>
</section>
