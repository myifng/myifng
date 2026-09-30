<?php
use App\Services\FormService;
$this->layout('layouts/admin');
$title = 'फ़ॉर्म बिल्डर';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">फ़ॉर्म</li></ol></nav>
    <h1>फ़ॉर्म बिल्डर</h1><p>बिना कोडिंग के फ़ॉर्म बनाएँ। किसी पेज/ख़बर में <code>[form:slug]</code> लिखें या सीधे <code>/form/slug</code> का लिंक दें।</p>
  </div>
  <?php if (can('forms.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.forms.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया फ़ॉर्म</a><?php endif; ?>
</div>
<section class="panel">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>फ़ॉर्म</th><th>प्रकार</th><th>खाने</th><th>जमा</th><th>स्थिति</th><th class="text-end">काम</th></tr></thead>
    <tbody><?php foreach ($items as $f): $t = FormService::type($f['type']); ?><tr class="<?= $f['status'] !== 'active' ? 'is-off' : '' ?>">
      <td><a class="fw-semibold text-reset" href="<?= e(route('admin.forms.edit', ['id' => $f['id']])) ?>"><?= e($f['title']) ?></a><?= $f['is_system'] ? ' <span class="badge text-bg-light">सिस्टम</span>' : '' ?>
        <div class="small text-body-secondary font-monospace">[form:<?= e($f['slug']) ?>]</div></td>
      <td class="small"><?= e($t[0]) ?></td>
      <td><?= num($f['fields']) ?></td>
      <td><?php if (FormService::can($f['type'])): ?><a href="<?= e(route('admin.inbox', ['type' => $f['type']])) ?>?form=<?= (int) $f['id'] ?>"><?= num($f['subs']) ?></a><?= $f['fresh'] ? ' <span class="badge text-bg-warning">' . num($f['fresh']) . ' नए</span>' : '' ?><?php else: ?><?= num($f['subs']) ?><?php endif; ?></td>
      <td><?= status_badge($f['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($f['type'] !== 'career' && $f['status'] === 'active'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('form.show', ['slug' => $f['slug']])) ?>" target="_blank" rel="noopener" aria-label="वेबसाइट पर देखें"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        <?php if (can('forms.create')): ?><form class="d-inline" method="post" action="<?= e(route('admin.forms.duplicate', ['id' => $f['id']])) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-icon btn-outline-secondary" type="submit" aria-label="कॉपी"><i class="fa-regular fa-copy"></i></button></form><?php endif; ?>
        <?php if (can('forms.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.forms.edit', ['id' => $f['id']])) ?>" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
      </td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
</section>
