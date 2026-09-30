<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use App\Services\FormService;
use App\Services\HtmlSanitizer;

/** फ़ॉर्म बिल्डर: फ़ॉर्म और उनके खाने */
final class FormBuilderController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all("SELECT f.*, (SELECT COUNT(*) FROM {p}form_fields x WHERE x.form_id = f.id) fields,
                (SELECT COUNT(*) FROM {p}form_submissions s WHERE s.form_id = f.id) subs, (SELECT COUNT(*) FROM {p}form_submissions s WHERE s.form_id = f.id AND s.status = 'new') fresh
            FROM {p}forms f ORDER BY f.is_system DESC, f.id");
        return $this->view('admin/forms/index', ['items' => $items]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/forms/form', ['form' => null, 'fields' => [
            ['id' => 0, 'field_key' => 'name', 'label' => 'नाम', 'type' => 'text', 'required' => 1, 'options' => '', 'help' => '', 'placeholder' => '', 'width' => 6, 'accept' => '', 'max_mb' => null],
            ['id' => 0, 'field_key' => 'email', 'label' => 'ईमेल', 'type' => 'email', 'required' => 1, 'options' => '', 'help' => '', 'placeholder' => '', 'width' => 6, 'accept' => '', 'max_mb' => null],
        ]]);
    }

    public function store(Request $request): Response
    {
        [$data, $fields] = $this->payload($request, null);
        $id = db()->transaction(function () use ($data, $fields) {
            $id = db()->insert('forms', $data + ['created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            $this->saveFields($id, $fields);
            return $id;
        });
        AuditService::log('create', 'forms', $id, 'फ़ॉर्म बनाया: ' . $data['title']);
        return $this->toRoute('admin.forms.edit', ['id' => $id])->with('success', 'फ़ॉर्म बन गया। पता: /form/' . $data['slug'] . ' · पेज में लगाने के लिए [form:' . $data['slug'] . ']');
    }

    public function edit(Request $request, int $id): Response
    {
        $form = $this->find($id);
        return $this->view('admin/forms/form', ['form' => $form, 'fields' => FormService::fields($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        $form = $this->find($id);
        [$data, $fields] = $this->payload($request, $form);
        db()->transaction(function () use ($id, $data, $fields) {
            db()->update('forms', $data + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $this->saveFields($id, $fields);
        });
        AuditService::log('update', 'forms', $id, 'फ़ॉर्म बदला: ' . $data['title']);
        return $this->toRoute('admin.forms.edit', ['id' => $id])->with('success', 'फ़ॉर्म सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $form = $this->find($id);
        if ($form['is_system']) {
            return $this->back()->with('danger', 'सिस्टम फ़ॉर्म हटाया नहीं जा सकता; उसे "बंद" कर सकते हैं।');
        }
        db()->query('DELETE FROM {p}forms WHERE id = ?', [$id]);
        AuditService::log('delete', 'forms', $id, 'फ़ॉर्म हटाया: ' . $form['title']);
        return $this->toRoute('admin.forms.index')->with('success', 'फ़ॉर्म और उसके जमा फ़ॉर्म हट गए।');
    }

    public function duplicate(Request $request, int $id): Response
    {
        $form = $this->find($id);
        $slug = FormService::slug($form['slug'] . '-copy', $form['title']);
        $new = db()->insert('forms', ['title' => $form['title'] . ' (कॉपी)', 'slug' => $slug, 'type' => $form['type'] === 'career' ? 'custom' : $form['type'],
            'description' => $form['description'], 'success_message' => $form['success_message'], 'submit_label' => $form['submit_label'], 'status' => 'inactive',
            'created_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        foreach (FormService::fields($id) as $f) {
            unset($f['id']);
            db()->insert('form_fields', ['form_id' => $new] + array_diff_key($f, ['form_id' => 1]));
        }
        return $this->toRoute('admin.forms.edit', ['id' => $new])->with('success', 'कॉपी बनी (अभी बंद है)।');
    }

    /** [form डेटा, खाने] */
    private function payload(Request $request, ?array $form): array
    {
        $v = $this->validate($request, ['title' => 'required|min:2|max:190', 'slug' => 'nullable|slug|max:100', 'type' => 'required', 'success_message' => 'nullable|max:500',
            'submit_label' => 'nullable|max:60', 'notify_emails' => 'nullable|max:500', 'status' => 'required|in:active,inactive'],
            ['title' => 'शीर्षक', 'slug' => 'पता (स्लग)', 'type' => 'प्रकार', 'success_message' => 'सफलता संदेश', 'submit_label' => 'बटन', 'notify_emails' => 'सूचना ईमेल', 'status' => 'स्थिति']);
        $errors = [];
        $type = $form && $form['is_system'] ? $form['type'] : (string) $v['type'];
        if (!isset(FormService::TYPES[$type]) || (!$form && $type === 'career')) {
            $errors['type'] = 'प्रकार सही नहीं (नौकरी आवेदन फ़ॉर्म एक ही होता है)।';
        } elseif (!FormService::can($type, $type === 'custom' ? 'create' : 'view') && !can('forms.edit')) {
            $errors['type'] = 'इस प्रकार के फ़ॉर्म की अनुमति नहीं है।';
        }
        foreach (array_filter(array_map('trim', preg_split('/[,\s]+/', (string) $v['notify_emails']) ?: [])) as $em) {
            if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $errors['notify_emails'] = "“{$em}” सही ईमेल नहीं।";
            }
        }
        $fields = [];
        $keys = [];
        foreach ((array) $request->input('fields', []) as $i => $f) {
            if (!is_array($f)) {
                continue;
            }
            $label = trim(strip_tags((string) ($f['label'] ?? '')));
            $ftype = (string) ($f['type'] ?? 'text');
            if ($label === '') {
                continue;
            }
            $key = strtolower(trim((string) ($f['field_key'] ?? '')));
            $key = $key !== '' ? $key : (\App\Helpers\Str::slug($label, 40) ?: 'field');
            $key = str_replace('-', '_', $key);
            if (!preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key)) {
                $errors['fields'] = "खाने की key “{$key}” अंग्रेज़ी छोटे अक्षर/अंक/_ में हो।";
                continue;
            }
            if (isset($keys[$key])) {
                $errors['fields'] = "दो खानों की key “{$key}” एक जैसी है।";
                continue;
            }
            $keys[$key] = true;
            if (!isset(FormService::FIELD_TYPES[$ftype])) {
                $ftype = 'text';
            }
            $opts = implode("\n", FormService::options((string) ($f['options'] ?? '')));
            if (in_array($ftype, ['select', 'radio'], true) && count(FormService::options($opts)) < 2) {
                $errors['fields'] = "“{$label}” में कम से कम 2 विकल्प लिखें (एक लाइन में एक)।";
            }
            $accept = (string) ($f['accept'] ?? '');
            $fields[] = ['id' => (int) ($f['id'] ?? 0), 'field_key' => $key, 'label' => mb_substr($label, 0, 190), 'type' => $ftype, 'required' => !empty($f['required']) ? 1 : 0,
                'options' => $opts !== '' ? mb_substr($opts, 0, 3000) : null, 'help' => mb_substr(trim(strip_tags((string) ($f['help'] ?? ''))), 0, 300) ?: null,
                'placeholder' => mb_substr(trim(strip_tags((string) ($f['placeholder'] ?? ''))), 0, 190) ?: null, 'width' => (int) ($f['width'] ?? 12) === 6 ? 6 : 12,
                'accept' => $ftype === 'file' ? (isset(FormService::FILE_KINDS[$accept]) ? $accept : 'image,document') : null,
                'max_mb' => $ftype === 'file' ? max(1, min(100, (int) ($f['max_mb'] ?? 5) ?: 5)) : null, 'sort_order' => count($fields)];
        }
        if (!array_filter($fields, static fn($f) => $f['type'] !== 'heading')) {
            $errors['fields'] = 'कम से कम एक खाना जोड़ें।';
        }
        if ($type === 'complaint' && !isset($keys['email']) && !isset($keys['mobile'])) {
            $errors['fields'] = 'शिकायत फ़ॉर्म में "email" या "mobile" key वाला खाना ज़रूरी है (ट्रैकिंग के लिए)।';
        }
        if ($type === 'career' && !isset($keys['email'])) {
            $errors['fields'] = 'आवेदन फ़ॉर्म में "email" key वाला खाना ज़रूरी है।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $slug = $form && $form['is_system'] ? $form['slug'] : FormService::slug((string) ($v['slug'] ?? ''), (string) $v['title'], (int) ($form['id'] ?? 0));
        $data = ['title' => trim(strip_tags((string) $v['title'])), 'slug' => $slug, 'type' => $type,
            'description' => ($d = trim(HtmlSanitizer::clean((string) $request->input('description', ''), (string) config('app.url')))) !== '' ? $d : null,
            'success_message' => $v['success_message'] ? strip_tags((string) $v['success_message']) : null, 'submit_label' => $v['submit_label'] ? strip_tags((string) $v['submit_label']) : null,
            'notify_emails' => $v['notify_emails'] ?: null, 'status' => $v['status']];
        return [$data, $fields];
    }

    private function saveFields(int $formId, array $fields): void
    {
        $keep = [];
        // key का UNIQUE टकराव न हो: पहले मौजूदा keys अस्थायी करें
        db()->query("UPDATE {p}form_fields SET field_key = CONCAT('__', id) WHERE form_id = ?", [$formId]);
        foreach ($fields as $f) {
            $id = $f['id'];
            unset($f['id']);
            if ($id && db()->value('SELECT id FROM {p}form_fields WHERE id = ? AND form_id = ?', [$id, $formId])) {
                db()->update('form_fields', $f, 'id = ?', [$id]);
                $keep[] = $id;
            } else {
                $keep[] = db()->insert('form_fields', $f + ['form_id' => $formId]);
            }
        }
        db()->query('DELETE FROM {p}form_fields WHERE form_id = ?' . ($keep ? ' AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')' : ''), [$formId]);
    }

    private function find(int $id): array
    {
        return db()->first('SELECT * FROM {p}forms WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }
}
