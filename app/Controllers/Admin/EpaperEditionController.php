<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\EpaperEdition;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\EpaperService;
use App\Services\TaxonomyService;

/** ई-पेपर संस्करण: मुख्य / राज्य / ज़िला / शहर */
final class EpaperEditionController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/epaper/editions', ['items' => $this->all(), 'edit' => null, 'location' => null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}epaper_editions');
        $id = EpaperEdition::create($data);
        $this->afterSave($id, $data);
        AuditService::log('create', 'epaper', $id, 'संस्करण: ' . $data['name']);
        return $this->toRoute('admin.epaper.editions')->with('success', '“' . $data['name'] . '” संस्करण बन गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        $edit = EpaperEdition::find($id) ?? throw new HttpException(404);
        return $this->view('admin/epaper/editions', ['items' => [], 'edit' => $edit, 'location' => $edit['location_id'] ? Location::find((int) $edit['location_id']) : null]);
    }

    public function update(Request $request, int $id): Response
    {
        $old = EpaperEdition::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $old);
        EpaperEdition::update($id, $data);
        $this->afterSave($id, $data);
        AuditService::log('update', 'epaper', $id, 'संस्करण बदला: ' . $data['name'], $old, $data);
        return $this->toRoute('admin.epaper.editions')->with('success', 'बदलाव सेव हो गए।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $e = EpaperEdition::find($id) ?? throw new HttpException(404);
        if ($n = (int) db()->value('SELECT COUNT(*) FROM {p}epaper_issues WHERE edition_id = ?', [$id])) {
            return $this->back()->with('danger', "इस संस्करण के $n अंक हैं। हटाने की जगह इसे बंद करें।");
        }
        EpaperEdition::delete($id);
        $this->afterSave(0, ['is_default' => 0]);
        AuditService::log('delete', 'epaper', $id, 'संस्करण हटाया: ' . $e['name']);
        return $this->toRoute('admin.epaper.editions')->with('success', '“' . $e['name'] . '” हट गया।');
    }

    private function all(): array
    {
        return db()->all("SELECT e.*, l.name AS location, (SELECT COUNT(*) FROM {p}epaper_issues i WHERE i.edition_id = e.id) AS issues
                          FROM {p}epaper_editions e LEFT JOIN {p}locations l ON l.id = e.location_id ORDER BY e.is_default DESC, e.sort_order, e.name");
    }

    /** एक ही डिफ़ॉल्ट; कोई न हो तो पहला चालू */
    private function afterSave(int $id, array $data): void
    {
        if ($id && $data['is_default']) {
            db()->query('UPDATE {p}epaper_editions SET is_default = 0 WHERE id <> ?', [$id]);
        }
        if (!(int) db()->value("SELECT COUNT(*) FROM {p}epaper_editions WHERE is_default = 1 AND status = 'active'")) {
            db()->query("UPDATE {p}epaper_editions SET is_default = 1 WHERE status = 'active' ORDER BY sort_order, id LIMIT 1");
        }
        EpaperService::changed();
    }

    private function payload(Request $request, ?array $e): array
    {
        $v = $this->validate($request, [
            'name' => 'required|max:150', 'slug' => 'nullable|slug|max:170', 'type' => 'required|in:' . implode(',', array_keys(EpaperEdition::TYPES)),
            'location_id' => 'nullable|integer|exists:locations,id', 'description' => 'nullable|max:500', 'status' => 'required|in:active,inactive', 'sort_order' => 'nullable|integer|min:0|max:100000',
        ], ['name' => 'नाम', 'slug' => 'URL (स्लग)', 'type' => 'प्रकार', 'location_id' => 'लोकेशन', 'description' => 'विवरण', 'status' => 'स्थिति', 'sort_order' => 'क्रम']);
        $name = trim(strip_tags((string) $v['name']));
        $data = ['name' => $name, 'slug' => TaxonomyService::slug('epaper_editions', (string) $v['slug'], $name, (int) ($e['id'] ?? 0), 'edition'), 'type' => $v['type'],
            'location_id' => $v['location_id'] ? (int) $v['location_id'] : null, 'description' => $v['description'] ? strip_tags((string) $v['description']) : null,
            'is_default' => $request->bool('is_default') ? 1 : 0, 'status' => $v['status']];
        if ($v['sort_order'] !== null) {
            $data['sort_order'] = (int) $v['sort_order'];
        }
        // "archive" तारीख़ वाले रास्ते से न टकराए
        if (in_array($data['slug'], ['archive', 'go'], true)) {
            $data['slug'] .= '-edition';
        }
        return $data;
    }
}
