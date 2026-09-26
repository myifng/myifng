<?php
use App\Services\PermissionService;
$this->layout('layouts/admin');
$isNew = $role === null;
$super = !$isNew && $role['slug'] === 'super-admin';
$title = $isNew ? 'नया रोल' : 'रोल: ' . $role['name'];
$phase = (int) config('app.phase');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.roles.index')) ?>">रोल</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($isNew ? 'नया' : $role['name']) ?></li></ol></nav>
    <h1><?= e($isNew ? 'नया रोल' : $role['name']) ?></h1>
    <p>मॉड्यूल-वार अनुमति चुनें। धुंधले मॉड्यूल आगे के phase में बनेंगे; अनुमति अभी से दी जा सकती है।</p>
  </div>
</div>

<form method="post" action="<?= e($isNew ? route('admin.roles.store') : route('admin.roles.update', ['id' => $role['id']])) ?>" novalidate>
  <?= csrf_field() ?><?= $isNew ? '' : method_field('PUT') ?>
  <section class="panel">
    <div class="panel-body">
      <div class="row">
        <div class="col-md-4"><?= field('text', 'name', 'रोल का नाम', $role['name'] ?? '', ['required' => true, 'placeholder' => 'जैसे: डेस्क एडिटर', 'attrs' => ['data-slug-source' => '#f_slug']]) ?></div>
        <div class="col-md-3"><?= field('text', 'slug', 'स्लग', $role['slug'] ?? '', ['help' => 'अंग्रेज़ी में, अपने आप बनेगा', 'attrs' => (!$isNew && $role['is_system']) ? ['readonly' => true] : []]) ?></div>
        <div class="col-md-2"><?= field('number', 'level', 'स्तर (1-99)', $role['level'] ?? 10, ['required' => true, 'help' => 'ऊँचा स्तर नीचे वालों को संभाल सकता है', 'attrs' => ['min' => 1, 'max' => 99]]) ?></div>
        <div class="col-md-3"><?= field('text', 'description', 'विवरण', $role['description'] ?? '', ['attrs' => ['maxlength' => 255]]) ?></div>
      </div>
    </div>
  </section>

  <?php if ($super): ?>
    <div class="alert alert-info mt-3"><i class="fa-solid fa-circle-info me-1"></i> Super Admin के पास सभी अनुमतियाँ अपने आप रहती हैं। इन्हें बदला नहीं जा सकता।</div>
  <?php else: ?>
  <section class="panel mt-3">
    <div class="panel-head">
      <h2>अनुमति मैट्रिक्स</h2>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-light" data-matrix="all">सब चुनें</button>
        <button type="button" class="btn btn-sm btn-light" data-matrix="none">सब हटाएँ</button>
        <button type="button" class="btn btn-sm btn-light" data-matrix="view">सिर्फ़ देखना</button>
      </div>
    </div>
    <div class="table-responsive matrix-wrap">
      <table class="table matrix mb-0" data-matrix-table>
        <?= $this->insert('partials/admin/matrix-head', ['toggles' => true]) ?>
        <?php foreach ($groups as $gKey => $gLabel):
            $mods = array_filter($matrix, fn($m) => $m['group'] === $gKey);
            if (!$mods) continue; ?>
          <tbody>
            <tr class="mx-group"><th colspan="9"><?= e($gLabel) ?></th></tr>
            <?php foreach ($mods as $key => $m): ?>
              <tr class="<?= $m['phase'] > $phase ? 'is-future' : '' ?>">
                <th scope="row" class="mx-module">
                  <label class="d-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input mx-row" aria-label="<?= e($m['label']) ?> की सभी अनुमतियाँ">
                    <i class="fa-solid <?= e($m['icon']) ?> fa-fw text-body-secondary"></i><span><?= e($m['label']) ?></span>
                    <?php if ($m['phase'] > $phase): ?><span class="badge text-bg-light">Phase <?= (int) $m['phase'] ?></span><?php endif; ?></label>
                </th>
                <?php foreach (array_keys(PermissionService::ACTION_LABELS) as $a): ?>
                  <td class="text-center">
                    <?php if (isset($m['actions'][$a])):
                        $pid = $m['actions'][$a];
                        $ok = $grantable === null || isset($grantable[$pid]); ?>
                      <input type="checkbox" class="form-check-input" name="permissions[]" value="<?= (int) $pid ?>" data-action="<?= e($a) ?>"<?= checked(isset($selected[$pid])) ?><?= $ok ? '' : ' disabled title="यह अनुमति आपके पास नहीं है"' ?> aria-label="<?= e($m['label'] . ': ' . PermissionService::ACTION_LABELS[$a]) ?>">
                    <?php else: ?><span class="text-body-tertiary" aria-hidden="true">·</span><?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        <?php endforeach; ?>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <div class="form-actions">
    <a class="btn btn-light" href="<?= e(route('admin.roles.index')) ?>">रद्द करें</a>
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i><?= $isNew ? 'रोल बनाएँ' : 'सेव करें' ?></button>
  </div>
</form>
