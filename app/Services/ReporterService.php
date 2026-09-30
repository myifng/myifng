<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\Str;
use App\Models\PasswordReset;
use App\Models\Reporter;
use App\Models\ReporterApplication;

/**
 * रिपोर्टर: आवेदन संख्या, मंज़ूरी → यूज़र खाता + रिपोर्टर ID, नवीनीकरण, निलंबन/इस्तीफ़ा, वैधता ख़त्म, प्रदर्शन।
 */
final class ReporterService
{
    /** RPT-APP-2026-000123 (ID से, इसलिए कभी दोहराव नहीं) */
    public static function appNo(int $id): string
    {
        return sprintf('RPT-APP-%s-%06d', date('Y'), $id);
    }

    /** RPT-2026-0007 */
    public static function code(int $id): string
    {
        $prefix = preg_match('/^[A-Z]{2,8}$/', (string) setting('reporter_id_prefix', 'RPT')) ? setting('reporter_id_prefix', 'RPT') : 'RPT';
        return sprintf('%s-%s-%04d', $prefix, date('Y'), $id);
    }

    public static function forUser(int $userId): ?array
    {
        return db()->first('SELECT * FROM {p}reporters WHERE user_id = ?', [$userId]);
    }

    /** आवेदन की स्थिति बदलें + टिप्पणी (एक ही जगह से) */
    public static function applicationStatus(array $app, string $to, string $message = '', bool $public = false): void
    {
        $data = ['status' => $to];
        if ($public) {
            $data['public_note'] = mb_substr($message, 0, 500) ?: null;
        }
        ReporterApplication::update((int) $app['id'], $data);
        db()->insert('application_remarks', ['application_id' => $app['id'], 'user_id' => auth()->id(), 'type' => $public ? 'correction' : 'status',
            'message' => $message ?: null, 'from_status' => $app['status'], 'to_status' => $to, 'created_at' => now()]);
    }

    /**
     * मंज़ूरी: यूज़र (Reporter रोल) + रिपोर्टर प्रोफ़ाइल; पासवर्ड सेट करने का लिंक (72 घंटे)
     * @return array{reporter_id: int, user_id: int, link: string}
     */
    public static function approve(array $app, array $d): array
    {
        return db()->transaction(static function () use ($app, $d): array {
            $roleId = (int) db()->value("SELECT id FROM {p}roles WHERE slug = 'reporter'");
            $userId = db()->insert('users', [
                'name' => $app['full_name'], 'email' => mb_strtolower($app['email']), 'mobile' => $app['mobile'],
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'role_id' => $roleId, 'status' => 'active',
                'bio' => mb_substr((string) $d['designation'], 0, 100), 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $docs = (array) json_decode((string) $app['documents'], true);
            $photo = self::publishPhoto($docs['photo'] ?? null);
            if ($photo) {
                db()->query('UPDATE {p}users SET avatar = ? WHERE id = ?', [$photo, $userId]);
            }
            $rid = Reporter::create([
                'user_id' => $userId, 'reporter_code' => 'TMP-' . bin2hex(random_bytes(6)), 'application_id' => $app['id'],
                'designation' => $d['designation'], 'reporter_type' => $d['reporter_type'], 'beat_category_id' => $d['beat_category_id'],
                'area_location_id' => $d['area_location_id'] ?? $app['preferred_area_id'], 'district_id' => $d['district_id'] ?? $app['district_id'],
                'state_id' => $d['state_id'] ?? $app['state_id'], 'bureau_id' => $d['bureau_id'], 'joining_date' => $d['joining_date'], 'valid_until' => $d['valid_until'],
                'status' => 'active', 'photo' => $photo, 'guardian_name' => $app['guardian_name'], 'dob' => $app['dob'], 'mobile' => $app['mobile'],
                'address' => trim($app['address'] . ($app['city'] ? ', ' . $app['city'] : '') . ' - ' . $app['pincode']), 'kyc' => $app['documents'], 'created_by' => auth()->id(),
            ]);
            Reporter::update($rid, ['reporter_code' => self::code($rid)]);
            self::applicationStatus($app, 'approved', 'मंज़ूर: ' . self::code($rid));
            ReporterApplication::update((int) $app['id'], ['reporter_id' => $rid]);
            $token = Str::random(32);
            PasswordReset::create(['user_id' => $userId, 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 72 * 3600)]);
            return ['reporter_id' => $rid, 'user_id' => $userId, 'link' => route('reporter.password.reset', ['token' => $token])];
        });
    }

    /** आवेदन की निजी फ़ोटो → सार्वजनिक uploads/reporters (सत्यापन पेज और ID कार्ड पर दिखती है); GD से दोबारा सेव */
    public static function publishPhoto(?string $privatePath): ?string
    {
        $abs = PrivateFileService::absolute($privatePath);
        if (!$abs || str_ends_with($abs, '.pdf')) {
            return null;
        }
        $dir = BASE_PATH . '/public/uploads/reporters/' . date('Y/m');
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return null;
        }
        $mime = str_ends_with($abs, '.png') ? 'image/png' : 'image/jpeg';
        $name = bin2hex(random_bytes(10)) . ($mime === 'image/png' ? '.png' : '.jpg');
        if (!extension_loaded('gd') || !UploadService::resize($abs, "$dir/$name", $mime, 600)) {
            return null; // GD न हो तो निजी फ़ाइल सार्वजनिक नहीं करते
        }
        return 'reporters/' . date('Y/m') . '/' . $name;
    }

