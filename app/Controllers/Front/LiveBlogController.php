<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\LiveBlogService;
use App\Services\NewsQuery;

/** लाइव ब्लॉग के नए अपडेट (पाठक का पेज हर 30 सेकंड में पूछता है); 10 सेकंड कैश */
final class LiveBlogController extends Controller
{
    public function updates(Request $request, int $id): Response
    {
        $after = max(0, $request->int('after'));
        $data = cache()->remember('live.' . $id . '.' . $after, 10, static function () use ($id, $after): ?array {
            $blog = db()->first('SELECT b.* FROM {p}live_blogs b JOIN {p}news n ON n.id = b.news_id WHERE b.id = ? AND ' . NewsQuery::PUBLISHED, [$id]);
            if (!$blog) {
                return null;
            }
            $items = array_reverse(LiveBlogService::updates($id, $after, 50)); // पुराने पहले, ताकि JS ऊपर जोड़ता जाए
            return [
                'status' => $blog['status'],
                'items' => array_map(static fn($u) => ['id' => (int) $u['id'], 'html' => LiveBlogService::render($u)], $items),
            ];
        });
        if ($data === null) {
            throw new HttpException(404);
        }
        return $this->json(['ok' => true] + $data)->header('Cache-Control', 'private, max-age=5');
    }
}
