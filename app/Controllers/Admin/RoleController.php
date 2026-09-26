<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Gate;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Str;
use App\Models\Role;
use App\Repositories\RoleRepository;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Validators\RoleValidator;

/** रोल और अनुमति मैट्रिक्स */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/roles/index', ['roles' => (new RoleRepository())->allWithCounts(), 'total' => (int) db()->value('SELECT COUNT(*) FROM {p}permissions')]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function store(Request $request): Response
    {
        $request = $this->withSlug($request);
        $data = $this->validate($request, RoleValidator::rules(), RoleValidator::LABELS);
        if (!app('gate')->outranks((int) $data['level'])) {
            return $this->back()->withErrors(['level' => 'स्तर आपके अपने रोल के स्तर से कम होना चाहिए।'])->withInput($request->post());
        }
        $id = db()->transaction(function () use ($data, $request) {
            $id = Role::create($data + ['is_system' => 0]);
            PermissionService::setRolePermissions(db(), $id, $this->allowedIds((array) $request->input('permissions', [])));
            return $id;
        });
        AuditService::log('create', 'roles', $id, 'नया रोल बनाया: ' . $data['name'], null, $data);
        return $this->toRoute('admin.roles.index')->with('success', 'रोल “' . $data['name'] . '” बन गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        return $this->form($this->manageable($id));
    }

    public function update(Request $request, int $id): Response
    {
        $role = $this->manageable($id);
        if ($role['is_system']) {
            // system रोल का स्लग नहीं बदलता
            $request = $this->withSlug($request, $role['slug']);
        } else {
            $request = $this->withSlug($request);
        }
        $data = $this->validate($request, RoleValidator::rules($id), RoleValidator::LABELS);
        if (!app('gate')->outranks((int) $data['level'])) {
            return $this->back()->withErrors(['level' => 'स्तर आपके अपने रोल के स्तर से कम होना चाहिए।'])->withInput($request->post());
        }
        $oldPerms = PermissionService::rolePermissionIds(db(), $id);
        db()->transaction(function () use ($id, $data, $role, $request) {
            Role::update($id, $data);
            if ($role['slug'] !== Gate::SUPER_ADMIN) {
                PermissionService::setRolePermissions(db(), $id, $this->allowedIds((array) $request->input('permissions', [])));
            }
        });
        $newPerms = PermissionService::rolePermissionIds(db(), $id);
        AuditService::log('update', 'roles', $id, 'रोल बदला: ' . $data['name'] . ' (अनुमतियाँ ' . count($oldPerms) . ' → ' . count($newPerms) . ')', $role, $data);
        return $this->toRoute('admin.roles.index')->with('success', 'रोल “' . $data['name'] . '” सेव हो गया।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $role = $this->manageable($id);
        if ($role['is_system']) {
            return $this->back()->with('danger', 'सिस्टम रोल हटाया नहीं जा सकता।');
        }
        if (($n = Role::userCount($id)) > 0) {
            return $this->back()->with('danger', "इस रोल में $n यूज़र हैं। पहले उन्हें दूसरा रोल दें।");
        }
        Role::delete($id);
        AuditService::log('delete', 'roles', $id, 'रोल हटाया: ' . $role['name'], $role);
        return $this->toRoute('admin.roles.index')->with('success', 'रोल हटा दिया गया।');
    }

    /** मॉड्यूल रजिस्ट्री से नई अनुमतियाँ जोड़ें (नया मॉड्यूल आने पर) */
    public function sync(Request $request): Response
    {
        $added = PermissionService::sync(db(), config('modules.modules'));
        AuditService::log('sync', 'roles', null, "अनुमतियाँ sync कीं: $added नई");
        return $this->back()->with('success', $added ? "$added नई अनुमतियाँ जुड़ीं।" : 'सभी अनुमतियाँ पहले से मौजूद हैं।');
    }

    /* ---------- सहायक ---------- */

    private function form(?array $role): Response
    {
        return $this->view('admin/roles/form', [
            'role' => $role,
            'matrix' => PermissionService::matrix(db(), config('modules')),
            'groups' => config('modules.groups'),
            'selected' => array_flip($role ? PermissionService::rolePermissionIds(db(), (int) $role['id']) : []),
            'grantable' => $this->grantable(),
        ]);
    }

    /** जो अनुमतियाँ मौजूदा यूज़र के पास ख़ुद हैं, वही वह किसी रोल को दे सकता है */
    private function grantable(): ?array
    {
        if (is_super_admin()) {
            return null; // सब
        }
        $mine = array_keys(app('gate')->permissions());
        return $mine ? array_flip(array_map('intval', array_column(db()->all('SELECT id FROM {p}permissions WHERE name IN (' . \App\Core\Database::in($mine) . ')', $mine), 'id'))) : [];
    }

    private function allowedIds(array $ids): array
    {
        $grantable = $this->grantable();
        $ids = array_map('intval', $ids);
        return $grantable === null ? $ids : array_values(array_filter($ids, fn($i) => isset($grantable[$i])));
    }

    private function manageable(int $id): array
    {
        $role = Role::find($id) ?? throw new HttpException(404);
        if (!app('gate')->outranks((int) $role['level'])) {
            throw new HttpException(403, 'आप अपने बराबर या ऊँचे स्तर का रोल नहीं बदल सकते।');
        }
        return $role;
    }

    /** स्लग ख़ाली हो तो नाम से बनाएँ */
    private function withSlug(Request $request, ?string $force = null): Request
    {
        $post = $request->post();
        $post['slug'] = $force ?? (trim((string) ($post['slug'] ?? '')) !== '' ? Str::slug((string) $post['slug']) : Str::slug((string) ($post['name'] ?? '')));
        return new Request($request->query(), $post, [], $_SERVER, '');
    }
}