    /** स्थिति: निलंबित/इस्तीफ़ा पर लॉगिन बंद; दोबारा चालू पर खुला */
    public static function setStatus(array $r, string $status, string $reason): void
    {
        db()->transaction(static function () use ($r, $status, $reason) {
            Reporter::update((int) $r['id'], ['status' => $status, 'status_reason' => mb_substr($reason, 0, 300) ?: null]);
            $userStatus = $status === 'active' ? 'active' : ($status === 'suspended' ? 'suspended' : 'inactive');
            db()->query('UPDATE {p}users SET status = ? WHERE id = ?', [$userStatus, $r['user_id']]);
            if ($status !== 'active') {
                // जारी ID कार्ड/पत्र अमान्य
                db()->query("UPDATE {p}reporter_documents SET status = 'revoked', revoked_reason = ? WHERE reporter_id = ? AND status = 'active' AND type IN ('id_card','authorization','press_certificate')",
                    ['रिपोर्टर की स्थिति: ' . (Reporter::STATUSES[$status][0] ?? $status), $r['id']]);
            }
        });
    }

    public static function renew(array $r, int $months): string
    {
        $from = max(strtotime((string) $r['valid_until']), strtotime('today'));
        $until = date('Y-m-d', strtotime("+$months months", $from));
        Reporter::update((int) $r['id'], ['valid_until' => $until, 'status' => $r['status'] === 'expired' ? 'active' : $r['status']]);
        if ($r['status'] === 'expired') {
            db()->query("UPDATE {p}users SET status = 'active' WHERE id = ? AND status = 'inactive'", [$r['user_id']]);
        }
        return $until;
    }

    /** वैधता ख़त्म: स्थिति expired (लॉगिन चालू रहता है ताकि नवीनीकरण के लिए संपर्क कर सके; ID कार्ड अमान्य) */
    public static function expireDue(): int
    {
        return db()->query("UPDATE {p}reporters SET status = 'expired' WHERE status = 'active' AND valid_until < CURDATE()")->rowCount();
    }

    /** प्रदर्शन */
    public static function stats(int $userId): array
    {
        $r = db()->first("SELECT COUNT(*) total, SUM(status = 'published') published, SUM(status = 'rejected') rejected, SUM(status IN ('submitted','review','fact_check','approved')) pending,
                          SUM(status = 'draft') drafts, COALESCE(SUM(CASE WHEN status = 'published' THEN views END), 0) views,
                          SUM(status = 'published' AND published_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) this_month
                          FROM {p}news WHERE reporter_id = ? AND deleted_at IS NULL", [$userId]);
        $a = db()->first("SELECT COUNT(*) total, SUM(status IN ('completed')) done, SUM(status IN ('open','accepted','in_progress') AND deadline < NOW()) late FROM {p}assignments WHERE reporter_id = ?", [$userId]);
        return array_map('intval', $r) + ['assignments' => (int) $a['total'], 'assignments_done' => (int) $a['done'], 'assignments_late' => (int) $a['late']];
    }

    /** QR/सत्यापन का हस्ताक्षर (APP_KEY से; नकली लिंक नहीं बन सकता) */
    public static function token(string $code): string
    {
        return substr(hash_hmac('sha256', 'verify|' . $code, (string) config('app.key')), 0, 16);
    }

    public static function verifyUrl(array $r): string
    {
        return route('verify.qr', ['code' => $r['reporter_code'], 'token' => self::token($r['reporter_code'])]);
    }

    /** दस्तावेज़ नंबर: IDC-2026-00012 */
    public static function issueDocument(array $r, string $type): array
    {
        $code = \App\Models\ReporterDocument::TYPES[$type][1];
        $id = db()->insert('reporter_documents', [
            'reporter_id' => $r['id'], 'type' => $type, 'doc_no' => 'TMP-' . bin2hex(random_bytes(6)), 'issued_at' => date('Y-m-d'),
            'valid_until' => in_array($type, ['id_card', 'authorization', 'press_certificate'], true) ? $r['valid_until'] : null,
            'issued_by' => auth()->id(), 'status' => 'active', 'created_at' => now(),
        ]);
        $no = sprintf('%s-%s-%05d', $code, date('Y'), $id);
        db()->query('UPDATE {p}reporter_documents SET doc_no = ? WHERE id = ?', [$no, $id]);
        // एक प्रकार का एक ही सक्रिय कार्ड/पत्र
        if (in_array($type, ['id_card', 'authorization', 'press_certificate'], true)) {
            db()->query("UPDATE {p}reporter_documents SET status = 'revoked', revoked_reason = ? WHERE reporter_id = ? AND type = ? AND status = 'active' AND id <> ?", ['नया जारी: ' . $no, $r['id'], $type, $id]);
        }
        return (array) db()->first('SELECT * FROM {p}reporter_documents WHERE id = ?', [$id]);
    }
}
