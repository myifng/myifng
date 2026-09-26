<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Location;
use App\Services\AuditService;
use App\Services\LocationService;
use App\Services\MenuService;
use App\Services\TaxonomyService;

/** लोकेशन: परत-दर-परत ब्राउज़ (देश → राज्य → ज़िला …), जोड़ना, बदलना, CSV इम्पोर्ट/एक्सपोर्ट, खोज (JSON) */
final class LocationController extends Controller
{
    private const LABELS = [
        'type' => 'स्तर', 'name' => 'नाम (हिंदी)', 'name_en' => 'नाम (अंग्रेज़ी)', 'slug' => 'URL (स्लग)', 'parent_id' => 'ऊपर वाली लोकेशन',
        'code' => 'कोड', 'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'status' => 'स्थिति',
    ];

    public function index(Request $request): Response
    {
        $q = $request->str('q');
        $parent = $request->int('parent') ?: null;
        $current = $parent ? (Location::find($parent) ?? throw new HttpException(404)) : null;
        $page = max(1, $request->int('page', 1));
        $type = $request->str('type');

        if ($q !== '') {
            // पूरे पेड़ में खोज
            $where = '(l.name LIKE ? OR l.name_en LIKE ? OR l.path LIKE ? OR l.code = ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params = [$like, $like, $like, $q];
        } else {
            $where = $parent ? 'l.parent_id = ?' : 'l.parent_id IS NULL';
            $params = $parent ? [$parent] : [];
        }
        if (isset(Location::TYPES[$type])) {
            $where .= ' AND l.type = ?';
            $params[] = $type;
        }
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}locations l WHERE $where", $params);
        $items = db()->all(
            "SELECT l.*, p.name AS parent_name, (SELECT COUNT(*) FROM {p}locations c WHERE c.parent_id = l.id) AS children
             FROM {p}locations l LEFT JOIN {p}locations p ON p.id = l.parent_id
             WHERE $where ORDER BY l.sort_order, l.name LIMIT 50 OFFSET " . Paginator::offset($page, 50),
            $params
        );
        $stats = db()->all('SELECT type, COUNT(*) n FROM {p}locations GROUP BY type');
        return $this->view('admin/locations/index', [
            'items' => new Paginator($items, $total, 50, $page), 'current' => $current, 'ancestors' => $current ? Location::ancestors($current) : [],
            'q' => $q, 'type' => $type, 'stats' => array_column($stats, 'n', 'type'),
            'childTypes' => Location::childTypes($current['type'] ?? null),
        ]);
    }

    public function create(Request $request): Response
    {
        $parent = $request->int('parent') ? Location::find($request->int('parent')) : null;
        if ($request->int('parent') && !$parent) {
            throw new HttpException(404);
        }
        $types = Location::childTypes($parent['type'] ?? null);
        if (!$types) {
            return $this->back()->with('warning', 'मोहल्ला/गाँव सबसे नीचे का स्तर है; इसके नीचे कुछ नहीं जुड़ता।');
        }
        return $this->view('admin/locations/form', ['loc' => null, 'parent' => $parent, 'ancestors' => $parent ? [...Location::ancestors($parent), $parent] : [], 'types' => $types]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}locations WHERE ' . ($data['parent_id'] ? 'parent_id = ?' : 'parent_id IS NULL'), $data['parent_id'] ? [$data['parent_id']] : []);
        $id = Location::create($data);
        MenuService::clearCache();
        cache()->flush('locations');
        AuditService::log('create', 'locations', $id, 'नई लोकेशन: ' . $data['name'] . ' (' . Location::SHORT[$data['type']] . ')', null, $data);
        $msg = '“' . $data['name'] . '” जुड़ गया।' . ($data['path'] ? ' URL: /' . $data['path'] . '/' : '');
        if ($request->bool('add_another')) {
            return $this->redirect(route('admin.locations.create') . ($data['parent_id'] ? '?parent=' . $data['parent_id'] : ''))->with('success', $msg);
        }
        return $this->redirect(route('admin.locations.index') . ($data['parent_id'] ? '?parent=' . $data['parent_id'] : ''))->with('success', $msg);
    }

    public function edit(Request $request, int $id): Response
    {
        $loc = Location::find($id) ?? throw new HttpException(404);
        $parent = $loc['parent_id'] ? Location::find((int) $loc['parent_id']) : null;
        return $this->view('admin/locations/form', [
            'loc' => $loc, 'parent' => $parent, 'ancestors' => Location::ancestors($loc), 'types' => Location::childTypes($parent['type'] ?? null),
            'children' => Location::count(['parent_id' => $id]), 'usage' => TaxonomyService::usage('locations', $id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $loc = Location::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $loc);
        $changed = 0;
        try {
            db()->transaction(function () use ($id, $data, $loc, &$changed) {
                Location::update($id, $data);
                if ($data['path'] !== $loc['path'] || $data['parent_id'] !== ($loc['parent_id'] === null ? null : (int) $loc['parent_id'])) {
                    $changed = LocationService::rebuildChildren($id);
                }
            });
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            throw new ValidationException(['slug' => 'इस बदलाव से नीचे की किसी लोकेशन का पता (URL) किसी और से टकरा रहा है। दूसरा स्लग चुनें।'], $request->post());
        }
        MenuService::clearCache();
        cache()->flush('locations');
        AuditService::log('update', 'locations', $id, 'लोकेशन बदली: ' . $data['name'], $loc, $data);
        $note = $loc['path'] !== $data['path'] ? ' URL बदला' . ($changed ? " (नीचे की $changed लोकेशन के भी)" : '') . '; पुराने लिंक के लिए Phase 10 में रीडायरेक्ट मैनेजर।' : '';
        return $this->toRoute('admin.locations.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।' . $note);
    }

    private function payload(Request $request, ?array $loc): array
    {
        $id = (int) ($loc['id'] ?? 0);
        $v = $this->validate($request, [
            'type' => 'required|in:' . implode(',', array_keys(Location::TYPES)),
            'name' => 'required|max:120',
            'name_en' => 'nullable|max:120|regex:/^[A-Za-z0-9 .()\'&-]+$/',
            'slug' => 'nullable|slug|max:100',
            'parent_id' => 'nullable|integer',
            'code' => 'nullable|max:20|regex:/^[A-Za-z0-9-]+$/',
            'meta_title' => 'nullable|max:190',
            'meta_description' => 'nullable|max:320',
            'status' => 'required|in:active,inactive',
        ], self::LABELS);
        $errors = [];
        // नाम में HTML नहीं (दिखाते समय escape तो होता ही है, पर स्लग और SEO साफ़ रहें)
        $v['name'] = trim(preg_replace('/\s+/u', ' ', strip_tags($v['name'])));
        if ($v['name'] === '') {
            throw new ValidationException(['name' => 'नाम में सिर्फ़ सादा टेक्स्ट लिखें।'], $request->post());
        }
        $parentId = $v['parent_id'] ? (int) $v['parent_id'] : null;
        $parent = $parentId ? Location::find($parentId) : null;
        if ($parentId && !$parent) {
            $errors['parent_id'] = 'ऊपर वाली लोकेशन नहीं मिली।';
        } elseif ($id && LocationService::wouldCycle($id, $parentId)) {
            $errors['parent_id'] = 'लोकेशन को ख़ुद या अपने ही नीचे वाली लोकेशन के नीचे नहीं रखा जा सकता।';
        } elseif (!in_array($v['type'], Location::childTypes($parent['type'] ?? null), true)) {
            $errors['type'] = $parent ? Location::SHORT[$parent['type']] . ' के नीचे “' . Location::SHORT[$v['type']] . '” नहीं आ सकता।' : 'सबसे ऊपर सिर्फ़ देश हो सकता है।';
        } elseif ($id && ($bad = db()->all('SELECT type FROM {p}locations WHERE parent_id = ?', [$id]))) {
            $allowed = Location::childTypes($v['type']);
            foreach ($bad as $c) {
                if (!in_array($c['type'], $allowed, true)) {
                    $errors['type'] = 'इसके नीचे ' . Location::SHORT[$c['type']] . ' हैं, इसलिए स्तर “' . Location::SHORT[$v['type']] . '” नहीं हो सकता।';
                    break;
                }
            }
        }
        $slug = LocationService::slugFrom((string) $v['slug'], $v['name_en'], $v['name']);
        $path = LocationService::pathFor($parentId, $v['type'], $slug);
        if (!$errors && LocationService::reservedPath($path)) {
            $errors['slug'] = "“{$slug}” वेबसाइट का आरक्षित शब्द है (जैसे /{$slug}/ पहले से किसी पेज का रास्ता है)। दूसरा स्लग दें।";
        }
        if (!$errors && $path !== null && db()->value('SELECT id FROM {p}locations WHERE path = ? AND id <> ?', [$path, $id])) {
            $errors['slug'] = "यह पता (/{$path}/) पहले से किसी दूसरी लोकेशन का है। अलग स्लग दें, जैसे {$slug}-city।";
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        return [
            'parent_id' => $parentId, 'type' => $v['type'], 'name' => $v['name'], 'name_en' => $v['name_en'], 'slug' => $slug, 'path' => $path,
            'code' => $v['code'] ? strtoupper($v['code']) : null, 'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'status' => $v['status'],
            'is_popular' => $request->bool('is_popular') ? 1 : 0, 'show_in_menu' => $request->bool('show_in_menu') ? 1 : 0,
        ];
    }

    public function destroy(Request $request, int $id): Response
    {
        $loc = Location::find($id) ?? throw new HttpException(404);
        if (($n = Location::count(['parent_id' => $id])) > 0) {
            return $this->back()->with('danger', "“{$loc['name']}” के नीचे $n लोकेशन हैं। पहले उन्हें हटाएँ।");
        }
        if (($n = TaxonomyService::usage('locations', $id)) > 0) {
            return $this->back()->with('danger', "“{$loc['name']}” $n ख़बरों से जुड़ी है। हटाने की जगह इसे बंद (inactive) करें।");
        }
        Location::delete($id);
        TaxonomyService::removeMenuLinks('location', $id);
        cache()->flush('locations');
        AuditService::log('delete', 'locations', $id, 'लोकेशन हटाई: ' . $loc['name'], ['name' => $loc['name'], 'path' => $loc['path'], 'type' => $loc['type']]);
        return $this->redirect(route('admin.locations.index') . ($loc['parent_id'] ? '?parent=' . $loc['parent_id'] : ''))->with('success', '“' . $loc['name'] . '” हटा दी गई।');
    }

    public function move(Request $request, int $id): Response
    {
        $loc = Location::find($id) ?? throw new HttpException(404);
        $ok = TaxonomyService::move('locations', $id, $request->str('dir') === 'up' ? 'up' : 'down', $loc['parent_id'] === null ? null : (int) $loc['parent_id']);
        return $request->wantsJson() ? $this->json(['ok' => $ok]) : $this->back();
    }

    /** लोकप्रिय चालू/बंद (सूची से एक क्लिक) */
    public function popular(Request $request, int $id): Response
    {
        $loc = Location::find($id) ?? throw new HttpException(404);
        Location::update($id, ['is_popular' => $loc['is_popular'] ? 0 : 1]);
        cache()->flush('locations');
        AuditService::log('update', 'locations', $id, ($loc['is_popular'] ? 'लोकप्रिय से हटाई: ' : 'लोकप्रिय बनाई: ') . $loc['name']);
        return $request->wantsJson() ? $this->json(['ok' => true, 'popular' => !$loc['is_popular']]) : $this->back();
    }

    /** खोज (JSON): ख़बर फ़ॉर्म, "ऊपर वाली लोकेशन" आदि में */
    public function search(Request $request): Response
    {
        $types = array_values(array_intersect(explode(',', $request->str('types')), array_keys(Location::TYPES)));
        return $this->json(['items' => LocationService::search($request->str('q'), $types, false, 20)]);
    }

    public function importForm(Request $request): Response
    {
        return $this->view('admin/locations/import', []);
    }

    public function import(Request $request): Response
    {
        $file = $request->file('csv');
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new ValidationException(['csv' => 'CSV फ़ाइल चुनें।'], []);
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true) || strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') {
            throw new ValidationException(['csv' => 'सिर्फ़ .csv फ़ाइल (Excel में “Save As → CSV UTF-8”)।'], []);
        }
        $r = LocationService::import((string) file_get_contents((string) $file['tmp_name']));
        if (!$r['ok']) {
            return $this->toRoute('admin.locations.import')->with('danger', 'कुछ नहीं जुड़ा। ये पंक्तियाँ ठीक करके दोबारा अपलोड करें: ' . implode(' | ', $r['errors']));
        }
        MenuService::clearCache();
        cache()->flush('locations');
        AuditService::log('import', 'locations', null, "CSV से {$r['created']} लोकेशन जुड़ीं, {$r['skipped']} पहले से थीं");
        return $this->toRoute('admin.locations.index')->with('success', "{$r['created']} लोकेशन जुड़ गईं।" . ($r['skipped'] ? " {$r['skipped']} पहले से मौजूद थीं, छोड़ दी गईं।" : ''));
    }

    /** नमूना या पूरी सूची CSV में (Excel में खुलती है) */
    public function export(Request $request): Response
    {
        $sample = $request->bool('sample');
        $rows = $sample
            ? [['district', 'कुशीनगर', 'Kushinagar', 'kushinagar', 'uttar-pradesh', '', '0'], ['city', 'पडरौना', 'Padrauna', '', 'uttar-pradesh/kushinagar', '', '0'], ['tehsil', 'हाटा', 'Hata', '', 'uttar-pradesh/kushinagar', '', '0']]
            : (function () {
                foreach (db()->query('SELECT l.type, l.name, l.name_en, l.slug, COALESCE(p.path, p.id) AS parent, l.code, l.is_popular FROM {p}locations l LEFT JOIN {p}locations p ON p.id = l.parent_id WHERE l.type <> \'country\' ORDER BY l.id') as $r) {
                    yield array_values($r);
                }
            })();
        AuditService::log('export', 'locations', null, $sample ? 'लोकेशन का नमूना CSV' : 'सभी लोकेशन CSV में');
        return \App\Core\Response::csv($sample ? 'locations-sample.csv' : 'locations-' . date('Y-m-d') . '.csv', ['type', 'name', 'name_en', 'slug', 'parent', 'code', 'is_popular'], $rows);
    }
}
