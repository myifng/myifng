<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\AdSlot;
use App\Services\AdService;
use App\Services\AuditService;

/** विज्ञापन स्लॉट: पहले से बने (सिस्टम) + कस्टम ([ad:key] या होमपेज बिल्डर) */
final class AdSlotController extends Controller
{
    public function index(Request $request): Response
    {
        $items = db()->all("SELECT s.*, (SELECT COUNT(*) FROM {p}ad_placements p JOIN {p}ads a ON a.id = p.ad_id WHERE p.slot_id = s.id AND a.status = 'active'
                              AND (a.start_at IS NULL OR a.start_at <= NOW()) AND (a.end_at IS NULL OR a.end_at > NOW())) AS running
                            FROM {p}ad_slots s ORDER BY s.is_system DESC, s.id");
        return $this->view('admin/ads/slots', ['items' => $items, 'edit' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $id = AdSlot::create($data + ['is_system' => 0, 'placement' => 'custom']);
        AdService::changed();
        AuditService::log('create', 'ads', $id, 'विज्ञापन स्लॉट: ' . $data['slot_key']);
        return $this->toRoute('admin.ads.slots')->with('success', 'स्लॉट बन गया। सामग्री में [ad:' . $data['slot_key'] . '] लिखें या होमपेज बिल्डर में “विज्ञापन” ब्लॉक से चुनें।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->view('admin/ads/slots', ['items' => [], 'edit' => AdSlot::find($id) ?? throw new HttpException(404)]);
    }

    public function update(Request $request, int $id): Response
    {
        $slot = AdSlot::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $slot);
        if ($slot['is_system']) {
            unset($data['slot_key']); // सिस्टम स्लॉट की key टेम्पलेट में लिखी है
        }
        AdSlot::update($id, $data);
        AdService::changed();
        AuditService::log('update', 'ads', $id, 'स्लॉट बदला: ' . $slot['slot_key'], $slot, $data);
        return $this->toRoute('admin.ads.slots')->with('success', 'स्लॉट सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $slot = AdSlot::find($id) ?? throw new HttpException(404);
        if ($slot['is_system']) {
            return $this->back()->with('danger', 'पहले से बना स्लॉट हटाया नहीं जा सकता; उसे बंद करें।');
        }
        AdSlot::delete($id);
        AdService::changed();
        AuditService::log('delete', 'ads', $id, 'स्लॉट हटाया: ' . $slot['slot_key']);
        return $this->toRoute('admin.ads.slots')->with('success', 'स्लॉट हटा दिया गया।');
    }

    private function payload(Request $request, ?array $slot): array
    {
        $v = $this->validate($request, [
            'slot_key' => $slot && $slot['is_system'] ? 'nullable' : ['required', 'regex:/^[a-z0-9_-]{2,60}$/'], 'name' => 'required|max:120', 'size' => 'nullable|max:40',
            'max_ads' => 'required|integer|min:1|max:10', 'status' => 'required|in:active,inactive', 'description' => 'nullable|max:300',
        ], ['slot_key' => 'स्लॉट की key', 'name' => 'नाम', 'size' => 'साइज़', 'max_ads' => 'एक साथ विज्ञापन', 'status' => 'स्थिति', 'description' => 'विवरण']);
        $key = (string) ($v['slot_key'] ?? '');
        if ($key !== '' && (int) db()->value('SELECT COUNT(*) FROM {p}ad_slots WHERE slot_key = ? AND id <> ?', [$key, (int) ($slot['id'] ?? 0)])) {
            throw new ValidationException(['slot_key' => 'यह key पहले से है।'], $request->post());
        }
        return ['slot_key' => $key, 'name' => trim(strip_tags((string) $v['name'])), 'size' => $v['size'] ? strip_tags((string) $v['size']) : null, 'max_ads' => (int) $v['max_ads'],
            'status' => $v['status'], 'description' => $v['description'] ? strip_tags((string) $v['description']) : null];
    }
}
