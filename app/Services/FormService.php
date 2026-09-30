<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;

/**
 * फ़ॉर्म इंजन: फ़ॉर्म + खाने, जाँच, फ़ाइलें (निजी), संदर्भ नंबर, पावती ईमेल, टीम को सूचना।
 * फ़ॉर्म का "प्रकार" तय करता है कि जमा फ़ॉर्म किस इनबॉक्स/अनुमति में जाएगा।
 */
final class FormService
{
    /** प्रकार: [लेबल, अनुमति मॉड्यूल, नंबर का शुरुआती हिस्सा, स्थितियाँ [key => [लेबल, रंग]]] */
    public const TYPES = [
        'contact' => ['संपर्क / पूछताछ', 'contacts', 'CT', ['new' => ['नया', 'warning'], 'in_progress' => ['प्रगति में', 'info'], 'replied' => ['जवाब दिया', 'success'], 'closed' => ['बंद', 'secondary'], 'spam' => ['स्पैम', 'dark']]],
        'news_tip' => ['न्यूज़ टिप', 'news_tips', 'TIP', ['new' => ['नया', 'warning'], 'reviewing' => ['जाँच में', 'info'], 'converted' => ['असाइनमेंट/ड्राफ़्ट बना', 'success'], 'rejected' => ['अस्वीकृत', 'secondary'], 'spam' => ['स्पैम', 'dark']]],
        'complaint' => ['शिकायत / ग्रीवेंस', 'complaints', 'CMP', ['new' => ['नई', 'warning'], 'in_progress' => ['प्रगति में', 'info'], 'resolved' => ['निपटाई गई', 'success'], 'rejected' => ['अस्वीकृत', 'secondary'], 'closed' => ['बंद', 'dark']]],
        'career' => ['नौकरी आवेदन', 'careers', 'JOB', ['new' => ['नया', 'warning'], 'shortlisted' => ['शॉर्टलिस्ट', 'info'], 'interview' => ['इंटरव्यू', 'primary'], 'selected' => ['चयनित', 'success'], 'rejected' => ['अस्वीकृत', 'secondary']]],
        'custom' => ['कस्टम फ़ॉर्म', 'forms', 'FRM', ['new' => ['नया', 'warning'], 'in_progress' => ['प्रगति में', 'info'], 'replied' => ['जवाब दिया', 'success'], 'closed' => ['बंद', 'secondary'], 'spam' => ['स्पैम', 'dark']]],
    ];

    public const FIELD_TYPES = [
        'text' => 'टेक्स्ट', 'email' => 'ईमेल', 'mobile' => 'मोबाइल', 'number' => 'नंबर', 'url' => 'लिंक (URL)', 'textarea' => 'बड़ा टेक्स्ट', 'select' => 'ड्रॉपडाउन',
        'radio' => 'रेडियो (एक चुनें)', 'checkbox' => 'चेकबॉक्स (कई चुनें)', 'date' => 'तारीख़', 'file' => 'फ़ाइल', 'consent' => 'सहमति', 'heading' => 'शीर्षक / जानकारी (खाना नहीं)',
    ];

    public const FILE_KINDS = ['image' => 'फ़ोटो (JPG/PNG)', 'document' => 'PDF', 'video' => 'वीडियो (MP4)', 'cv' => 'CV (PDF/DOC/DOCX)', 'image,document' => 'फ़ोटो या PDF'];

    public static function type(string $t): array
    {
        return self::TYPES[$t] ?? self::TYPES['custom'];
    }

    /** इस प्रकार के इनबॉक्स की अनुमति */
    public static function can(string $type, string $action = 'view'): bool
    {
        return can(self::type($type)[1] . '.' . $action);
    }

    public static function find(string $slug, bool $activeOnly = true): ?array
    {
        $f = db()->first('SELECT * FROM {p}forms WHERE slug = ?' . ($activeOnly ? " AND status = 'active'" : ''), [$slug]);
        if ($f) {
            $f['fields'] = self::fields((int) $f['id']);
        }
        return $f;
    }

    public static function fields(int $formId): array
    {
        return db()->all('SELECT * FROM {p}form_fields WHERE form_id = ? ORDER BY sort_order, id', [$formId]);
    }

