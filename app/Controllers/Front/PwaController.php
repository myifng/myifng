<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\PwaService;

/** Phase 16: PWA: manifest, आइकन, सर्विस वर्कर, ऑफ़लाइन पेज */
final class PwaController extends Controller
{
    public function manifest(Request $request): Response
    {
        if (!PwaService::enabled()) {
            throw new HttpException(404);
        }
        return Response::json(PwaService::manifest())->header('Content-Type', 'application/manifest+json; charset=utf-8')->header('Cache-Control', 'public, max-age=3600');
    }

    public function icon(Request $request, int $size): Response
    {
        return $this->sendIcon($size, false);
    }

    public function maskable(Request $request, int $size): Response
    {
        return $this->sendIcon($size, true);
    }

    private function sendIcon(int $size, bool $maskable): Response
    {
        $file = PwaService::icon($size, $maskable);
        if (!$file) {
            throw new HttpException(404);
        }
        AnalyticsService::skip();
        // URL में ?v= वर्ज़न है, इसलिए लंबा कैश सुरक्षित
        return (new Response((string) file_get_contents($file)))->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=2592000')->header('X-Content-Type-Options', 'nosniff');
    }

    /** सर्विस वर्कर: पुश (हमेशा) + कैश (PWA चालू हो तो); सेटिंग सबसे ऊपर self.NP में */
    public function serviceWorker(Request $request): Response
    {
        $cfg = json_encode(PwaService::swConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        $js = 'self.NP = ' . $cfg . ";\n" . (string) file_get_contents(BASE_PATH . '/public/assets/js/sw.js');
        return (new Response($js, 200))->header('Content-Type', 'application/javascript; charset=UTF-8')->header('Service-Worker-Allowed', '/')->header('Cache-Control', 'no-cache');
    }

    /** ऑफ़लाइन पेज: सर्विस वर्कर इसे पहले से कैश करता है; अपने में पूरा (मेनू/विज्ञापन/टिकर नहीं) */
    public function offline(Request $request): Response
    {
        AnalyticsService::skip();
        return $this->view('front/offline', [])->header('X-Robots-Tag', 'noindex');
    }
}
