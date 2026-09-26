<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Tag;
use App\Services\AuditService;
use App\Services\MenuService;
use App\Services\TaxonomyService;

/** टैग: खोज, जल्दी जोड़ना, बदलना, बल्क हटाना, मर्ज */
final class TagController extends Controller
{
    private const LABELS = ['name' => 'टैग का नाम', 'slug' => 'URL (स्लग)', 'description' => 'विवरण'];
    private const SORTS = ['name' => 'name ASC', 'usage' => 'usage_count DESC, name ASC', 'new' => 'id DESC'];

    public function index(Request $request): Response
    {
        $q = $request->str('q');
        $sort = isset(self::SORTS[$request->str('sort')]) ? $request->str('sort') : 'usage';
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = '(name LIKE ? OR slug LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params = [$like, $like];
        }
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}tags WHERE $where", $params);
        $items = db()->all("SELECT * FROM {p}tags WHERE $where ORDER BY " . self::SORTS[$sort] . ' LIMIT 50 OFFSET ' . Paginator::offset($page, 50), $params);
        return $this->view('admin/tags/index', ['tags' => new Paginator($items, $total, 50, $page), 'q' => $q, 'sort' => $sort, 'unused' => Tag::count(['usage_count' => 0])]);
    }

    /** एक साथ कई टैग: "चुनाव, बजट 2026, मौसम" */
    public function store(Request $request): Response
    {
        $names = array_values(array_unique(array_filter(array_map(static fn($n) => trim(mb_substr(strip_tags($n), 0, 100)), preg_split('/[,،\n]+/u', $request->str('names')) ?: []))));
        if (!$names) {
            throw new ValidationException(['names' => 'कम से कम एक टैग का नाम लिखें।'], $request->post());
        }
        if (count($names) > 50) {
            throw new ValidationException(['names' => 'एक बार में 50 टैग तक।'], $request->post());
        }
        $created = $existing = [];
        foreach ($names as $name) {
            $dupe = db()->value('SELECT id FROM {p}tags WHERE name = ?', [$name]);
            if ($dupe) {
                $existing[] = $name;
                continue;
            }
            $id = Tag::create(['name' => $name, 'slug' => TaxonomyService::slug('tags', '', $name, 0, 'tag')]);
            $created[] = $name;
            AuditService::log('create', 'tags', $id, 'नया टैग: ' . $name);
        }
        $msg = $created ? count($created) . ' टैग जुड़े: ' . implode(', ', $created) . '।' : 'कोई नया टैग नहीं जुड़ा।';
        if ($existing) {
            $msg .= ' पहले से मौजूद: ' . implode(', ', $existing) . '।';
        }
        return $this->back()->with($created ? 'success' : 'warning', $msg);
    }

    public function edit(Request $request, int $id): Response
    {
        $tag = Tag::find($id) ?? throw new HttpException(404);
        return $this->view('admin/tags/form', ['tag' => $tag]);
    }

    public function update(Request $request, int $id): Response
    {
        $tag = Tag::find($id) ?? throw new HttpException(404);
        $v = $this->validate($request, ['name' => 'required|max:100', 'slug' => 'nullable|slug|max:120', 'description' => 'nullable|max:500'], self::LABELS);
        if (db()->value('SELECT id FROM {p}tags WHERE name = ? AND id <> ?', [$v['name'], $id])) {
            throw new ValidationException(['name' => 'इस नाम का टैग पहले से है। दोनों को एक करना हो तो सूची में “मर्ज” इस्तेमाल करें।'], $request->post());
        }
        $data = ['name' => $v['name'], 'slug' => TaxonomyService::slug('tags', (string) $v['slug'], $v['name'], $id, 'tag'), 'description' => $v['description']];
        Tag::update($id, $data);
        MenuService::clearCache();
        AuditService::log('update', 'tags', $id, 'टैग बदला: ' . $data['name'], $tag, $data);
        return $this->toRoute('admin.tags.index')->with('success', 'टैग “' . $data['name'] . '” सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $tag = Tag::find($id) ?? throw new HttpException(404);
        $this->detach([$id]);
        Tag::delete($id);
        AuditService::log('delete', 'tags', $id, 'टैग हटाया: ' . $tag['name'], ['name' => $tag['name'], 'usage' => $tag['usage_count']]);
        return $this->back()->with('success', 'टैग “' . $tag['name'] . '” हटा दिया गया।');
    }

    /** बल्क: delete या merge (चुने गए सभी टैग एक टैग में) */
    public function bulk(Request $request): Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        $action = $request->str('action');
        if (!$ids || !in_array($action, ['delete', 'merge'], true)) {
            return $this->back()->with('warning', 'पहले टैग और काम चुनें।');
        }
        $this->authorize('tags.delete');
        $in = Database::in($ids);
        $tags = db()->all("SELECT id, name, usage_count FROM {p}tags WHERE id IN ($in)", $ids);
        if ($action === 'delete') {
            $this->detach($ids);
            $n = db()->query("DELETE FROM {p}tags WHERE id IN ($in)", $ids)->rowCount();
            AuditService::log('bulk_delete', 'tags', implode(',', $ids), "$n टैग हटाए: " . implode(', ', array_column($tags, 'name')));
            return $this->back()->with('success', "$n टैग हटा दिए गए।");
        }

        // मर्ज: target चुने गए में से हो
        $this->authorize('tags.edit');
        $target = $request->int('target');
        if (count($ids) < 2 || !in_array($target, $ids, true)) {
            return $this->back()->with('warning', 'मर्ज के लिए कम से कम 2 टैग चुनें और बताएँ कि किस टैग में मिलाना है।');
        }
        $sources = array_values(array_diff($ids, [$target]));
        db()->transaction(function () use ($sources, $target, $tags) {
            $this->retarget($sources, $target);
            $sum = array_sum(array_map(static fn($t) => (int) $t['usage_count'], $tags));
            db()->query('UPDATE {p}tags SET usage_count = ?, updated_at = NOW() WHERE id = ?', [$sum, $target]);
            db()->query('DELETE FROM {p}tags WHERE id IN (' . Database::in($sources) . ')', $sources);
        });
        $targetName = (string) db()->value('SELECT name FROM {p}tags WHERE id = ?', [$target]);
        $names = array_column(array_filter($tags, static fn($t) => (int) $t['id'] !== $target), 'name');
        AuditService::log('merge', 'tags', $target, 'टैग मर्ज: ' . implode(', ', $names) . ' → ' . $targetName);
        return $this->back()->with('success', count($sources) . ' टैग “' . $targetName . '” में मिला दिए गए।');
    }

    /** Phase 4 के ख़बर-टैग रिश्ते (टेबल हो तो) */
    private function detach(array $ids): void
    {
        if (in_array('news_tags', MenuService::existingTables(), true)) {
            db()->query('DELETE FROM {p}news_tags WHERE tag_id IN (' . Database::in($ids) . ')', $ids);
        }
    }

    private function retarget(array $sources, int $target): void
    {
        if (!in_array('news_tags', MenuService::existingTables(), true)) {
            return;
        }
        $in = Database::in($sources);
        // जिस ख़बर में target पहले से है, वहाँ दोहराव न बने
        db()->query("DELETE s FROM {p}news_tags s JOIN {p}news_tags t ON t.news_id = s.news_id AND t.tag_id = ? WHERE s.tag_id IN ($in)", [$target, ...$sources]);
        db()->query("UPDATE {p}news_tags SET tag_id = ? WHERE tag_id IN ($in)", [$target, ...$sources]);
    }

    /** ख़बर फ़ॉर्म (Phase 4) के लिए सुझाव: JSON */
    public function search(Request $request): Response
    {
        $q = $request->str('q');
        $rows = $q === '' ? [] : db()->all('SELECT id, name, slug, usage_count FROM {p}tags WHERE name LIKE ? OR slug LIKE ? ORDER BY usage_count DESC, name LIMIT 15', ['%' . addcslashes($q, '%_\\') . '%', '%' . addcslashes($q, '%_\\') . '%']);
        return $this->json(['items' => $rows]);
    }
}
