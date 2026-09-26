<?php
/**
 * फ़ॉर्म के खाने (Bootstrap 5)। हर खाना पिछला भरा मान (old) और त्रुटि अपने आप दिखाता है।
 *   <?= field('email', 'email', 'ईमेल', $user['email'] ?? '', ['required' => true]) ?>
 */
declare(strict_types=1);

function field(string $type, string $name, string $label, mixed $value = null, array $o = []): string
{
    $id = $o['id'] ?? 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    $err = error($name);
    $val = in_array($type, ['password', 'file'], true) ? '' : old($name, $value ?? '');
    $req = !empty($o['required']);
    $cls = ($type === 'select' ? 'form-select' : ($type === 'color' ? 'form-control form-control-color' : 'form-control')) . ($err ? ' is-invalid' : '') . (!empty($o['class']) ? ' ' . $o['class'] : '');
    $attrs = '';
    foreach (($o['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    if ($req) {
        $attrs .= ' required';
    }
    if (!empty($o['placeholder'])) {
        $attrs .= ' placeholder="' . e($o['placeholder']) . '"';
    }
    $describedBy = ($err || !empty($o['help'])) ? ' aria-describedby="' . $id . '_help"' : '';
    $labelHtml = '<label class="form-label" for="' . $id . '">' . e($label) . ($req ? ' <span class="text-danger" aria-hidden="true">*</span>' : '') . '</label>';

    if ($type === 'switch') {
        $on = old($name, $value) ? ' checked' : '';
        $html = '<div class="form-check form-switch"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . $on . $attrs . '><label class="form-check-label" for="' . $id . '">' . e($label) . '</label></div>';
    } elseif ($type === 'textarea') {
        $html = $labelHtml . '<textarea class="' . $cls . '" id="' . $id . '" name="' . e($name) . '" rows="' . (int) ($o['rows'] ?? 3) . '"' . $attrs . $describedBy . '>' . e($val) . '</textarea>';
    } elseif ($type === 'select') {
        $opts = '';
        if (isset($o['empty'])) {
            $opts .= '<option value="">' . e($o['empty']) . '</option>';
        }
        foreach (($o['options'] ?? []) as $k => $v) {
            $opts .= '<option value="' . e($k) . '"' . selected($k, $val) . '>' . e($v) . '</option>';
        }
        $html = $labelHtml . '<select class="' . $cls . '" id="' . $id . '" name="' . e($name) . '"' . $attrs . $describedBy . '>' . $opts . '</select>';
    } else {
        $input = '<input type="' . e($type) . '" class="' . $cls . '" id="' . $id . '" name="' . e($name) . '"' . ($type !== 'file' ? ' value="' . e($val) . '"' : '') . $attrs . $describedBy . '>';
        if (!empty($o['prefix'])) {
            $input = '<div class="input-group has-validation"><span class="input-group-text">' . $o['prefix'] . '</span>' . $input . ($err ? '<div class="invalid-feedback">' . e($err) . '</div>' : '') . '</div>';
            $err = null;
        }
        $html = $labelHtml . $input;
    }
    if ($err) {
        $html .= '<div class="invalid-feedback d-block" id="' . $id . '_help">' . e($err) . '</div>';
    } elseif (!empty($o['help'])) {
        $html .= '<div class="form-text" id="' . $id . '_help">' . e($o['help']) . '</div>';
    }
    return '<div class="' . e($o['wrap'] ?? 'mb-3') . '">' . $html . '</div>';
}

/** स्थिति का रंगीन बैज */
function status_badge(string $status): string
{
    $map = ['active' => ['success', 'चालू'], 'inactive' => ['secondary', 'बंद'], 'suspended' => ['danger', 'निलंबित'],
        'success' => ['success', 'सफल'], 'failed' => ['danger', 'असफल'], 'blocked' => ['warning', 'रोका गया'], 'logout' => ['secondary', 'लॉगआउट']];
    [$c, $l] = $map[$status] ?? ['secondary', $status];
    return '<span class="badge-status text-bg-' . $c . '"><i class="dot"></i>' . e($l) . '</span>';
}

/** अवतार: फ़ोटो या नाम का पहला अक्षर */
function avatar_html(?string $avatar, ?string $name, string $size = ''): string
{
    if ($avatar) {
        return '<span class="avatar ' . $size . '"><img src="' . e(upload_url($avatar)) . '" alt=""></span>';
    }
    $h = 0;
    foreach (mb_str_split((string) $name) as $ch) {
        $h = ($h * 31 + mb_ord($ch)) % 360;
    }
    return '<span class="avatar ' . $size . '" style="--h:' . $h . '">' . e(initials($name)) . '</span>';
}

/** हटाने वाला बटन (पुष्टि के साथ) */
function delete_button(string $action, string $message, string $label = '', string $class = 'btn btn-sm btn-icon btn-outline-danger'): string
{
    return '<form method="post" action="' . e($action) . '" class="d-inline" data-confirm="' . e($message) . '">' . csrf_field() . method_field('DELETE')
        . '<button type="submit" class="' . e($class) . '" title="हटाएँ" aria-label="हटाएँ"><i class="fa-solid fa-trash-can"></i>' . ($label ? ' ' . e($label) : '') . '</button></form>';
}

/** सेटिंग schema के एक खाने का HTML (config/settings.php के type के हिसाब से) */
function setting_field(string $name, array $f, bool $disabled = false): string
{
    $id = 's_' . $name;
    $err = error($name);
    $val = old($name, setting($name));
    $dis = $disabled ? ' disabled' : '';
    $label = e($f['label']);
    $help = !empty($f['help']) ? '<div class="form-text">' . e($f['help']) . '</div>' : '';
    $invalid = $err ? ' is-invalid' : '';
    $errHtml = $err ? '<div class="invalid-feedback d-block">' . e($err) . '</div>' : '';
    $col = 'col-md-' . (int) ($f['width'] ?? 12);
    $icon = !empty($f['icon']) ? '<i class="' . e($f['icon']) . ' fa-fw me-1"></i>' : '';

    switch ($f['type']) {
        case 'switch':
            $html = '<div class="form-check form-switch setting-switch"><input type="hidden" name="' . e($name) . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($name) . '" value="1"' . checked($val === '1' || $val === 1) . $dis . '><label class="form-check-label" for="' . $id . '">' . $label . '</label></div>' . $help;
            return '<div class="col-12">' . $html . $errHtml . '</div>';
        case 'checkboxes':
            $sel = is_array($val) ? $val : explode(',', (string) $val);
            $boxes = '';
            foreach ($f['options'] as $k => $l) {
                $boxes .= '<label class="chip-check"><input type="checkbox" name="' . e($name) . '[]" value="' . e($k) . '"' . checked(in_array($k, $sel, true)) . $dis . '><span>' . e($l) . '</span></label>';
            }
            return '<div class="col-12 mb-3"><span class="form-label d-block">' . $label . '</span><div class="chip-checks">' . $boxes . '</div>' . $help . $errHtml . '</div>';
        case 'image':
            $prev = $val ? '<div class="img-preview"><img src="' . e(upload_url((string) $val)) . '" alt=""><label class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="remove_' . e($name) . '" value="1"' . $dis . '> <span class="form-check-label">हटाएँ</span></label></div>' : '<div class="img-preview empty"><i class="fa-regular fa-image"></i></div>';
            return '<div class="' . $col . ' mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label>' . $prev . '<input class="form-control' . $invalid . '" type="file" id="' . $id . '" name="' . e($name) . '" accept="image/*"' . $dis . '>' . $help . $errHtml . '</div>';
        case 'textarea':
        case 'code':
            $cls = $f['type'] === 'code' ? ' font-monospace code-area' : '';
            return '<div class="' . $col . ' mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label><textarea class="form-control' . $cls . $invalid . '" id="' . $id . '" name="' . e($name) . '" rows="' . ($f['type'] === 'code' ? 6 : 3) . '"' . $dis . ' spellcheck="' . ($f['type'] === 'code' ? 'false' : 'true') . '">' . e($val) . '</textarea>' . $help . $errHtml . '</div>';
        case 'select':
        case 'font':
        case 'timezone':
            $opts = $f['type'] === 'timezone' ? array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()) : $f['options'];
            $o = '';
            foreach ($opts as $k => $l) {
                $o .= '<option value="' . e($k) . '"' . selected($k, $val) . ($f['type'] === 'font' ? ' style="font-family:\'' . e($k) . '\'"' : '') . '>' . e($l) . '</option>';
            }
            return '<div class="' . $col . ' mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label><select class="form-select' . $invalid . '" id="' . $id . '" name="' . e($name) . '"' . $dis . '>' . $o . '</select>' . $help . $errHtml . '</div>';
        case 'color':
            return '<div class="' . $col . ' mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label><div class="input-group"><input type="color" class="form-control form-control-color' . $invalid . '" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $dis . ' data-color-sync="#' . $id . '_hex"><input type="text" class="form-control font-monospace" id="' . $id . '_hex" value="' . e($val) . '" aria-label="' . $label . ' (hex)" readonly></div>' . $help . $errHtml . '</div>';
        default:
            $type = in_array($f['type'], ['email', 'url', 'tel', 'number'], true) ? $f['type'] : 'text';
            $ph = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
            $input = '<input type="' . $type . '" class="form-control' . $invalid . '" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $ph . $dis . '>';
            if ($icon) {
                $input = '<div class="input-group"><span class="input-group-text">' . $icon . '</span>' . $input . '</div>';
            }
            return '<div class="' . $col . ' mb-3"><label class="form-label" for="' . $id . '">' . $label . '</label>' . $input . $help . $errHtml . '</div>';
    }
}

/** होमपेज ब्लॉक की एक सेटिंग का खाना (config/home_blocks.php) */
function block_field(int $sid, string $name, array $f, mixed $value, array $categories = [], array $locations = []): string
{
    $id = 'hs' . $sid . '_' . $name;
    $n = 'settings[' . e($name) . ']';
    $label = '<label class="form-label small" for="' . $id . '">' . e($f['label']) . (!empty($f['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';
    $value ??= $f['default'] ?? '';
    switch ($f['type']) {
        case 'switch':
            return '<div class="col-12"><div class="form-check form-switch"><input type="hidden" name="' . $n . '" value="0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . $n . '" value="1"' . checked($value) . '><label class="form-check-label small" for="' . $id . '">' . e($f['label']) . '</label></div></div>';
        case 'number':
            return '<div class="col-md-4">' . $label . '<input class="form-control form-control-sm" type="number" id="' . $id . '" name="' . $n . '" value="' . e($value) . '" min="' . (int) ($f['min'] ?? 1) . '" max="' . (int) ($f['max'] ?? 100) . '"></div>';
        case 'select':
            $o = '';
            foreach ($f['options'] as $k => $l) {
                $o .= '<option value="' . e($k) . '"' . selected($k, $value) . '>' . e($l) . '</option>';
            }
            return '<div class="col-md-4">' . $label . '<select class="form-select form-select-sm" id="' . $id . '" name="' . $n . '">' . $o . '</select></div>';
        case 'category':
        case 'location':
            $rows = $f['type'] === 'category' ? $categories : $locations;
            if (!$rows) {
                return '<div class="col-md-6">' . $label . '<div class="form-text mt-0">' . ($f['type'] === 'category' ? 'श्रेणियाँ' : 'लोकेशन') . ' Phase 3 में बनेंगी, तब यहाँ चुन सकेंगे।</div><input type="hidden" name="' . $n . '" value="' . e($value) . '"></div>';
            }
            $o = '<option value="">' . (!empty($f['required']) ? 'चुनें…' : 'कोई नहीं') . '</option>';
            foreach ($rows as $r) {
                $o .= '<option value="' . (int) $r['id'] . '"' . selected($r['id'], $value) . '>' . e($r['name']) . '</option>';
            }
            return '<div class="col-md-6">' . $label . '<select class="form-select form-select-sm" id="' . $id . '" name="' . $n . '">' . $o . '</select></div>';
        case 'stories':
            return '<div class="col-12" data-show-when-manual>' . $label . '<input class="form-control form-control-sm" id="' . $id . '" name="' . $n . '" value="' . e(implode(', ', (array) $value)) . '" placeholder="ख़बर IDs, जैसे 12, 45, 7"><div class="form-text">Phase 4 में यहाँ ख़बर खोजकर चुनने का बॉक्स आएगा।</div></div>';
        case 'code':
        case 'textarea':
            return '<div class="col-12">' . $label . '<textarea class="form-control form-control-sm' . ($f['type'] === 'code' ? ' font-monospace code-area' : '') . '" id="' . $id . '" name="' . $n . '" rows="5" spellcheck="false">' . e($value) . '</textarea></div>';
        default:
            return '<div class="col-md-8">' . $label . '<input class="form-control form-control-sm" id="' . $id . '" name="' . $n . '" value="' . e($value) . '" maxlength="300"></div>';
    }
}
