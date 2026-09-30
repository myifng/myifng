<?php
use App\Services\ElectionService as ES;
$this->layout('layouts/admin');
$title = 'चुनाव केंद्र';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">चुनाव केंद्र</li></ol></nav>
    <h1>चुनाव केंद्र</h1>
    <p>लोकसभा, विधानसभा, उपचुनाव: सीटें, उम्मीदवार, लाइव रुझान और नतीजे।</p>
  </div>
  <?php if (can('elections.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.elections.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया चुनाव</a><?php endif; ?>
</div>
<?= $this->insert('admin/elections/_nav', ['active' => 'index']) ?>
<section class="panel">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>चुनाव</th><th>स्थिति</th><th class="text-end">सीटें</th><th class="text-end d-none d-md-table-cell">उम्मीदवार</th><th class="text-end d-none d-md-table-cell">घोषित</th><th class="text-end">काम</th></tr></thead>
    <tbody>
    <?php foreach ($items as $x): [$sl, $sc] = ES::STATUSES[$x['status']]; ?>
      <tr>
        <td><a class="fw-semibold text-reset" href="<?= e(route('admin.elections.show', ['id' => $x['id']])) ?>"><?= e($x['name']) ?></a>
          <div class="small text-body-secondary"><?= e(ES::TYPES[$x['type']]) ?> · <?= (int) $x['year'] ?><?= $x['state'] ? ' · ' . e($x['state']) : '' ?><?= $x['is_featured'] ? ' · <i class="fa-solid fa-star text-warning"></i> मुख्य' : '' ?></div></td>
        <td><span class="badge text-bg-<?= e($sc) ?>"><?= e($sl) ?></span></td>
        <td class="text-end"><?= num((int) $x['seats']) ?><?= $x['total_seats'] && (int) $x['total_seats'] !== (int) $x['seats'] ? '<small class="text-body-secondary"> / ' . num((int) $x['total_seats']) . '</small>' : '' ?></td>
        <td class="text-end d-none d-md-table-cell"><?= num((int) $x['candidates']) ?></td>
        <td class="text-end d-none d-md-table-cell"><?= num((int) $x['declared']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin.elections.show', ['id' => $x['id']])) ?>"><i class="fa-solid fa-table-list me-1"></i>काउंटिंग डेस्क</a>
          <?php if (can('elections.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.elections.edit', ['id' => $x['id']])) ?>" title="बदलें" aria-label="बदलें"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-check-to-slot"></i><p>अभी कोई चुनाव नहीं। "नया चुनाव" से शुरू करें।</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
