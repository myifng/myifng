<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\DashboardService;

/** डैशबोर्ड: विजेट, चार्ट, गतिविधि, सेटअप चेकलिस्ट, रोडमैप */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $db = db();
        $phase = (int) config('app.phase', 1);
        $roadmap = [];
        foreach (config('modules.modules') as $m) {
            if ($m['phase'] > $phase) {
                $roadmap[$m['phase']][] = $m;
            }
        }
        ksort($roadmap);

        return $this->view('admin/dashboard/index', [
            'cards' => DashboardService::cards(),
            'loginChart' => DashboardService::loginChart(),
            'activityChart' => can('audit.view') ? DashboardService::activityChart() : null,
            'activity' => can('audit.view') ? $db->all('SELECT * FROM {p}audit_logs ORDER BY id DESC LIMIT 8') : [],
            'myLogins' => $db->all('SELECT * FROM {p}login_history WHERE user_id = ? ORDER BY id DESC LIMIT 5', [auth()->id()]),
            'checklist' => DashboardService::checklist(),
            'pending' => DashboardService::pendingMigrations(),
            'roadmap' => $roadmap,
            'recentPages' => can('pages.view') ? $db->all('SELECT id, title, status, updated_at FROM {p}pages WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 5') : [],
            'system' => [
                'PHP' => PHP_VERSION,
                'डेटाबेस' => (string) $db->value('SELECT VERSION()'),
                'सॉफ़्टवेयर वर्ज़न' => config('app.version') . ' (Phase ' . $phase . ')',
                'टाइमज़ोन' => date_default_timezone_get(),
                'अपलोड सीमा' => ini_get('upload_max_filesize'),
                'GD (इमेज)' => extension_loaded('gd') ? 'उपलब्ध' . (function_exists('imagewebp') ? ' · WebP' : '') : 'नहीं',
            ],
        ]);
    }
}
