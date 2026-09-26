<?php
declare(strict_types=1);

namespace App\Core;

/**
 * दोबारा इस्तेमाल होने वाला वैलिडेटर। नियम: 'required|email|max:190|unique:users,email,5'
 * संदेश हिंदी में, खाने के नाम ($labels) के साथ।
 */
final class Validator
{
    private array $errors = [];
    private array $valid = [];

    public function __construct(private array $data, private array $rules, private array $labels = [])
    {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $rules, $labels);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return (bool) $this->errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->valid;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $label = $this->labels[$field] ?? $field;
            $empty = $value === null || $value === '' || $value === [];

            if (in_array('nullable', $rules, true) && $empty) {
                $this->valid[$field] = null;
                continue;
            }
            $numeric = (bool) array_intersect(['integer', 'numeric'], $rules);
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'nullable') {
                    continue;
                }
                if ($name !== 'required' && $name !== 'accepted' && $empty) {
                    continue;
                }
                $msg = $this->check($name, $arg, $value, $field, $label, $numeric);
                if ($msg !== null) {
                    $this->errors[$field] = $msg;
                    break;
                }
            }
            if (!isset($this->errors[$field])) {
                $this->valid[$field] = $value;
            }
        }
    }

    private function check(string $rule, ?string $arg, mixed $v, string $field, string $label, bool $numeric = false): ?string
    {
        $len = is_string($v) ? mb_strlen($v) : 0;
        return match ($rule) {
            'required' => ($v === null || $v === '' || $v === []) ? "$label ज़रूरी है।" : null,
            'accepted' => in_array($v, ['1', 1, 'on', 'yes', true], true) ? null : "$label स्वीकार करना ज़रूरी है।",
            'string' => is_string($v) ? null : "$label टेक्स्ट होना चाहिए।",
            'email' => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : "$label सही ईमेल पता नहीं है।",
            'url' => (filter_var($v, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', (string) $v)) ? null : "$label सही वेब पता (https://…) नहीं है।",
            'mobile' => preg_match('/^(\+91[\-\s]?)?[6-9]\d{9}$/', (string) $v) ? null : "$label 10 अंकों का सही मोबाइल नंबर नहीं है।",
            'numeric' => is_numeric($v) ? null : "$label संख्या होनी चाहिए।",
            'integer' => filter_var($v, FILTER_VALIDATE_INT) !== false ? null : "$label पूर्ण संख्या होनी चाहिए।",
            'min' => $numeric ? ((float) $v < (float) $arg ? "$label कम से कम $arg होना चाहिए।" : null) : ($len < (int) $arg ? "$label कम से कम $arg अक्षर का हो।" : null),
            'max' => $numeric ? ((float) $v > (float) $arg ? "$label $arg से ज़्यादा नहीं हो सकता।" : null) : ($len > (int) $arg ? "$label $arg अक्षरों से ज़्यादा नहीं हो सकता।" : null),
            'in' => in_array((string) $v, explode(',', (string) $arg), true) ? null : "$label का चुना गया मान मान्य नहीं है।",
            'regex' => preg_match((string) $arg, (string) $v) ? null : "$label का प्रारूप सही नहीं है।",
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $v) ? null : "$label में सिर्फ़ छोटे अंग्रेज़ी अक्षर, अंक और - हों।",
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? null : "$label सही रंग कोड (#d71920) नहीं है।",
            'date' => strtotime((string) $v) !== false ? null : "$label सही तारीख़ नहीं है।",
            'confirmed' => ($v === ($this->data[$field . '_confirmation'] ?? null)) ? null : "$label दोनों बार एक जैसा नहीं है।",
            'same' => ($v === ($this->data[$arg] ?? null)) ? null : "$label मेल नहीं खाता।",
            'password' => (mb_strlen((string) $v) >= 8 && preg_match('/[A-Za-z]/', (string) $v) && preg_match('/\d/', (string) $v)) ? null : "$label कम से कम 8 अक्षर का हो और उसमें अक्षर व अंक दोनों हों।",
            'unique' => $this->unique((string) $arg, $v) ? null : "यह $label पहले से इस्तेमाल हो रहा है।",
            'exists' => $this->exists((string) $arg, $v) ? null : "$label का चुना गया मान मौजूद नहीं है।",
            'array' => is_array($v) ? null : "$label सूची होनी चाहिए।",
            default => throw new \InvalidArgumentException("अज्ञात वैलिडेशन नियम: $rule"),
        };
    }

    /** unique:table,column,exceptId */
    private function unique(string $arg, mixed $v): bool
    {
        [$table, $col, $except] = array_pad(explode(',', $arg), 3, null);
        $sql = "SELECT COUNT(*) FROM {p}$table WHERE `$col` = ?";
        $params = [$v];
        if ($except) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $except;
        }
        return (int) db()->value($sql, $params) === 0;
    }

    /** exists:table,column */
    private function exists(string $arg, mixed $v): bool
    {
        [$table, $col] = array_pad(explode(',', $arg), 2, 'id');
        return (int) db()->value("SELECT COUNT(*) FROM {p}$table WHERE `$col` = ?", [$v]) > 0;
    }
}
