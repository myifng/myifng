<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\AuditService;
use App\Services\SettingService;
use App\Services\SettingsSchema;
use App\Services\UploadService;

/** साइट सेटिंग: schema (config/settings.php) से बने टैब */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->toRoute('admin.settings', ['tab' => 'general']);
    }

    public function edit(Request $request, string $tab): Response
    {
        $schema = SettingsSchema::tab($tab) ?? throw new HttpException(404);
        return $this->view('admin/settings/index', [
            'tabs' => SettingsSchema::tabs(),
            'active' => $tab,
            'schema' => $schema,
            'canEdit' => SettingsSchema::canEdit($schema),
        ]);
    }

    public function update(Request $request, string $tab): Response
    {
        $schema = SettingsSchema::tab($tab) ?? throw new HttpException(404);
        if (!SettingsSchema::canEdit($schema)) {
            throw new HttpException(403);
        }
        $post = $request->post();
        $data = [];
        foreach ($schema['fields'] as $name => $f) {
            $data[$name] = match ($f['type']) {
                'switch' => !empty($post[$name]) && $post[$name] !== '0' ? '1' : '0',
                'checkboxes' => implode(',', array_values(array_intersect(array_keys($f['options']), (array) ($post[$name] ?? [])))),
                'image' => null, // नीचे अलग से
                'code' => (string) ($post[$name] ?? ''),
                default => is_scalar($post[$name] ?? null) ? trim((string) $post[$name]) : '',
            };
        }

        [$rules, $labels] = SettingsSchema::rules($schema);
        $v = Validator::make($data, $rules, $labels);
        $errors = $v->errors();
        foreach ($schema['fields'] as $name => $f) {
            if ($f['type'] === 'timezone' && !in_array($data[$name], \DateTimeZone::listIdentifiers(), true)) {
                $errors[$name] = 'टाइमज़ोन सही नहीं है।';
            }
        }

        // इमेज: नई अपलोड, हटाना, या पुरानी बनी रहे
        foreach ($schema['fields'] as $name => $f) {
            if ($f['type'] !== 'image') {
                continue;
            }
            unset($data[$name]);
            if ($file = $request->file($name)) {
                $r = UploadService::store($file, $f['kind'] ?? 'image', 'branding', BASE_PATH . '/public/uploads', 1200);
                $r['ok'] ? $data[$name] = $r['path'] : $errors[$name] = $r['error'];
            } elseif (!empty($post['remove_' . $name])) {
                $data[$name] = '';
            }
        }
        if ($errors) {
            return $this->back()->withErrors($errors)->withInput($post);
        }

        $old = [];
        foreach (array_keys($data) as $k) {
            $old[$k] = (string) setting($k);
        }
        foreach ($data as $k => $val) {
            if ((string) $val !== $old[$k]) {
                SettingService::set($k, (string) $val, $tab);
            }
        }
        cache()->flush('menus');
        cache()->flush('home');
        AuditService::log('update', 'settings', $tab, 'सेटिंग बदली: ' . $schema['label'], $old, $data);

        $msg = '“' . $schema['label'] . '” सेटिंग सेव हो गई।';
        if (($data['maintenance_mode'] ?? '0') === '1') {
            return $this->toRoute('admin.settings', ['tab' => $tab])->with('warning', $msg . ' मेंटेनेंस मोड चालू है: पाठकों को वेबसाइट बंद दिखेगी। आप लॉगिन हैं, इसलिए आपको साइट दिखती रहेगी।');
        }
        return $this->toRoute('admin.settings', ['tab' => $tab])->with('success', $msg);
    }
}
