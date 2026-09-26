<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Menu;
use App\Services\AuditService;
use App\Services\MenuService;

/** मेनू बिल्डर: हर जगह (location) का एक मेनू, नेस्टेड आइटम */
final class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $menus = db()->all('SELECT m.*, (SELECT COUNT(*) FROM {p}menu_items i WHERE i.menu_id = m.id) AS item_count FROM {p}menus m');
        $byLoc = array_column($menus, null, 'location');
        // कोई जगह का मेनू न हो तो बना दें (नई जगह config में जुड़ने पर)
        foreach ((array) config('menus.locations') as $loc => $label) {
            if (!isset($byLoc[$loc])) {
                $id = Menu::create(['name' => $label, 'location' => $loc]);
                $byLoc[$loc] = ['id' => $id, 'name' => $label, 'location' => $loc, 'item_count' => 0, 'updated_at' => now()];
            }
        }
        return $this->view('admin/menus/index', ['menus' => $byLoc, 'locations' => config('menus.locations')]);
    }

    public function edit(Request $request, int $id): Response
    {
        $menu = Menu::find($id) ?? throw new HttpException(404);
        $items = db()->all('SELECT * FROM {p}menu_items WHERE menu_id = ? ORDER BY sort_order, id', [$id]);
        $refs = MenuService::referenceRows($items);
        foreach ($items as &$it) {
            $it['ref_title'] = $refs[$it['type']][(int) $it['reference_id']]['title'] ?? null;
            $it['href'] = MenuService::url($it, $refs);
        }
        unset($it);
        $types = MenuService::availableTypes();
        $options = [];
        foreach ($types as $key => $t) {
            if (!empty($t['table'])) {
                $extra = $t['table'] === 'pages' ? ' WHERE deleted_at IS NULL' : '';
                $options[$key] = db()->all("SELECT id, `{$t['title_col']}` AS title FROM {p}{$t['table']}$extra ORDER BY `{$t['title_col']}` LIMIT 500");
            }
        }
        return $this->view('admin/menus/edit', [
            'menu' => $menu,
            'tree' => $this->toFlat(MenuService::nest($items)),
            'types' => $types,
            'options' => $options,
            'allTypes' => config('menus.types'),
            'maxDepth' => (int) config('menus.max_depth', 3),
        ]);
    }

    /** नेस्टेड → [item + depth] (बिल्डर के लिए) */
    private function toFlat(array $tree, int $depth = 0): array
    {
        $out = [];
        foreach ($tree as $node) {
            $children = $node['children'];
            unset($node['children']);
            $node['depth'] = $depth;
            $out[] = $node;
            array_push($out, ...$this->toFlat($children, $depth + 1));
        }
        return $out;
    }

    /** पूरा ढाँचा एक साथ सेव: items = [{title,type,reference_id,url,target_blank,is_active,depth}] (क्रम में) */
    public function update(Request $request, int $id): Response
    {
        $menu = Menu::find($id) ?? throw new HttpException(404);
        $name = trim(mb_substr($request->str('name'), 0, 100));
        $items = json_decode((string) $request->input('items', '[]'), true);
        if (!is_array($items) || count($items) > 300) {
            return $this->json(['ok' => false, 'message' => 'मेनू का डेटा सही नहीं है।'], 422);
        }
        $types = MenuService::availableTypes();
        $maxDepth = (int) config('menus.max_depth', 3);
        $errors = [];
        $clean = [];
        $prevDepth = -1;
        foreach ($items as $i => $it) {
            $n = $i + 1;
            $type = (string) ($it['type'] ?? '');
            $title = trim(mb_substr(strip_tags((string) ($it['title'] ?? '')), 0, 120));
            $depth = max(0, min($maxDepth - 1, (int) ($it['depth'] ?? 0), $prevDepth + 1));
            if (!isset($types[$type])) {
                $errors[] = "आइटम $n: प्रकार मान्य नहीं है।";
                continue;
            }
            if ($title === '') {
                $errors[] = "आइटम $n: नाम ज़रूरी है।";
            }
            $ref = null;
            $url = null;
            if (!empty($types[$type]['table'])) {
                $ref = (int) ($it['reference_id'] ?? 0);
                if (!$ref || !db()->value("SELECT id FROM {p}{$types[$type]['table']} WHERE id = ?", [$ref])) {
                    $errors[] = "आइटम $n ($title): चुना गया {$types[$type]['label']} मौजूद नहीं है।";
                }
            } elseif (in_array($type, ['custom', 'external'], true)) {
                $url = trim((string) ($it['url'] ?? ''));
                if (!MenuService::safeUrl($url, $type === 'external')) {
                    $errors[] = "आइटम $n ($title): पता सही नहीं है। " . ($type === 'external' ? 'https:// से शुरू करें।' : 'जैसे /page/about-us');
                }
            }
            $clean[] = ['title' => $title, 'type' => $type, 'reference_id' => $ref, 'url' => $url, 'target_blank' => !empty($it['target_blank']) ? 1 : 0, 'is_active' => !isset($it['is_active']) || !empty($it['is_active']) ? 1 : 0, 'depth' => $depth];
            $prevDepth = $depth;
        }
        if ($name === '') {
            $errors[] = 'मेनू का नाम ज़रूरी है।';
        }
        if ($errors) {
            return $this->json(['ok' => false, 'message' => 'कुछ आइटम ठीक करने हैं।', 'errors' => $errors], 422);
        }

        $before = (int) db()->value('SELECT COUNT(*) FROM {p}menu_items WHERE menu_id = ?', [$id]);
        db()->transaction(function ($db) use ($id, $name, $clean) {
            $db->update('menus', ['name' => $name, 'updated_at' => now()], 'id = ?', [$id]);
            $db->delete('menu_items', 'menu_id = ?', [$id]);
            $parents = [];
            foreach ($clean as $i => $c) {
                $depth = $c['depth'];
                unset($c['depth']);
                $c['menu_id'] = $id;
                $c['parent_id'] = $depth > 0 ? ($parents[$depth - 1] ?? null) : null;
                $c['sort_order'] = $i + 1;
                $parents[$depth] = $db->insert('menu_items', $c);
            }
        });
        MenuService::clearCache();
        AuditService::log('update', 'menus', $id, "मेनू बदला: $name (" . count($clean) . ' आइटम)', ['items' => $before], ['items' => count($clean)]);
        return $this->json(['ok' => true, 'message' => 'मेनू सेव हो गया।']);
    }
}
