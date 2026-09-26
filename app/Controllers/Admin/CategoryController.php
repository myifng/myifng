<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\MenuService;
use App\Services\TaxonomyService;

/** श्रेणियाँ: मुख्य → उप-श्रेणी, क्रम, SEO, मेनू/होमपेज पर दिखना */
final class CategoryController extends Controller
{
    public const LABELS = [
        'name' => 'नाम', 'slug' => 'URL (स्लग)', 'parent_id' => 'मुख्य श्रेणी', 'description' => 'विवरण', 'icon' => 'आइकन', 'color' => 'रंग',
        'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'status' => 'स्थिति', 'sort_order' => 'क्रम',
    ];

    public function index(Request $request): Response
    {
        $tree = Category::tree();
        $q = $request->str('q');
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $match = static fn($c) => str_contains(mb_strtolower($c['name']), $needle) || str_contains($c['slug'], $needle);
            $tree = array_values(array_filter(array_map(static function ($c) use ($match) {
                $c['children'] = array_values(array_filter($c['children'], $match));
                return ($match($c) || $c['children']) ? $c : null;
            }, $tree)));
        }
        return $this->view('admin/categories/index', ['tree' => $tree, 'q' => $q, 'total' => Category::count()]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/categories/form', ['cat' => null, 'parents' => $this->parents(0), 'parentId' => $request->int('parent') ?: null]);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}categories WHERE ' . ($data['parent_id'] ? 'parent_id = ?' : 'parent_id IS NULL'), $data['parent_id'] ? [$data['parent_id']] : []);
        $data['language_id'] = db()->value("SELECT id FROM {p}languages WHERE code = ?", [setting('language', 'hi')]) ?: null;
        $id = Category::create($data);
        MenuService::clearCache();
        AuditService::log('create', 'categories', $id, 'नई श्रेणी: ' . $data['name'], null, $data);
        $next = $request->bool('add_another') ? route('admin.categories.create') . ($data['parent_id'] ? '?parent=' . $data['parent_id'] : '') : route('admin.categories.index');
        return $this->redirect($next)->with('success', 'श्रेणी “' . $data['name'] . '” बन गई। URL: /category/' . $data['slug']);
    }

    public function edit(Request $request, int $id): Response
    {
        $cat = Category::find($id) ?? throw new HttpException(404);
        return $this->view('admin/categories/form', [
            'cat' => $cat, 'parents' => $this->parents($id), 'parentId' => $cat['parent_id'],
            'childCount' => Category::count(['parent_id' => $id]), 'usage' => TaxonomyService::usage('categories', $id),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $cat = Category::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $cat);
        Category::update($id, $data);
        MenuService::clearCache();
        AuditService::log('update', 'categories', $id, 'श्रेणी बदली: ' . $data['name'], $cat, $data);
        $note = $cat['slug'] !== $data['slug'] ? ' URL बदला है: पुराने लिंक के लिए Phase 10 में रीडायरेक्ट मैनेजर।' : '';
        return $this->toRoute('admin.categories.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।' . $note);
    }

    /** मुख्य श्रेणियाँ जो parent बन सकती हैं (ख़ुद को छोड़कर) */
    private function parents(int $exceptId): array
    {
        return db()->all('SELECT id, name FROM {p}categories WHERE parent_id IS NULL AND id <> ? ORDER BY sort_order, name', [$exceptId]);
    }

    private function payload(Request $request, ?array $cat): array
    {
        $id = (int) ($cat['id'] ?? 0);
        $v = $this->validate($request, [
            'name' => 'required|max:120',
            'slug' => 'nullable|slug|max:140',
            'parent_id' => 'nullable|integer',
            'description' => 'nullable|max:500',
            'icon' => 'nullable|max:60',
            'color' => 'nullable|color',
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
        $parent = $v['parent_id'] ? (int) $v['parent_id'] : null;
        if ($parent !== null) {
            $p = Category::find($parent);
            if (!$p || $p['parent_id'] !== null || $parent === $id) {
                $errors['parent_id'] = 'सिर्फ़ मुख्य श्रेणी के नीचे उप-श्रेणी बन सकती है (दो स्तर तक)।';
            } elseif ($id && Category::count(['parent_id' => $id]) > 0) {
                $errors['parent_id'] = 'इस श्रेणी की अपनी उप-श्रेणियाँ हैं, इसलिए यह किसी और के नीचे नहीं जा सकती।';
            }
        }
        if (!TaxonomyService::validIcon($v['icon'])) {
            $errors['icon'] = 'आइकन Font Awesome की class हो, जैसे fa-futbol या fa-solid fa-futbol।';
        }
        $image = $request->str('image');
        if ($image !== ($cat['image'] ?? '') && !MediaService::validImagePath($image)) {
            $errors['image'] = 'इमेज मीडिया लाइब्रेरी से चुनें।';
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        return [
            'name' => $v['name'], 'slug' => TaxonomyService::slug('categories', (string) $v['slug'], $v['name'], $id, 'category'),
            'parent_id' => $parent, 'description' => $v['description'], 'icon' => $v['icon'] ?: null, 'color' => $v['color'] ?: null,
            'image' => $image ?: null, 'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'status' => $v['status'],
            'show_in_menu' => $request->bool('show_in_menu') ? 1 : 0, 'show_on_home' => $request->bool('show_on_home') ? 1 : 0,
        ];
    }

    /** ऊपर/नीचे (अपने भाई-बहनों में) */
    public function move(Request $request, int $id): Response
    {
        $cat = Category::find($id) ?? throw new HttpException(404);
        $dir = $request->str('dir') === 'up' ? 'up' : 'down';
        $moved = TaxonomyService::move('categories', $id, $dir, $cat['parent_id'] === null ? null : (int) $cat['parent_id']);
        if ($moved) {
            MenuService::clearCache();
            AuditService::log('reorder', 'categories', $id, 'श्रेणी का क्रम बदला: ' . $cat['name']);
        }
        return $request->wantsJson() ? $this->json(['ok' => $moved]) : $this->back();
    }

    public function destroy(Request $request, int $id): Response
    {
        $cat = Category::find($id) ?? throw new HttpException(404);
        if (($n = Category::count(['parent_id' => $id])) > 0) {
            return $this->back()->with('danger', "“{$cat['name']}” की $n उप-श्रेणियाँ हैं। पहले उन्हें हटाएँ या दूसरी श्रेणी में ले जाएँ।");
        }
        if (($n = TaxonomyService::usage('categories', $id)) > 0) {
            return $this->back()->with('danger', "“{$cat['name']}” में $n ख़बरें हैं, इसलिए हटाई नहीं जा सकती। चाहें तो इसे बंद (inactive) कर दें।");
        }
        Category::delete($id);
        TaxonomyService::removeMenuLinks('category', $id);
        AuditService::log('delete', 'categories', $id, 'श्रेणी हटाई: ' . $cat['name'], ['name' => $cat['name'], 'slug' => $cat['slug']]);
        return $this->toRoute('admin.categories.index')->with('success', 'श्रेणी “' . $cat['name'] . '” हटा दी गई।');
    }
}
