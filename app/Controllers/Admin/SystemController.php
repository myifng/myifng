<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\SecurityService;
use App\Services\SystemService;

/** सिस्टम अपडेट: नई migrations चलाना (पहले से इंस्टॉल साइट पर नया वर्ज़न अपलोड करने के बाद) */
final class SystemController extends Controller
{
    public function migrate(Request $request): Response
    {
        if (!is_super_admin()) {
            throw new HttpException(403);
        }
        try {
            $ran = (new Migrator(db(), BASE_PATH . '/database/migrations'))->migrate();
            $added = PermissionService::sync(db(), config('modules.modules'));
            cache()->flush();
        } catch (\Throwable $e) {
            logger()->error('अपडेट विफल: ' . $e->getMessage());
            return new Response(app('view')->render('admin/system/update', ['pending' => ['त्रुटि: ' . $e->getMessage()]]), 500);
        }
        AuditService::log('migrate', 'system', null, 'सिस्टम अपडेट: ' . count($ran) . ' migration, ' . $added . ' नई अनुमतियाँ', null, ['migrations' => $ran]);
        return $this->toRoute('admin.dashboard')->with('success', 'सिस्टम अपडेट पूरा: ' . count($ran) . ' migration चलीं, ' . $added . ' नई अनुमतियाँ जुड़ीं।');
    }

    // ---------- Phase 15: सेहत, कैश, सफ़ाई ----------
    public function index(Request $request): Response
    {
        return $this->view('admin/system/index', ['health' => SystemService::health(), 'cache' => SystemService::cacheStats(), 'opcache' => SystemService::opcache(),
            'slow' => SystemService::slowLog(20)]);
    }

    public function clearCache(Request $request): Response
    {
        $g = (string) $request->input('group', '');
        if ($g !== '' && !preg_match('/^[a-z0-9_-]+$/', $g)) {
            throw new HttpException(422);
        }
        $n = SystemService::clearCache($g !== '' ? $g : null);
        if ($g === '' && function_exists('opcache_reset') && $request->input('opcache')) {
            @opcache_reset();
        }
        AuditService::log('cache', 'system', null, 'कैश साफ़: ' . ($g !== '' ? $g : 'सारा') . " ($n फ़ाइलें)");
        return $this->toRoute('admin.system.index')->with('success', ($g !== '' ? (SystemService::CACHE_GROUPS[$g] ?? $g) . ' का' : 'सारा') . " कैश साफ़ हो गया ($n फ़ाइलें)।");
    }

    public function cleanup(Request $request): Response
    {
        $task = (string) $request->input('task', '');
        $tasks = $task === 'all' ? array_diff(array_keys(SystemService::TASKS), ['optimize']) : [$task];
        $done = [];
        foreach ($tasks as $t) {
            if (!isset(SystemService::TASKS[$t])) {
                throw new HttpException(422);
            }
            @set_time_limit(300);
            $done[] = SystemService::TASKS[$t] . ': ' . SystemService::cleanup($t);
        }
        AuditService::log('cleanup', 'system', null, 'सफ़ाई: ' . implode('; ', $done));
        return $this->toRoute('admin.system.index')->with('success', 'सफ़ाई पूरी: ' . implode(' · ', $done));
    }

    // ---------- सुरक्षा ----------
    public function security(Request $request): Response
    {
        return $this->view('admin/system/security', ['checks' => SecurityService::checklist(), 'blocked' => SecurityService::blocked(), 'ip' => $request->ip(),
            'allow' => SecurityService::parseAllowlist((string) setting('admin_ip_allowlist', ''))['list']]);
    }

    public function unblock(Request $request): Response
    {
        $kind = (string) $request->input('kind');
        $key = trim((string) $request->input('key'));
        if (!in_array($kind, ['ip', 'email', 'all'], true)) {
            throw new HttpException(422);
        }
        $n = $kind === 'all' ? db()->query('DELETE FROM {p}login_attempts')->rowCount()
            : db()->query('DELETE FROM {p}login_attempts WHERE ' . ($kind === 'ip' ? 'ip' : 'email') . ' = ?', [$key])->rowCount();
        AuditService::log('unblock', 'system', null, 'लॉगिन रोक हटाई: ' . ($kind === 'all' ? 'सभी' : $kind . ' ' . $key) . " ($n प्रयास)");
        return $this->toRoute('admin.system.security')->with('success', 'रोक हट गई; अब लॉगिन हो सकेगा।');
    }
}
