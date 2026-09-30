<?php
/** $form (fields के साथ), $extra [name => value] छुपे खाने */
use App\Services\FormService;
$hasFile = (bool) array_filter($form['fields'], static fn($f) => $f['type'] === 'file');
$formErr = error('_form');
?>
<form method="post" action="<?= e(route('form.submit', ['slug' => $form['slug']])) ?>" class="fgrid dyn-form"<?= $hasFile ? ' enctype="multipart/form-data"' : '' ?> novalidate data-dyn-form>
  <?= csrf_field() ?><input type="hidden" name="_ts" value="<?= e(FormService::stamp()) ?>">
  <?php foreach ($extra as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
  <div class="hp" aria-hidden="true"><label>वेबसाइट <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <?php if ($formErr): ?><div class="notice notice-danger ff-wide" role="alert"><?= e($formErr) ?></div><?php endif; ?>
  <?php foreach ($form['fields'] as $f):
      $key = $f['field_key']; $id = 'df_' . $form['id'] . '_' . $key; $name = 'f_' . $key; $err = error($key); $req = (bool) $f['required'];
      $wide = (int) $f['width'] >= 12 || in_array($f['type'], ['textarea', 'checkbox', 'radio', 'consent', 'heading'], true);
      $help = $err ? '<small class="ff-err" id="' . $id . '_e">' . e($err) . '</small>' : ($f['help'] ? '<small class="ff-help" id="' . $id . '_h">' . e($f['help']) . '</small>' : '');
      $aria = $err ? ' aria-invalid="true" aria-describedby="' . $id . '_e"' : ($f['help'] ? ' aria-describedby="' . $id . '_h"' : '');
      $old = old($name, '');
      $lab = '<label for="' . $id . '">' . e($f['label']) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
      $ph = $f['placeholder'] ? ' placeholder="' . e($f['placeholder']) . '"' : '';
  ?>
    <?php if ($f['type'] === 'heading'): ?>
      <div class="ff-wide dyn-heading"><h3><?= e($f['label']) ?></h3><?= $f['help'] ? '<p>' . e($f['help']) . '</p>' : '' ?></div>
    <?php elseif ($f['type'] === 'consent'): ?>
      <div class="ff ff-wide<?= $err ? ' has-err' : '' ?>"><label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= $old ? ' checked' : '' ?><?= $req ? ' required' : '' ?><?= $aria ?>> <span><?= e($f['label']) ?></span></label><?= $help ?></div>
    <?php elseif (in_array($f['type'], ['radio', 'checkbox'], true)): $opts = FormService::options($f['options']); $sel = (array) $old; ?>
      <fieldset class="ff ff-wide dyn-choices<?= $err ? ' has-err' : '' ?>"<?= $aria ?>><legend><?= e($f['label']) ?><?= $req ? ' <span class="req" aria-hidden="true">*</span>' : '' ?></legend>
        <?php if ($f['type'] === 'checkbox' && !$opts): ?><label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= $old ? ' checked' : '' ?>> हाँ</label>
        <?php else: foreach ($opts as $o): ?><label class="check"><input type="<?= $f['type'] ?>" name="<?= e($name) . ($f['type'] === 'checkbox' ? '[]' : '') ?>" value="<?= e($o) ?>"<?= in_array($o, array_map('strval', $sel), true) ? ' checked' : '' ?>> <?= e($o) ?></label><?php endforeach; endif; ?>
        <?= $help ?></fieldset>
    <?php else: ?>
      <div class="ff<?= $wide ? ' ff-wide' : '' ?><?= $err ? ' has-err' : '' ?>"><?= $lab ?>
        <?php if ($f['type'] === 'textarea'): ?><textarea id="<?= $id ?>" name="<?= e($name) ?>" rows="5" maxlength="5000"<?= $req ? ' required' : '' ?><?= $ph . $aria ?>><?= e((string) $old) ?></textarea>
        <?php elseif ($f['type'] === 'select'): ?><select id="<?= $id ?>" name="<?= e($name) ?>"<?= $req ? ' required' : '' ?><?= $aria ?>><option value="">चुनें…</option><?php foreach (FormService::options($f['options']) as $o): ?><option<?= $o === $old ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select>
        <?php elseif ($f['type'] === 'file'): $acc = []; foreach (explode(',', (string) ($f['accept'] ?: 'image,document')) as $g) { $acc = array_merge($acc, array_map(static fn($m) => '.' . \App\Services\PrivateFileService::TYPES[$m], \App\Services\PrivateFileService::GROUPS[trim($g)] ?? [])); } ?>
          <input type="file" id="<?= $id ?>" name="<?= e($name) ?>" accept="<?= e(implode(',', array_unique($acc))) ?>"<?= $req ? ' required' : '' ?><?= $aria ?> data-max-mb="<?= (int) ($f['max_mb'] ?: 5) ?>">
        <?php else: $type = ['email' => 'email', 'mobile' => 'tel', 'number' => 'number', 'url' => 'url', 'date' => 'date'][$f['type']] ?? 'text'; ?>
          <input type="<?= $type ?>" id="<?= $id ?>" name="<?= e($name) ?>" value="<?= e((string) $old) ?>" maxlength="300"<?= $req ? ' required' : '' ?><?= $ph . $aria ?><?= $f['type'] === 'mobile' ? ' inputmode="numeric" autocomplete="tel"' : ($f['type'] === 'email' ? ' autocomplete="email"' : ($key === 'name' ? ' autocomplete="name"' : '')) ?>>
        <?php endif; ?>
        <?= $help ?></div>
    <?php endif; ?>
  <?php endforeach; ?>
  <div class="ff-wide"><button class="btn" type="submit"><?= e($form['submit_label'] ?: 'भेजें') ?></button></div>
</form>
