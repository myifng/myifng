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
}
