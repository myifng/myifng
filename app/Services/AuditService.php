<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ऑडिट लॉग: कौन, कब, क्या, किस मॉड्यूल में, किस रिकॉर्ड पर, IP और डिवाइस के साथ।
 * पासवर्ड जैसी संवेदनशील जानकारी कभी दर्ज नहीं होती।
 */
final class AuditService
{
    private const HIDDEN = ['password', 'password_confirmation', 'current_password', 'token', 'token_hash', '_csrf', '_method'];

    public static function log(string $action, string $module, int|string|null $recordId = null, string $description = '', ?array $old = null, ?array $new = null): void
    {
        try {
            $user = auth()->user();
            $req = app('request');
            [$old, $new] = self::diff($old, $new);
            db()->insert('audit_logs', [
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
                'role' => $user['role_name'] ?? null,
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId !== null ? (string) $recordId : null,
                'description' => mb_substr($description, 0, 500),
                'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'ip' => $req->ip(),
                'user_agent' => $req->userAgent(),
                'url' => mb_substr($req->fullUrl(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            logger()->error('ऑडिट लॉग नहीं लिखा जा सका: ' . $e->getMessage());
        }
    }

    /** सिर्फ़ बदले हुए मान रखें, संवेदनशील हटाएँ */
    private static function diff(?array $old, ?array $new): array
    {
        $clean = static fn(?array $a) => $a === null ? null : array_diff_key($a, array_flip(self::HIDDEN));
        $old = $clean($old);
        $new = $clean($new);
        if ($old !== null && $new !== null) {
            $changedNew = [];
            $changedOld = [];
            foreach ($new as $k => $v) {
                if (!array_key_exists($k, $old) || (string) $old[$k] !== (string) $v) {
                    $changedNew[$k] = $v;
                    $changedOld[$k] = $old[$k] ?? null;
                }
            }
            return [$changedOld ?: null, $changedNew ?: null];
        }
        return [$old, $new];
    }
}