    public static function options(?string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $raw))), static fn($o) => $o !== ''));
    }

    public static function stamp(): string
    {
        $t = (string) time();
        return $t . '.' . substr(hash_hmac('sha256', 'fm|' . $t, (string) config('app.key')), 0, 16);
    }

    private static function age(string $s): ?int
    {
        [$t, $h] = array_pad(explode('.', $s, 2), 2, '');
        return ctype_digit($t) && hash_equals(substr(hash_hmac('sha256', 'fm|' . $t, (string) config('app.key')), 0, 16), $h) ? time() - (int) $t : null;
    }

    /** फ़ॉर्म का HTML ($extra: छुपे खाने, जैसे job) */
    public static function render(array $form, array $extra = []): string
    {
        return app('view')->render('partials/front/form', ['form' => $form, 'extra' => $extra]);
    }

    /** पेज/ख़बर में [form:slug] */
    public static function shortcodes(string $html): string
    {
        return preg_replace_callback('~(?:<p>\s*)?\[form:([a-z0-9-]{2,120})\](?:\s*</p>)?~', static function ($m) {
            $f = self::find($m[1]);
            return $f ? '<div class="form-embed">' . self::render($f) . '</div>' : '';
        }, $html) ?? $html;
    }

    /**
     * जमा करें। लौटाए: ['errors' => [...]] या ['id' => int, 'ref' => string, 'fake' => bool]
     * $ctx: job_id, page_url
     */
    public static function submit(array $form, Request $request, array $ctx = []): array
    {
        if ($request->str('website') !== '') {
            return ['id' => 0, 'ref' => '', 'fake' => true]; // honeypot
        }
        $age = self::age($request->str('_ts'));
        if ($age === null || $age < 3 || $age > 86400) {
            return ['errors' => ['_form' => 'फ़ॉर्म दोबारा खोलकर कुछ सेकंड बाद भेजें।']];
        }
        $errors = [];
        $data = [];
        $pending = [];
        foreach ($form['fields'] as $f) {
            $key = $f['field_key'];
            $label = $f['label'];
            $req = (bool) $f['required'];
            if ($f['type'] === 'heading') {
                continue;
            }
            if ($f['type'] === 'file') {
                $file = $request->file('f_' . $key);
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    if ($req) {
                        $errors[$key] = "$label ज़रूरी है।";
                    }
                    continue;
                }
                $pending[$key] = [$file, $f];
                continue;
            }
            $raw = $request->input('f_' . $key);
            if ($f['type'] === 'checkbox') {
                $opts = self::options($f['options']);
                $val = $opts ? array_values(array_intersect($opts, array_map('strval', (array) $raw))) : ($raw ? ['हाँ'] : []);
                if ($req && !$val) {
                    $errors[$key] = "$label में कम से कम एक चुनें।";
                }
                if ($val) {
                    $data[$key] = [$label, $val];
                }
                continue;
            }
            if ($f['type'] === 'consent') {
                if ($req && !$request->bool('f_' . $key)) {
                    $errors[$key] = 'आगे बढ़ने के लिए सहमति ज़रूरी है।';
                } elseif ($request->bool('f_' . $key)) {
                    $data[$key] = [$label, 'हाँ'];
                }
                continue;
            }
            $val = is_scalar($raw) ? trim(str_replace("\r", '', strip_tags((string) $raw))) : '';
            if ($val === '') {
                if ($req) {
                    $errors[$key] = "$label ज़रूरी है।";
                }
                continue;
            }
            $max = $f['type'] === 'textarea' ? 5000 : 300;
            $err = match ($f['type']) {
                'email' => filter_var($val, FILTER_VALIDATE_EMAIL) ? null : 'सही ईमेल लिखें।',
                'mobile' => preg_match('/^(\+91[\-\s]?)?[6-9]\d{9}$/', $val) ? null : '10 अंकों का सही मोबाइल नंबर लिखें।',
                'number' => is_numeric($val) ? null : 'सिर्फ़ अंक लिखें।',
                'url' => filter_var($val, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $val) ? null : 'पूरा लिंक लिखें (https:// से)।',
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) && strtotime($val) ? null : 'सही तारीख़ चुनें।',
                'select', 'radio' => in_array($val, self::options($f['options']), true) ? null : 'दिए गए विकल्पों में से चुनें।',
                default => null,
            } ?? (mb_strlen($val) > $max ? "ज़्यादा से ज़्यादा $max अक्षर।" : null);
            if ($err) {
                $errors[$key] = $err;
                continue;
            }
            $data[$key] = [$label, $f['type'] === 'email' ? mb_strtolower($val) : $val];
        }
        if ($errors) {
            return ['errors' => $errors];
        }
        // फ़ाइलें (बाकी सब सही होने के बाद ही सेव)
        $files = [];
        foreach ($pending as $key => [$file, $f]) {
            $mimes = [];
            foreach (explode(',', (string) ($f['accept'] ?: 'image,document')) as $g) {
                $mimes = array_merge($mimes, PrivateFileService::GROUPS[trim($g)] ?? []);
            }
            $r = PrivateFileService::store($file, 'forms/' . $form['id'] . '/' . date('Y-m'), array_values(array_unique($mimes)), max(1, min(100, (int) ($f['max_mb'] ?: 5))));
            if (!$r['ok']) {
                self::cleanup($files);
                return ['errors' => [$key => $f['label'] . ': ' . $r['error']]];
            }
            $files[$key] = ['label' => $f['label'], 'path' => $r['path'], 'mime' => $r['mime'], 'size' => (int) $file['size'], 'name' => mb_substr(basename((string) $file['name']), 0, 120)];
        }
        $t = self::type((string) $form['type']);
        $pick = static fn(string $k) => isset($data[$k]) && is_string($data[$k][1]) ? mb_substr($data[$k][1], 0, $k === 'name' ? 150 : 190) : null;
        $id = db()->insert('form_submissions', [
            'form_id' => (int) $form['id'], 'ref_no' => 'TMP-' . bin2hex(random_bytes(6)), 'status' => 'new', 'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'files' => $files ? json_encode($files, JSON_UNESCAPED_UNICODE) : null, 'name' => $pick('name'), 'email' => $pick('email'), 'mobile' => $pick('mobile'),
            'job_id' => $ctx['job_id'] ?? null, 'ip_hash' => ReaderAuth::ipHash($request), 'user_agent' => mb_substr($request->userAgent(), 0, 255),
            'page_url' => isset($ctx['page_url']) ? mb_substr((string) $ctx['page_url'], 0, 500) : null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $ref = sprintf('%s-%s-%05d', $t[2], date('Y'), $id);
        db()->query('UPDATE {p}form_submissions SET ref_no = ? WHERE id = ?', [$ref, $id]);
        self::afterSubmit($form, $id, $ref, $data, $ctx);
        return ['id' => $id, 'ref' => $ref, 'fake' => false];
    }

    private static function cleanup(array $files): void
    {
        foreach ($files as $f) {
            if ($abs = PrivateFileService::absolute($f['path'])) {
                @unlink($abs);
            }
        }
    }

    /** पावती ईमेल + टीम को सूचना */
    private static function afterSubmit(array $form, int $id, string $ref, array $data, array $ctx): void
    {
        try {
            $email = isset($data['email']) ? (string) $data['email'][1] : '';
            if ($email !== '') {
                $track = $form['type'] === 'complaint' && setting('complaint_tracking', '1') === '1'
                    ? '<p>स्थिति देखें: <a href="' . e(route('complaint.track')) . '?ref=' . e($ref) . '">' . e(route('complaint.track')) . '</a></p>' : '';
                NotificationService::mail($email, $form['title'] . ': ' . $ref, '<p>नमस्ते ' . e((string) ($data['name'][1] ?? '')) . ',</p><p>' . e((string) ($form['success_message'] ?: 'आपका फ़ॉर्म मिल गया।')) . '</p>'
                    . '<p>संदर्भ नंबर: <b style="font-size:18px">' . e($ref) . '</b></p>' . $track . '<p style="font-size:12px;color:#777">यह ईमेल अपने आप भेजा गया है।</p>');
            }
            $t = self::type((string) $form['type']);
            $who = $data['name'][1] ?? ($data['email'][1] ?? 'अनजान');
            $title = $t[0] . ': ' . $form['title'] . ' (' . $ref . ')';
            $url = route('admin.submissions.show', ['id' => $id]);
            $emails = array_filter(array_map('trim', preg_split('/[,\s]+/', (string) $form['notify_emails']) ?: []), static fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
            NotificationService::notify('form', ['users' => NotificationService::usersWith($t[1] . '.view'), 'emails' => $emails], ['title' => $title, 'body' => 'भेजने वाला: ' . (is_string($who) ? $who : ''), 'url' => $url]);
        } catch (\Throwable $e) {
            logger()->warning('Form afterSubmit: ' . $e->getMessage());
        }
    }

    /** नोट/टाइमलाइन */
    public static function note(int $submissionId, string $type, string $body): void
    {
        db()->insert('form_notes', ['submission_id' => $submissionId, 'user_id' => auth()->id(), 'type' => $type, 'body' => mb_substr($body, 0, 5000), 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** स्लग (अंग्रेज़ी) बनाना: शीर्षक से, दोहराव नहीं */
    public static function slug(string $given, string $title, int $exceptId = 0, string $table = 'forms'): string
    {
        $base = \App\Helpers\Str::slug($given !== '' ? $given : $title, 100) ?: 'form';
        $slug = $base;
        $i = 2;
        while (db()->value("SELECT id FROM {p}$table WHERE slug = ? AND id <> ?", [$slug, $exceptId])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
