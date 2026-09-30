<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\LocalService;
use App\Services\LocationService;
use App\Services\NewsQuery;
use App\Services\TrendingService;

/** लोकल न्यूज़ हब (/local) और मेरा शहर (/my-city) */
final class LocalController extends FrontController
{
    public function index(Request $request): Response
    {
        $states = LocalService::states();
        $stateId = $request->int('state');
        $city = LocationService::myCity();
        if (!$stateId) {
            // मेरे शहर का राज्य, वरना सबसे ज़्यादा ख़बरों वाला
            $stateId = ($city ? LocalService::ancestorOfType((int) $city['id'], 'state') : null) ?? (int) ($states[0]['id'] ?? 0);
        }
        $state = null;
        foreach ($states as $s) {
            if ($s['id'] === $stateId) {
                $state = $s;
            }
        }
        AnalyticsService::context(['type' => 'location', 'location_id' => $stateId ?: null]);
        $districts = $state ? LocalService::districts($stateId) : [];
        return $this->view('front/local/index', ['states' => $states, 'state' => $state, 'districts' => $districts, 'city' => $city, 'side' => $this->sidebar(),
            'seo' => ['title' => 'लोकल न्यूज़' . ($state ? ': ' . $state['name'] . ' के सभी ज़िले' : ''), 'description' => ($state ? $state['name'] . ' के' : 'हर') . ' ज़िले और शहर की ताज़ा स्थानीय ख़बरें, ' . setting('site_name') . ' पर।',
                'canonical' => route('local') . ($request->int('state') ? '?state=' . $stateId : ''), 'robots' => $request->int('state') ? 'noindex,follow' : 'index,follow']]);
    }

    public function myCity(Request $request): Response
    {
        $city = LocationService::myCity();
        $data = ['city' => $city, 'items' => [], 'nearby' => [], 'nearbyNews' => [], 'popular' => [], 'reporters' => [], 'side' => $this->sidebar(),
            'popularCities' => db()->all("SELECT id, name FROM {p}locations WHERE status = 'active' AND is_popular = 1 AND type IN ('" . implode("','", LocationService::MY_CITY_TYPES) . "') ORDER BY name LIMIT 16"),
            'seo' => ['title' => 'मेरा शहर' . ($city ? ': ' . $city['name'] . ' की ख़बरें' : ''), 'robots' => 'noindex,follow', 'canonical' => route('my_city')]];
        if ($city) {
            $loc = db()->first('SELECT * FROM {p}locations WHERE id = ?', [$city['id']]);
            AnalyticsService::context(['type' => 'location', 'id' => $city['id'], 'location_id' => $city['id']]);
            [$w, $p] = NewsQuery::locationWhere((int) $city['id']);
            $data['items'] = NewsQuery::list($w, $p, 15);
            $data['popular'] = TrendingService::mostRead(7, 5, null, (int) $city['id']);
            $data['nearby'] = LocalService::nearby($loc, 12);
            $data['reporters'] = LocalService::reporters((int) $city['id'], 6);
            // आसपास की ख़बरें: ऊपर वाले (ज़िला/मंडल) की, इस शहर को छोड़कर
            if ($loc['parent_id'] && ($parent = db()->first("SELECT id, type FROM {p}locations WHERE id = ? AND type <> 'country'", [$loc['parent_id']]))) {
                [$pw, $pp] = NewsQuery::locationWhere((int) $parent['id']);
                $mine = NewsQuery::descendants((int) $city['id']);
                $data['nearbyNews'] = NewsQuery::list("$pw AND n.location_id NOT IN (" . \App\Core\Database::in($mine) . ')', [...$pp, ...$mine], 6);
            }
        }
        return $this->view('front/local/my-city', $data);
    }
}
