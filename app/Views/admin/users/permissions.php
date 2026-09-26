<?php
use App\Services\PermissionService;
$this->layout('layouts/admin');
$title = 'व्यक्तिगत अनुमतियाँ';
$phase = (int) config('app.phase');
?>
<div class="page-head">
  <div>
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(route('admin.dashboard')) ?>">डैशबोर्ड</a></li><li class="breadcrumb-item"><a href="<?= e(route('admin.users.index')) ?>">यूज़र</a></li><li class="breadcrumb-item active" aria-current="page">अनुमतियाँ</li></ol></nav>
    <h1><?= e($user['name']) ?>: व्यक्तिगत अनुमतियाँ</h1>
    <p>रोल <b><?= e($user['role_name']) ?></b> की अनुमतियाँ अपने आप मिलती हैं। यहाँ सिर्फ़ इस यूज़र के लिए अलग से अनुमति दें या छीनें, जैसे किसी रिपोर्टर को सीधे प्रकाशित करने की अनुमति।</p>
  </div>
</div>
<div class="legend mb-3">
  <span><i class="lg lg-role"></i> रोल से मिली</span><span><i class="lg lg-allow"></i> अलग से दी गई</span><span><i class="lg lg-deny"></i> अलग से छीनी गई</span>
</div>

<form method="post" action="<?= e(route('admin.users.permissions.save', ['id' => $user['id']])) ?>">
  <?= csrf_field() ?>
  <section class="panel">
    <div class="table-responsive matrix-wrap">
      <table class="table matrix override mb-0">
        <?= $this->insert('partials/admin/matrix-head', ['toggles' => false]) ?>
        <?php foreach ($groups as $gKey => $gLabel):
            $mods = array_filter($matrix, fn($m) => $m['group'] === $gKey);
            if (!$mods) continue; ?>
          <tbody>
            <tr class="mx-group"><th colspan="9"><?= e($gLabel) ?></th></tr>
            <?php foreach ($mods as $m): ?>
              <tr class="<?= $m['phase'] > $phase ? 'is-future' : '' ?>">
                <th scope="row" class="mx-module"><i class="fa-solid <?= e($m['icon']) ?> fa-fw text-body-secondary me-1"></i><?= e($m['label']) ?></th>
                <?php foreach (array_keys(PermissionService::ACTION_LABELS) as $a): ?>
                  <td class="text-center">
                    <?php if (isset($m['actions'][$a])):
                        $pid = $m['actions'][$a];
                        $state = isset($overrides[$pid]) ? ($overrides[$pid] ? 'allow' : 'deny') : 'inherit';
                        $fromRole = isset($rolePerms[$pid]); ?>
                      <select name="perm[<?= (int) $pid ?>]" class="ov ov-<?= $state ?><?= $fromRole ? ' from-role' : '' ?>" aria-label="<?= e($m['label'] . ': ' . PermissionService::ACTION_LABELS[$a]) ?>">
                        <option value="inherit"<?= selected('inherit', $state) ?>><?= $fromRole ? '✓' : '–' ?></option>
                        <option value="allow"<?= selected('allow', $state) ?>>दें</option>
                        <option value="deny"<?= selected('deny', $state) ?>>छीनें</option>
                      </select>
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
  <div class="form-actions">
    <a class="btn btn-light" href="<?= e(route('admin.users.index')) ?>">वापस</a>
    <button class="btn btn-brand" type="submit"><i class="fa-solid fa-check me-1"></i>सेव करें</button>
  </div>
</form>
