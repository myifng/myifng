<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Location;
use App\Services\LocationService;
use App\Services\NewsQuery;
use App\Services\SeoService;

/** लोकेशन पेज: /uttar-pradesh/ , /uttar-pradesh/maharajganj/ , /…/local-news/ */
final class LocationController extends FrontController
{
    public function show(Request $request, string $path): Response
    {
        $path = trim(strtolower($path), '/');
        if (str_ends_with($path, '/local-news')) {
            $path = substr($path, 0, -11);
        }
        $loc = db()->first("SELECT * FROM {p}locations WHERE path = ? AND status = 'active'", [$path]);
        if (!$loc) {
            throw new HttpException(404);
        }
        $chain = array_values(array_filter([...Location::ancestors($loc), $loc], static fn($l) => $l['path'] !== null));
        \App\Services\AdService::setContext([], array_column([...Location::ancestors($loc), $loc], 'id'));
        \App\Services\AnalyticsService::context(['type' => 'location', 'id' => $loc['id'], 'location_id' => $loc['id']]);
        $crumbs = [['होम', url()]];
        foreach ($chain as $l) {
            $crumbs[] = [$l['name'], (int) $l['id'] === (int) $loc['id'] ? null : url($l['path'])];
        }
        // नीचे वाली लोकेशन (मंडल हो तो उसके नीचे के ज़िले भी)
        $children = db()->all(
            "SELECT l.name, l.path, l.is_popular FROM {p}locations l WHERE l.status = 'active' AND l.path IS NOT NULL
             AND (l.parent_id = ? OR l.parent_id IN (SELECT id FROM {p}locations WHERE parent_id = ? AND path IS NULL))
             ORDER BY l.is_popular DESC, l.sort_order, l.name LIMIT 80",
            [$loc['id'], $loc['id']]
        );
        $chips = array_map(static fn($c) => [$c['name'], url($c['path']), false], $children);
        [$w, $p] = NewsQuery::locationWhere((int) $loc['id']);
        $items = NewsQuery::page($w, $p, $this->page($request->int('page', 1)));
        $mine = (int) (my_city()['id'] ?? 0) === (int) $loc['id'];
        $canSet = in_array($loc['type'], LocationService::MY_CITY_TYPES, true);
        $actions = $canSet ? '<button type="button" class="btn-outline" data-set-city="' . (int) $loc['id'] . '" data-city-name="' . e($loc['name']) . '"' . ($mine ? ' disabled' : '') . '><i class="fa-solid fa-location-dot"></i> ' . ($mine ? 'यह आपका शहर है' : 'इसे मेरा शहर बनाएँ') . '</button>' : '';
        $parentName = count($chain) > 1 ? $chain[count($chain) - 2]['name'] : '';
        return $this->listing([
            'side' => $this->sidebar(null, (int) $loc['id'], $loc['name'] . ' में लोकप्रिय'),
            // Phase 14: इलाक़े की बड़ी ख़बर, वीडियो/फ़ोटो, आसपास, रिपोर्टर (सिर्फ़ पहले पेज पर)
            'local' => $items->page === 1 ? ['top' => \App\Services\LocalService::topStory((int) $loc['id']), 'media' => \App\Services\LocalService::media((int) $loc['id'], 6),
                'nearby' => \App\Services\LocalService::nearby($loc, 12), 'reporters' => \App\Services\LocalService::reporters((int) $loc['id'], 6)] : null,
            'heading' => $loc['name'] . ' समाचार', 'desc' => $parentName ? $parentName . ' · ' . ($loc['name_en'] ?? '') : ($loc['name_en'] ?? null),
            'crumbs' => $crumbs, 'chips' => $chips, 'chipsLabel' => 'यहाँ के इलाक़े', 'items' => $items, 'actions' => $actions,
            'empty' => $loc['name'] . ' की अभी कोई ख़बर नहीं है।',
            'seo' => [
                'title' => ($loc['meta_title'] ?: $loc['name'] . ' समाचार') . ($items->page > 1 ? ' - पेज ' . $items->page : ''),
                'description' => $loc['meta_description'] ?: $loc['name'] . ($parentName ? ', ' . $parentName : '') . ' की ताज़ा ख़बरें और स्थानीय समाचार, ' . setting('site_name') . ' पर।',
                'canonical' => url($loc['path']) . ($items->page > 1 ? '?page=' . $items->page : ''), 'jsonld' => SeoService::breadcrumbs($crumbs),
            ],
        ]);
    }
}
