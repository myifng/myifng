<?php $this->layout('layouts/admin'); $title = 'यूज़र'; ?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">यूज़र</li></ol></nav>
    <h1>यूज़र</h1>
    <p>टीम के सदस्य, उनके रोल और खाते की स्थिति</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('users.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.users.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i> CSV</a><?php endif; ?>
    <?php if (can('users.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.users.create')) ?>"><i class="fa-solid fa-user-plus me-1"></i> नया यूज़र</a><?php endif; ?>
  </div>
</div>

<section class="panel">
  <div class="panel-tabs">
    <?php foreach (['' => ['सभी', $counts['all']], 'active' => ['चालू', $counts['active']], 'inactive' => ['बंद', $counts['inactive']], 'suspended' => ['निलंबित', $counts['suspended']]] as $k => [$l, $n]): ?>
      <a href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $filters['q'], 'role' => $filters['role'] ?: null]))) ?>" class="<?= $filters['status'] === $k ? 'active' : '' ?>"><?= e($l) ?> <span><?= num($n) ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="filter-bar" method="get">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="नाम, ईमेल या मोबाइल" aria-label="खोजें"></div>
    <select class="form-select w-auto" name="role" aria-label="रोल">
      <option value="">सभी रोल</option>
      <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"<?= selected($r['id'], $filters['role']) ?>><?= e($r['name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select w-auto" name="sort" aria-label="क्रम">
      <option value="">नए पहले</option><option value="name"<?= selected('name', $filters['sort']) ?>>नाम (अ-ज्ञ)</option><option value="login"<?= selected('login', $filters['sort']) ?>>हाल में लॉगिन</option>
    </select>
    <button class="btn btn-dark" type="submit">फ़िल्टर</button>
    <?php if ($filters['q'] !== '' || $filters['role'] || $filters['sort']): ?><a class="btn btn-link" href="<?= e(route('admin.users.index')) ?>">साफ़ करें</a><?php endif; ?>
  </form>

  <?php if ($users->items): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr><th>यूज़र</th><th>रोल</th><th>मोबाइल</th><th>स्थिति</th><th>आख़िरी लॉगिन</th><th class="text-end">काम</th></tr></thead>
      <tbody>
      <?php foreach ($users->items as $u): ?>
        <tr>
          <td><div class="d-flex align-items-center gap-2"><?= avatar_html($u['avatar'], $u['name']) ?><div><b class="d-block"><?= e($u['name']) ?><?= (int) $u['id'] === auth()->id() ? ' <span class="badge text-bg-light">आप</span>' : '' ?></b><span class="small text-body-secondary"><?= e($u['email']) ?></span></div></div></td>
          <td><span class="role-chip role-<?= e($u['role_slug']) ?>"><?= e($u['role_name']) ?></span></td>
          <td class="text-nowrap"><?= e($u['mobile'] ?: '—') ?></td>
          <td><?= status_badge($u['status']) ?></td>
          <td class="text-nowrap small"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : '<span class="text-body-secondary">कभी नहीं</span>' ?></td>
          <td class="text-end text-nowrap">
            <?php if (can('users.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.users.edit', ['id' => $u['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            <?php if (can('users.manage') && $u['role_slug'] !== 'super-admin'): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.users.permissions', ['id' => $u['id']])) ?>" title="व्यक्तिगत अनुमतियाँ" aria-label="व्यक्तिगत अनुमतियाँ"><i class="fa-solid fa-key"></i></a><?php endif; ?>
            <?php if (can('users.delete') && (int) $u['id'] !== auth()->id()): ?><?= delete_button(route('admin.users.destroy', ['id' => $u['id']]), '“' . $u['name'] . '” का खाता हटा दिया जाएगा। उनकी पुरानी गतिविधि ऑडिट लॉग में बनी रहेगी।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= $this->insert('partials/admin/pagination', ['p' => $users]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="fa-solid fa-users-slash"></i><p>इन फ़िल्टर से कोई यूज़र नहीं मिला।</p></div>
  <?php endif; ?>
</section>
