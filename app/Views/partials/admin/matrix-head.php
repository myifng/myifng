<?php use App\Services\PermissionService; ?>
<thead class="matrix-head">
  <tr>
    <th scope="col" class="mx-module">मॉड्यूल</th>
    <?php foreach (PermissionService::ACTION_LABELS as $a => $l): ?>
      <th scope="col" class="text-center"><?= e($l) ?><?php if (!empty($toggles)): ?><br><button type="button" class="btn btn-link btn-sm p-0 mx-col" data-col="<?= e($a) ?>">सब</button><?php endif; ?></th>
    <?php endforeach; ?>
  </tr>
</thead>
