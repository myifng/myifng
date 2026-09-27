<?php
use App\Models\Advertiser;
use App\Services\AdvertiserService;
$this->layout('layouts/admin');
$title = 'विज्ञापनदाता';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">विज्ञापनदाता</li></ol></nav>
    <h1>विज्ञापनदाता CRM</h1>
    <p>कंपनियाँ, कैंपेन, इनवॉइस और भुगतान एक जगह।</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('advertisers.manage')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.invoices.index')) ?>"><i class="fa-solid fa-file-invoice me-1"></i> इनवॉइस</a><a class="btn btn-outline-secondary" href="<?= e(route('admin.revenue')) ?>"><i class="fa-solid fa-chart-column me-1"></i> राजस्व</a><?php endif; ?>
    <?php if (can('ads.view')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.ads.index')) ?>"><i class="fa-solid fa-rectangle-ad me-1"></i> विज्ञापन</a><?php endif; ?>
    <?php if (can('advertisers.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.advertisers.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया विज्ञापनदाता</a><?php endif; ?>
  </div>
</div>
<?php if ($kpi): ?><?= $this->insert('admin/advertisers/_kpi', ['kpi' => $kpi]) ?><?php endif; ?>
<section class="panel">
  <form class="filter-bar" method="get">
    <select class="form-select w-auto" name="status" aria-label="स्थिति"><option value="">सभी</option><?php foreach (Advertiser::STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $status) ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <div class="input-icon flex-grow-1"><i class="fa-solid fa-magnifying-glass"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="कंपनी, नाम, फ़ोन या ईमेल" aria-label="खोजें"></div>
    <button class="btn btn-dark" type="submit">खोजें</button>
    <?php if (can('advertisers.export')): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin.advertisers.export')) ?>"><i class="fa-solid fa-file-csv me-1"></i>CSV</a><?php endif; ?>
  </form>
  <?php if ($items->items): ?>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0 data-table">
    <thead><tr><th>कंपनी</th><th>संपर्क</th><th>चालू कैंपेन</th><?php if (can('advertisers.manage')): ?><th class="text-end">भुगतान</th><th class="text-end">बकाया</th><?php endif; ?><th>स्थिति</th></tr></thead>
    <tbody><?php foreach ($items->items as $v): ?>
      <tr class="<?= $v['status'] === 'inactive' ? 'is-off' : '' ?>">
        <td><a class="fw-semibold text-reset" href="<?= e(route('admin.advertisers.show', ['id' => $v['id']])) ?>"><?= e($v['company']) ?></a><?= $v['city'] ? '<div class="small text-body-secondary">' . e($v['city']) . '</div>' : '' ?></td>
        <td class="small"><?= e($v['contact_name'] ?? '') ?><?= $v['phone'] ? '<div><a href="tel:' . e($v['phone']) . '">' . e($v['phone']) . '</a></div>' : '' ?></td>
        <td><?= num($v['live_campaigns']) ?></td>
        <?php if (can('advertisers.manage')): ?><td class="text-end"><?= e(AdvertiserService::money($v['paid'])) ?></td><td class="text-end<?= $v['due'] > 0 ? ' text-warning-emphasis fw-semibold' : '' ?>"><?= e(AdvertiserService::money($v['due'])) ?></td><?php endif; ?>
        <td><span class="badge-status text-bg-<?= ['lead' => 'info', 'active' => 'success', 'inactive' => 'secondary'][$v['status']] ?>"><i class="dot"></i><?= e(Advertiser::STATUSES[$v['status']]) ?></span></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= $this->insert('partials/admin/pagination', ['p' => $items]) ?>
  <?php else: ?><div class="empty-state"><i class="fa-solid fa-handshake"></i><p>कोई विज्ञापनदाता नहीं मिला।</p></div><?php endif; ?>
</section>
