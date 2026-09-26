<?php
use App\Models\Bureau;
$this->layout('layouts/admin');
$title = 'ब्यूरो';
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item active" aria-current="page">ब्यूरो</li></ol></nav>
    <h1>ब्यूरो</h1>
    <p>मुख्यालय → राज्य → ज़िला → तहसील/शहर। इस महीने की प्रकाशित ख़बरें और व्यूज़ ब्यूरो के रिपोर्टरों से।</p>
  </div>
  <?php if (can('bureaus.create')): ?><a class="btn btn-brand" href="<?= e(route('admin.bureaus.create')) ?>"><i class="fa-solid fa-plus me-1"></i> नया ब्यूरो</a><?php endif; ?>
</div>
<section class="panel">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table tree-table">
      <thead><tr><th>ब्यूरो</th><th>प्रमुख</th><th class="text-end">सक्रिय रिपोर्टर</th><th class="text-end">इस महीने ख़बरें</th><th class="text-end">व्यूज़</th><th class="text-end"></th></tr></thead>
      <tbody>
      <?php foreach ($tree as $b): ?>
        <tr class="<?= $b['status'] !== 'active' ? 'is-off' : '' ?>">
          <td><div class="d-flex align-items-center gap-2" style="padding-left:<?= (int) $b['depth'] * 22 ?>px"><?php if ($b['depth']): ?><span class="tree-elbow" aria-hidden="true"></span><?php endif; ?>
            <div><b><?= e($b['name']) ?></b><span class="d-block small text-body-secondary"><?= e(Bureau::TYPES[$b['type']]) ?><?= $b['location'] ? ' · ' . e($b['location']) : '' ?></span></div></div></td>
          <td class="small"><?= e($b['chief'] ?? '—') ?></td>
          <td class="text-end"><a href="<?= e(route('admin.reporters.index')) ?>?bureau=<?= (int) $b['id'] ?>"><?= num($b['reporters']) ?></a></td>
          <td class="text-end"><?= num($b['month_news']) ?></td>
          <td class="text-end"><?= num($b['month_views']) ?></td>
          <td class="text-end text-nowrap">
            <?php if (can('bureaus.create')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.bureaus.create')) ?>?parent=<?= (int) $b['id'] ?>" title="नीचे ब्यूरो जोड़ें" aria-label="<?= e($b['name']) ?> के नीचे ब्यूरो जोड़ें"><i class="fa-solid fa-plus"></i></a><?php endif; ?>
            <?php if (can('bureaus.edit')): ?><a class="btn btn-sm btn-icon btn-outline-secondary" href="<?= e(route('admin.bureaus.edit', ['id' => $b['id']])) ?>" title="बदलें" aria-label="बदलें: <?= e($b['name']) ?>"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
            <?php if (can('bureaus.delete')): ?><?= delete_button(route('admin.bureaus.destroy', ['id' => $b['id']]), 'ब्यूरो “' . $b['name'] . '” हटेगा।') ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
