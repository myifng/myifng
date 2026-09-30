<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\TrendingService;

/** सार्वजनिक: ट्रेंडिंग / सबसे ज़्यादा पढ़ी / सबसे ज़्यादा शेयर — श्रेणी और शहर के हिसाब से भी */
final class TrendingController extends FrontController
{
    public const TABS = ['trending' => ['ट्रेंडिंग', 'fa-fire'], 'read' => ['सबसे ज़्यादा पढ़ी', 'fa-ranking-star'], 'shared' => ['सबसे ज़्यादा शेयर', 'fa-share-nodes']];
    public const DAYS = ['1' => 'आज', '7' => '7 दिन', '30' => '30 दिन'];

    public function index(Request $request): Response
    {
        $tab = (string) $request->query('tab', 'trending');
        $tab = isset(self::TABS[$tab]) ? $tab : 'trending';
        $days = (string) $request->query('days', '7');
        $days = isset(self::DAYS[$days]) ? $days : '7';
        $cat = $request->int('cat') ?: null;
        $loc = $request->int('loc') ?: null;
        $category = $cat ? db()->first("SELECT id, name FROM {p}categories WHERE id = ? AND status = 'active'", [$cat]) : null;
        $location = $loc ? db()->first("SELECT id, name FROM {p}locations WHERE id = ? AND status = 'active'", [$loc]) : null;
        $cat = $category ? (int) $category['id'] : null;
        $loc = $location ? (int) $location['id'] : null;
        $items = match ($tab) {
            'read' => TrendingService::mostRead((int) $days, 20, $cat, $loc),
            'shared' => TrendingService::mostShared((int) $days, 20, $cat, $loc),
            default => TrendingService::trending(20, $cat, $loc),
        };
        $where = trim(($category['name'] ?? '') . ($location ? ($category ? ' · ' : '') . $location['name'] : ''));
        $title = self::TABS[$tab][0] . ($where !== '' ? ' - ' . $where : '');
        $cats = db()->all("SELECT id, name FROM {p}categories WHERE status = 'active' AND parent_id IS NULL ORDER BY sort_order, name");
        $cities = db()->all("SELECT id, name FROM {p}locations WHERE status = 'active' AND path IS NOT NULL AND (is_popular = 1 OR id = ?) ORDER BY name LIMIT 60", [$loc ?? 0]);
        return $this->view('front/trending', [
            'tab' => $tab, 'days' => $days, 'cat' => $cat, 'loc' => $loc, 'items' => $items, 'heading' => $title,
            'cats' => $cats, 'cities' => $cities, 'tags' => $tab === 'trending' && !$cat && !$loc ? TrendingService::tags(12) : [], 'side' => $this->sidebar(),
            'seo' => ['title' => $title, 'description' => 'अभी सबसे ज़्यादा पढ़ी और शेयर की जा रही ख़बरें' . ($where !== '' ? ' (' . $where . ')' : '') . ', ' . setting('site_name') . ' पर।',
                'canonical' => route('trending') . ($tab !== 'trending' ? '?tab=' . $tab : ''), 'robots' => $cat || $loc || $days !== '7' ? 'noindex,follow' : 'index,follow'],
        ]);
    }
}
