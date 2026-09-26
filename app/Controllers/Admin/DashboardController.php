<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * डैशबोर्ड (Phase 1 का आधार)। Phase 2 में ख़बर, रिपोर्टर, विज़िटर आदि के पूरे आँकड़े जुड़ेंगे।
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $db = db();
        $stats = [
            'users' => (int) $db->value('SELECT COUNT(*) FROM {p}users WHERE deleted_at IS NULL'),
            'active' => (int) $db->value("SELECT COUNT(*) FROM {p}users WHERE deleted_at IS NULL AND status = 'active'"),
            'roles' => (int) $db->value('SELECT COUNT(*) FROM {p}roles'),
            'logins_today' => (int) $db->value("SELECT COUNT(*) FROM {p}login_history WHERE status = 'success' AND created_at >= CURDATE()"),
            'failed_24h' => (int) $db->value("SELECT COUNT(*) FROM {p}login_history WHERE status IN ('failed','blocked') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
                + (int) $db->value('SELECT COUNT(*) FROM {p}login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'),
        ];

        // पिछले 14 दिन के लॉगिन (सफल / असफल)
        $rows = $db->all(
            "SELECT DATE(created_at) d, SUM(status = 'success') ok, SUM(status IN ('failed','blocked')) bad
             FROM {p}login_history WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)"
        );
        $byDay = array_column($rows, null, 'd');
        $chart = ['labels' => [], 'ok' => [], 'bad' => []];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $chart['labels'][] = (int) date('j', strtotime($d)) . ' ' . mb_substr(HINDI_MONTHS[(int) date('n', strtotime($d)) - 1], 0, 3);
            $chart['ok'][] = (int) ($byDay[$d]['ok'] ?? 0);
            $chart['bad'][] = (int) ($byDay[$d]['bad'] ?? 0);
        }

        $roleSplit = $db->all('SELECT r.name, COUNT(u.id) c FROM {p}roles r LEFT JOIN {p}users u ON u.role_id = r.id AND u.deleted_at IS NULL GROUP BY r.id ORDER BY r.level DESC');
        $activity = can('audit.view') ? $db->all('SELECT * FROM {p}audit_logs ORDER BY id DESC LIMIT 8') : [];
        $myLogins = $db->all('SELECT * FROM {p}login_history WHERE user_id = ? ORDER BY id DESC LIMIT 5', [auth()->id()]);

        // आगे आने वाले मॉड्यूल (रोडमैप)
        $phase = (int) config('app.phase', 1);
        $roadmap = [];
        foreach (config('modules.modules') as $m) {
            if ($m['phase'] > $phase) {
                $roadmap[$m['phase']][] = $m;
            }
        }
        ksort($roadmap);

        $system = [
            'PHP' => PHP_VERSION,
            'डेटाबेस' => (string) $db->value('SELECT VERSION()'),
            'सॉफ़्टवेयर वर्ज़न' => config('app.version') . ' (Phase ' . $phase . ')',
            'टाइमज़ोन' => date_default_timezone_get(),
            'अपलोड सीमा' => ini_get('upload_max_filesize'),
            'GD (इमेज)' => extension_loaded('gd') ? 'उपलब्ध' : 'नहीं',
        ];

        return $this->view('admin/dashboard/index', compact('stats', 'chart', 'roleSplit', 'activity', 'myLogins', 'roadmap', 'system'));
    }
}
