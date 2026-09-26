<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Topic;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\MenuService;
use App\Services\TaxonomyService;

/** टॉपिक (लोकसभा चुनाव, बजट…) और विशेष सेक्शन (बैनर, रंग, अलग पेज) */
final class TopicController extends Controller
{
    public const LABELS = [
        'type' => 'प्रकार', 'name' => 'नाम', 'slug' => 'URL (स्लग)', 'description' => 'विवरण', 'color' => 'रंग',
        'meta_title' => 'SEO शीर्षक', 'meta_description' => 'SEO विवरण', 'status' => 'स्थिति', 'sort_order' => 'क्रम',
    ];

    public function index(Request $request): Response
    {
        $type = $request->str('type');
        $q = $request->str('q');
        $where = ['1=1'];
        $params = [];
        if (isset(Topic::TYPES[$type])) {
            $where[] = 'type = ?';
            $params[] = $type;
        } elseif ($type === 'featured') {
            $where[] = 'is_featured = 1';
        }
        if ($q !== '') {
            $where[] = '(name LIKE ? OR slug LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        $w = implode(' AND ', $where);
        $page = max(1, $request->int('page', 1));
        $total = (int) db()->value("SELECT COUNT(*) FROM {p}topics WHERE $w", $params);
        $items = db()->all("SELECT * FROM {p}topics WHERE $w ORDER BY is_featured DESC, sort_order, name LIMIT 30 OFFSET " . Paginator::offset($page, 30), $params);
        $c = db()->first("SELECT COUNT(*) alls, SUM(type = 'topic') topic, SUM(type = 'special') special, SUM(is_featured = 1) featured FROM {p}topics");
        return $this->view('admin/topics/index', [
            'topics' => new Paginator($items, $total, 30, $page), 'type' => $type, 'q' => $q,
            'counts' => ['' => (int) $c['alls'], 'topic' => (int) $c['topic'], 'special' => (int) $c['special'], 'featured' => (int) $c['featured']],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/topics/form', ['topic' => null, 'type' => $request->str('type') === 'special' ? 'special' : 'topic']);
    }

    public function store(Request $request): Response
    {
        $data = $this->payload($request, null);
        $data['sort_order'] = (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM {p}topics');
        $id = Topic::create($data);
        MenuService::clearCache();
        AuditService::log('create', 'topics', $id, 'नया ' . Topic::TYPES[$data['type']] . ': ' . $data['name'], null, $data);
        return $this->toRoute('admin.topics.edit', ['id' => $id])->with('success', '“' . $data['name'] . '” बन गया। URL: /topic/' . $data['slug']);
    }

    public function edit(Request $request, int $id): Response
    {
        $topic = Topic::find($id) ?? throw new HttpException(404);
        return $this->view('admin/topics/form', ['topic' => $topic, 'type' => $topic['type'], 'usage' => TaxonomyService::usage('topics', $id)]);
    }

    public function update(Request $request, int $id): Response
    {
        $topic = Topic::find($id) ?? throw new HttpException(404);
        $data = $this->payload($request, $topic);
        Topic::update($id, $data);
        MenuService::clearCache();
        AuditService::log('update', 'topics', $id, 'टॉपिक बदला: ' . $data['name'], $topic, $data);
        return $this->toRoute('admin.topics.edit', ['id' => $id])->with('success', 'बदलाव सेव हो गए।');
    }

    private function payload(Request $request, ?array $topic): array
    {
        $id = (int) ($topic['id'] ?? 0);
        $v = $this->validate($request, [
            'type' => 'required|in:topic,special',
            'name' => 'required|max:150',
            'slug' => 'nullable|slug|max:170',
            'description' => 'nullable|max:1000',
            'color' => 'nullable|color',
            'sort_order' => 'nullable|integer|min:0|max:100000',
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
        foreach (['image', 'banner'] as $f) {
            $val = $request->str($f);
            if ($val !== ($topic[$f] ?? '') && !MediaService::validImagePath($val)) {
                $errors[$f] = 'इमेज मीडिया लाइब्रेरी से चुनें।';
            }
        }
        if ($errors) {
            throw new ValidationException($errors, $request->post());
        }
        $data = [
            'type' => $v['type'], 'name' => $v['name'], 'slug' => TaxonomyService::slug('topics', (string) $v['slug'], $v['name'], $id, 'topic'),
            'description' => $v['description'], 'color' => $v['color'] ?: null, 'image' => $request->str('image') ?: null, 'banner' => $request->str('banner') ?: null,
            'is_featured' => $request->bool('is_featured') ? 1 : 0, 'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'status' => $v['status'],
        ];
        if ($v['sort_order'] !== null) {
            $data['sort_order'] = (int) $v['sort_order'];
        }
        return $data;
    }

    /** ट्रेंडिंग/फ़ीचर्ड चालू-बंद */
    public function feature(Request $request, int $id): Response
    {
        $topic = Topic::find($id) ?? throw new HttpException(404);
        Topic::update($id, ['is_featured' => $topic['is_featured'] ? 0 : 1]);
        AuditService::log($topic['is_featured'] ? 'unfeature' : 'feature', 'topics', $id, ($topic['is_featured'] ? 'फ़ीचर्ड से हटाया: ' : 'फ़ीचर्ड किया: ') . $topic['name']);
        return $request->wantsJson() ? $this->json(['ok' => true, 'featured' => !$topic['is_featured']]) : $this->back();
    }

    public function destroy(Request $request, int $id): Response
    {
        $topic = Topic::find($id) ?? throw new HttpException(404);
        if (($n = TaxonomyService::usage('topics', $id)) > 0) {
            return $this->back()->with('danger', "“{$topic['name']}” $n ख़बरों से जुड़ा है। हटाने की जगह इसे बंद (inactive) कर दें।");
        }
        Topic::delete($id);
        TaxonomyService::removeMenuLinks('topic', $id);
        AuditService::log('delete', 'topics', $id, 'टॉपिक हटाया: ' . $topic['name'], ['name' => $topic['name'], 'slug' => $topic['slug']]);
        return $this->toRoute('admin.topics.index')->with('success', '“' . $topic['name'] . '” हटा दिया गया।');
    }
}
