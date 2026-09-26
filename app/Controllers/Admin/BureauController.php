<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Bureau;
use App\Models\Location;
use App\Services\AuditService;

/** ब्यूरो: मुख्यालय → राज्य → ज़िला → तहसील/शहर; प्रमुख; ब्यूरो-वार रिपोर्ट */
final class BureauController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = db()->all(
            "SELECT b.*, l.name AS location, u.name AS chief,
                (SELECT COUNT(*) FROM {p}reporters r WHERE r.bureau_id = b.id AND r.status = 'active') AS reporters,
                (SELECT COUNT(*) FROM {p}news n JOIN {p}reporters r2 ON r2.user_id = n.reporter_id WHERE r2.bureau_id = b.id AND n.status = 'published' AND n.deleted_at IS NULL
                    AND n.published_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month_news,
                (SELECT COALESCE(SUM(n.views), 0) FROM {p}news n JOIN {p}reporters r3 ON r3.user_id = n.reporter_id WHERE r3.bureau_id = b.id AND n.status = 'published' AND n.deleted_at IS NULL
                    AND n.published_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month_views
             FROM {p}bureaus b LEFT JOIN {p}locations l ON l.id = b.location_id LEFT JOIN {p}users u ON u.id = b.chief_user_id ORDER BY FIELD(b.type,'head_office','state','district','tehsil','city'), b.name"
        );
        // पेड़: parent के नीचे children
        $by = [];
        foreach ($rows as $r) {
            $by[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $tree = [];
        $walk = function (int $pid, int $depth) use (&$walk, &$tree, $by) {
            foreach ($by[$pid] ?? [] as $r) {
                $tree[] = $r + ['depth' => $depth];
                $walk((int) $r['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $this->view('admin/bureaus/index', ['tree' => $tree]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, $request->int('parent') ?: null);
    }

    private function form(?array $b, ?int $parent): Response
    {
        return $this->view('admin/bureaus/form', [
            'b' => $b, 'parentId' => $b['parent_id'] ?? $parent,
            'parents' => array_column(db()->all('SELECT id, name FROM {p}bureaus WHERE id <> ? ORDER BY name', [(int) ($b['id'] ?? 0)]), 'name', 'id'),
            'location' => !empty($b['location_id']) ? Location::find((int) $b['location_id']) : null,
            'chiefs' => array_column(db()->all("SELECT id, name FROM {p}users WHERE deleted_at IS NULL AND status = 'active' ORDER BY name"), 'name', 'id'),
        ]);
    }

    public function store(Request $request): Response
    {
        $d = $this->payload($request, 0);
        $id = Bureau::create($d);
        AuditService::log('create', 'bureaus', $id, 'ब्यूरो बनाया: ' . $d['name']);
        return $this->toRoute('admin.bureaus.index')->with('success', 'ब्यूरो “' . $d['name'] . '” बन गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form(Bureau::find($id) ?? throw new HttpException(404), null);
    }

    public function update(Request $request, int $id): Response
    {
        $b = Bureau::find($id) ?? throw new HttpException(404);
        $d = $this->payload($request, $id);
        Bureau::update($id, $d);
        AuditService::log('update', 'bureaus', $id, 'ब्यूरो बदला: ' . $d['name'], $b, $d);
        return $this->toRoute('admin.bureaus.index')->with('success', 'ब्यूरो सेव हो गया।');
    }

    private function payload(Request $request, int $id): array
    {
        $v = $this->validate($request, [
            'name' => 'required|max:150', 'type' => 'required|in:' . implode(',', array_keys(Bureau::TYPES)), 'parent_id' => 'nullable|integer|exists:bureaus,id',
            'location_id' => 'nullable|integer|exists:locations,id', 'address' => 'nullable|max:300', 'phone' => 'nullable|max:20', 'email' => 'nullable|email|max:190',
            'chief_user_id' => 'nullable|integer|exists:users,id', 'status' => 'required|in:active,inactive',
        ], ['name' => 'नाम', 'type' => 'प्रकार', 'parent_id' => 'ऊपर वाला ब्यूरो', 'location_id' => 'लोकेशन', 'address' => 'पता', 'phone' => 'फ़ोन', 'email' => 'ईमेल', 'chief_user_id' => 'ब्यूरो प्रमुख', 'status' => 'स्थिति']);
        $parent = $v['parent_id'] ? (int) $v['parent_id'] : null;
        // चक्र नहीं
        for ($p = $parent, $i = 0; $p && $i < 10; $i++) {
            if ($p === $id) {
                throw new ValidationException(['parent_id' => 'ब्यूरो को ख़ुद या अपने नीचे वाले ब्यूरो के नीचे नहीं रखा जा सकता।'], $request->post());
            }
            $p = (int) db()->value('SELECT parent_id FROM {p}bureaus WHERE id = ?', [$p]);
        }
        if ($v['type'] !== 'head_office' && !$parent) {
            throw new ValidationException(['parent_id' => 'मुख्यालय के अलावा हर ब्यूरो किसी ऊपर वाले ब्यूरो के नीचे हो।'], $request->post());
        }
        return ['name' => strip_tags($v['name']), 'type' => $v['type'], 'parent_id' => $parent, 'location_id' => $v['location_id'] ? (int) $v['location_id'] : null,
            'address' => $v['address'] ? strip_tags($v['address']) : null, 'phone' => $v['phone'], 'email' => $v['email'],
            'chief_user_id' => $v['chief_user_id'] ? (int) $v['chief_user_id'] : null, 'status' => $v['status']];
    }

    public function destroy(Request $request, int $id): Response
    {
        $b = Bureau::find($id) ?? throw new HttpException(404);
        if (db()->value('SELECT id FROM {p}bureaus WHERE parent_id = ? LIMIT 1', [$id]) || db()->value('SELECT id FROM {p}reporters WHERE bureau_id = ? LIMIT 1', [$id])) {
            return $this->back()->with('danger', 'इस ब्यूरो के नीचे ब्यूरो या रिपोर्टर हैं। पहले उन्हें दूसरे ब्यूरो में ले जाएँ।');
        }
        Bureau::delete($id);
        AuditService::log('delete', 'bureaus', $id, 'ब्यूरो हटाया: ' . $b['name']);
        return $this->toRoute('admin.bureaus.index')->with('success', 'ब्यूरो हटा दिया गया।');
    }
}
