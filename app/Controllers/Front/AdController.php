<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdService;
use App\Services\EmbedService;

/** विज्ञापन: क्लिक (गिनकर विज्ञापनदाता की साइट पर) और इम्प्रेशन (JS से) */
final class AdController extends Controller
{
    public function click(Request $request, int $id): Response
    {
        $ad = db()->first('SELECT id, target_url, status FROM {p}ads WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $to = EmbedService::href($ad['target_url']);
        if (!$to) {
            throw new HttpException(404);
        }
        if ($ad['status'] === 'active') {
            AdService::recordClick($ad);
        }
        return $this->redirect($to)->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'origin');
    }

    public function impressions(Request $request): Response
    {
        $ids = (array) ($request->post()['ids'] ?? []);
        return $this->json(['ok' => true, 'counted' => AdService::recordImpressions($ids)]);
    }
}
