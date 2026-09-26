<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\LocationService;

/** "मेरा शहर": पाठक अपना शहर/ज़िला चुनता है; कुकी में सिर्फ़ लोकेशन ID (आगे "आपके शहर की ख़बरें" इसी से) */
final class LocationApiController extends Controller
{
    public function search(Request $request): Response
    {
        $q = mb_substr($request->str('q'), 0, 60);
        $items = $q === ''
            ? cache()->remember('locations.popular', 3600, static fn() => LocationService::search('', ['state', 'district', 'city'], true, 50))
            : LocationService::search($q, LocationService::MY_CITY_TYPES, true, 12);
        if ($q === '') {
            $items = array_slice(array_values(array_filter($items, static fn($r) => (int) $r['is_popular'] === 1)), 0, 12);
        }
        $out = array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'label' => $r['label'], 'type' => $r['type_label']], $items);
        return $this->json(['items' => $out])->header('Cache-Control', 'public, max-age=300');
    }

    public function save(Request $request): Response
    {
        $id = $request->int('location_id');
        $secure = $request->isSecure();
        if ($id === 0) {
            setcookie('my_city', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax']);
            return $this->json(['ok' => true, 'name' => null, 'message' => 'शहर हटा दिया गया।']);
        }
        $loc = db()->first(
            "SELECT id, name, type FROM {p}locations WHERE id = ? AND status = 'active' AND type IN ('" . implode("','", LocationService::MY_CITY_TYPES) . "')",
            [$id]
        );
        if (!$loc) {
            return $this->json(['ok' => false, 'message' => 'यह शहर हमारी सूची में नहीं है।'], 422);
        }
        // JS इसे पढ़ती है, इसलिए HttpOnly नहीं; इसमें सिर्फ़ ID है, कोई निजी जानकारी नहीं
        setcookie('my_city', (string) $loc['id'], ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax']);
        return $this->json(['ok' => true, 'id' => (int) $loc['id'], 'name' => $loc['name'], 'message' => $loc['name'] . ' आपका शहर चुना गया।']);
    }
}
