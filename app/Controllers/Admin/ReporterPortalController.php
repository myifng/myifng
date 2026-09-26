<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReporterService;

/** रिपोर्टर पोर्टल: मेरा रिपोर्टर प्रोफ़ाइल, प्रदर्शन, अपने ID कार्ड/पत्र (सिर्फ़ अपने) */
final class ReporterPortalController extends Controller
{
    private function mine(): array
    {
        return ReporterService::forUser((int) auth()->id()) ?? throw new HttpException(404, 'आपका रिपोर्टर प्रोफ़ाइल नहीं है।');
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/reporters/portal', ReporterController::profileData($this->mine()));
    }

    public function document(Request $request, int $doc): Response
    {
        $r = $this->mine();
        $d = db()->first("SELECT * FROM {p}reporter_documents WHERE id = ? AND reporter_id = ? AND status = 'active'", [$doc, $r['id']]) ?? throw new HttpException(404);
        return ReporterController::renderDocument($r, $d);
    }
}
