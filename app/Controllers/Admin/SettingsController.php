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

    /** सेव की गई ईमेल सेटिंग से टेस्ट मेल; न जाए तो SMTP की बातचीत दिखाएँ */
    public function mailTest(Request $request): Response
    {
        $to = trim($request->str('test_to')) ?: (string) user('email');
        $back = $this->toRoute('admin.settings', ['tab' => 'mail']);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $back->with('danger', 'टेस्ट मेल का पता सही नहीं है।');
        }
        $mailer = app('mailer');
        $site = (string) setting('site_name');
        $ok = $mailer->send($to, 'टेस्ट मेल: ' . $site, \App\Services\MailTemplate::title('ईमेल सेटिंग ठीक है', '✅')
            . \App\Services\MailTemplate::p('यह <b>' . e($site) . '</b> से भेजा गया टेस्ट मेल है। अब OTP, पासवर्ड रीसेट और सूचनाएँ इसी तरीके से जाएँगी।')
            . \App\Services\MailTemplate::info([['तरीका', \App\Core\Mailer::driver() === 'smtp' ? 'SMTP (' . setting('smtp_host') . ':' . setting('smtp_port') . ')' : 'PHP mail()'], ['भेजने वाला', (string) setting('mail_from_email')], ['समय', date('d-m-Y H:i:s')]])
            . \App\Services\MailTemplate::note('मेल स्पैम में मिला हो तो उसे "Not spam" करें, और डोमेन पर SPF/DKIM रिकॉर्ड जोड़ें (hosting के Email Deliverability में)।', 'ok'));
        AuditService::log('mail_test', 'settings', 'mail', 'टेस्ट मेल ' . ($ok ? 'भेजा' : 'नहीं गया') . ': ' . $to);
        if ($mailer->transcript()) {
            app('session')->flash('mail_transcript', implode("\n", $mailer->transcript()));
        }
        return $ok ? $back->with('success', "टेस्ट मेल $to पर भेज दिया। इनबॉक्स (और स्पैम फ़ोल्डर) देखें।")
            : $back->with('danger', 'मेल नहीं गया: ' . $mailer->lastError());
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

        // Phase 15: एडमिन IP allowlist: हर लाइन IP जाँचें, और अपना IP न हो तो सेव न हो (ख़ुद बाहर न हों)
        if (array_key_exists('admin_ip_allowlist', $data)) {
            $ips = \App\Services\SecurityService::parseAllowlist((string) $data['admin_ip_allowlist']);
            if ($ips['invalid']) {
                $errors['admin_ip_allowlist'] = 'ये IP सही नहीं: ' . implode(', ', array_slice($ips['invalid'], 0, 5));
            } elseif ($ips['list'] && !\App\Services\SecurityService::ipAllowed($request->ip(), $ips['list'])) {
                $errors['admin_ip_allowlist'] = 'आपका अभी का IP (' . $request->ip() . ') सूची में नहीं है; सेव करने पर आप ख़ुद एडमिन से बाहर हो जाते। पहले इसे जोड़ें।';
            } else {
                $data['admin_ip_allowlist'] = implode("\n", $ips['list']);
            }
        }

        // पासवर्ड: ख़ाली = पुराना बना रहे; "हटाएँ" = ख़ाली
        foreach ($schema['fields'] as $name => $f) {
            if ($f['type'] === 'password') {
                $raw = is_scalar($post[$name] ?? null) ? (string) $post[$name] : '';
                if (!empty($post['remove_' . $name])) {
                    $data[$name] = '';
                } elseif ($raw === '') {
                    unset($data[$name]);
                } else {
                    $data[$name] = $raw; // पासवर्ड में आगे-पीछे की जगह भी मायने रखती है
                }
            }
        }
        // ईमेल: SMTP चुना तो होस्ट ज़रूरी
        if (($data['mail_driver'] ?? '') === 'smtp' && trim((string) ($data['smtp_host'] ?? '')) === '') {
            $errors['smtp_host'] = 'SMTP चुना है तो होस्ट लिखें।';
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
        // ऑडिट में पासवर्ड नहीं
        foreach ($schema['fields'] as $name => $f) {
            if ($f['type'] === 'password' && array_key_exists($name, $data)) {
                $old[$name] = $old[$name] !== '' ? '••••' : '';
                $data[$name] = $data[$name] !== '' ? '•••• (बदला)' : '';
            }
        }
        AuditService::log('update', 'settings', $tab, 'सेटिंग बदली: ' . $schema['label'], $old, $data);

        $msg = '“' . $schema['label'] . '” सेटिंग सेव हो गई।';
        if (($data['maintenance_mode'] ?? '0') === '1') {
            return $this->back()->with('warning', $msg . ' मेंटेनेंस मोड चालू है: पाठकों को वेबसाइट बंद दिखेगी। आप लॉगिन हैं, इसलिए आपको साइट दिखती रहेगी।');
        }
        if (($data['two_factor_enabled'] ?? '0') === '1' && ($old['two_factor_enabled'] ?? '0') !== '1') {
            return $this->back()->with('warning', $msg . ' दो-चरण लॉगिन चालू हो गया: अगली बार से सभी स्टाफ़ और रिपोर्टर को ईमेल OTP लगेगा। सेटिंग → ईमेल में "टेस्ट मेल" भेजकर पक्का कर लें कि मेल पहुँच रहा है।'
                . ' (मेल बंद हो जाए तो config/env.php में TWO_FACTOR_BYPASS = 1)');
        }
        if (str_starts_with($tab, 'seo')) {
            cache()->flush('sitemap');
        }
        return $this->back()->with('success', $msg);
    }
}
