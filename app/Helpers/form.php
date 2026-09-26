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
