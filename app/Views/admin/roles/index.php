<?php $this->layout('layouts/admin'); $title = 'रोल और अनुमतियाँ'; ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">रोल</li></ol></nav>
    <h1>रोल और अनुमतियाँ</h1>
    <p>हर रोल को मॉड्यूल-वार अनुमति दें। कुल <?= num($total) ?> अनुमतियाँ, <?= count(config('modules.modules')) ?> मॉड्यूल।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('roles.manage')): ?>
      <form method="post" action="<?= e(route('admin.roles.sync')) ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit" title="नए मॉड्यूल की अनुमतियाँ जोड़ें"><i class="fa-solid fa-rotate me-1"></i> Sync</button></form>
    <?php endif; ?>
    <?php if (can('roles.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.roles.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया रोल</a><?php endif; ?>
  </div>
</div>

<div class="role-grid">
  <?php foreach ($roles as $r):
      $super = $r['slug'] === 'super-admin';
      $pct = $super ? 100 : ($total ? round($r['permission_count'] / $total * 100) : 0); ?>
    <article class="role-card">
      <header>
        <span class="role-chip role-<?= e($r['slug']) ?>"><?= e($r['name']) ?></span>
        <?php if ($r['is_system']): ?><span class="badge text-bg-light" title="सिस्टम रोल हटाया नहीं जा सकता"><i class="fa-solid fa-lock"></i> सिस्टम</span><?php endif; ?>
      </header>
      <p><?= e($r['description'] ?: '—') ?></p>
      <dl>
        <div><dt>यूज़र</dt><dd><?= num($r['user_count']) ?></dd></div>
        <div><dt>अनुमतियाँ</dt><dd><?= $super ? 'सभी' : num($r['permission_count']) ?></dd></div>
        <div><dt>स्तर</dt><dd><?= (int) $r['level'] ?></dd></div>
      </dl>
      <div class="progress" role="progressbar" aria-label="अनुमति कवरेज" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
      <footer>
        <?php if (can('roles.edit') && app('gate')->outranks((int) $r['level'])): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(route('admin.roles.edit', ['id' => $r['id']])) ?>"><i class="fa-solid fa-sliders me-1"></i> <?= $super ? 'देखें' : 'अनुमतियाँ बदलें' ?></a><?php endif; ?>
        <?php if (can('users.view')): ?><a class="btn btn-sm btn-link" href="<?= e(route('admin.users.index') . '?role=' . $r['id']) ?>">यूज़र देखें</a><?php endif; ?>
        <?php if (!$r['is_system'] && can('roles.delete') && app('gate')->outranks((int) $r['level'])): ?><span class="ms-auto"><?= delete_button(route('admin.roles.destroy', ['id' => $r['id']]), 'रोल “' . $r['name'] . '” हटा दिया जाएगा।') ?></span><?php endif; ?>
      </footer>
    </article>
  <?php endforeach; ?>
</div>
