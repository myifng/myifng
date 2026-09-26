<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Gate;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\UploadService;
use App\Validators\UserValidator;

/** यूज़र प्रबंधन: सूची, बनाना, बदलना, हटाना, स्थिति, यूज़र-स्तर अनुमति */
final class UserController extends Controller
{
    private string $uploadError = '';

    public function __construct(private UserRepository $repo = new UserRepository())
    {
    }

    public function index(Request $request): Response
    {
        $filters = ['q' => $request->str('q'), 'role' => $request->int('role'), 'status' => $request->str('status'), 'sort' => $request->str('sort')];
        return $this->view('admin/users/index', [
            'users' => $this->repo->paginate($filters, max(1, $request->int('page', 1))),
            'filters' => $filters,
            'roles' => Role::where([], 'level DESC'),
            'counts' => $this->repo->statusCounts(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/users/form', ['user' => null, 'roles' => Role::assignable()]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, UserValidator::rules(), UserValidator::LABELS);
        $this->guardRole((int) $data['role_id']);
        $data['email'] = strtolower($data['email']);
        $data['password'] = password_hash((string) $request->input('password'), PASSWORD_DEFAULT);
        $data['created_by'] = auth()->id();
        $avatar = $this->avatar($request);
        if ($avatar === false) {
            return $this->back()->withErrors(['avatar' => $this->uploadError])->withInput($request->post());
        }
        if ($avatar) {
            $data['avatar'] = $avatar;
        }
        $id = User::create($data);
        AuditService::log('create', 'users', $id, 'नया यूज़र बनाया: ' . $data['name'], null, $data);
        return $this->toRoute('admin.users.index')->with('success', 'यूज़र “' . $data['name'] . '” बन गया।');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        return $this->view('admin/users/form', ['user' => $user, 'roles' => Role::assignable(), 'history' => $this->repo->loginHistory($id, 10)]);
    }

    public function update(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        $data = $this->validate($request, UserValidator::rules($id), UserValidator::LABELS);
        $data['email'] = strtolower($data['email']);
        $self = $id === auth()->id();

        // अपना रोल/स्थिति ख़ुद नहीं बदल सकते (ग़लती से ख़ुद को बाहर करने से बचाव)
        if ($self && ((int) $data['role_id'] !== (int) $user['role_id'] || $data['status'] !== 'active')) {
            return $this->back()->withErrors(['role_id' => 'आप अपना रोल या स्थिति ख़ुद नहीं बदल सकते।'])->withInput($request->post());
        }
        if ((int) $data['role_id'] !== (int) $user['role_id']) {
            $this->guardRole((int) $data['role_id']);
        }
        if ($this->isLastSuperAdmin($user) && ((int) $data['role_id'] !== (int) $user['role_id'] || $data['status'] !== 'active')) {
            return $this->back()->withErrors(['role_id' => 'यह आख़िरी चालू Super Admin है। पहले कोई दूसरा Super Admin बनाएँ।'])->withInput($request->post());
        }

        if (!empty($data['password'])) {
            $data['password'] = password_hash((string) $request->input('password'), PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        $avatar = $this->avatar($request);
        if ($avatar === false) {
            return $this->back()->withErrors(['avatar' => $this->uploadError])->withInput($request->post());
        }
        if ($avatar) {
            $data['avatar'] = $avatar;
        }
        User::update($id, $data);
        AuditService::log('update', 'users', $id, 'यूज़र बदला: ' . $data['name'], $user, $data);
        return $this->toRoute('admin.users.index')->with('success', 'बदलाव सेव हो गए।');
    }

    public function status(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        $status = $request->str('status');
        if (!isset(User::STATUSES[$status])) {
            throw new HttpException(400);
        }
        if ($id === auth()->id()) {
            return $this->back()->with('danger', 'आप अपने खाते की स्थिति ख़ुद नहीं बदल सकते।');
        }
        if ($status !== 'active' && $this->isLastSuperAdmin($user)) {
            return $this->back()->with('danger', 'आख़िरी चालू Super Admin को बंद नहीं किया जा सकता।');
        }
        User::update($id, ['status' => $status]);
        AuditService::log('status', 'users', $id, $user['name'] . ' की स्थिति: ' . User::STATUSES[$status], ['status' => $user['status']], ['status' => $status]);
        return $this->back()->with('success', $user['name'] . ' की स्थिति “' . User::STATUSES[$status] . '” हो गई।');
    }

    public function destroy(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        if ($id === auth()->id()) {
            return $this->back()->with('danger', 'आप अपना ही खाता नहीं हटा सकते।');
        }
        if ($this->isLastSuperAdmin($user)) {
            return $this->back()->with('danger', 'आख़िरी Super Admin को हटाया नहीं जा सकता।');
        }
        User::softDelete($id);
        AuditService::log('delete', 'users', $id, 'यूज़र हटाया: ' . $user['name'], ['name' => $user['name'], 'email' => $user['email']]);
        return $this->toRoute('admin.users.index')->with('success', 'यूज़र “' . $user['name'] . '” हटा दिया गया।');
    }

    /** यूज़र-स्तर अनुमति: रोल से अलग कुछ अनुमति देना या छीनना */
    public function permissions(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        if ($user['role_slug'] === Gate::SUPER_ADMIN) {
            return $this->back()->with('info', 'Super Admin के पास पहले से सभी अनुमतियाँ हैं।');
        }
        $overrides = [];
        foreach (db()->all('SELECT permission_id, allow FROM {p}user_permissions WHERE user_id = ?', [$id]) as $o) {
            $overrides[(int) $o['permission_id']] = (int) $o['allow'];
        }
        return $this->view('admin/users/permissions', [
            'user' => $user,
            'matrix' => PermissionService::matrix(db(), config('modules')),
            'groups' => config('modules.groups'),
            'rolePerms' => array_flip(PermissionService::rolePermissionIds(db(), (int) $user['role_id'])),
            'overrides' => $overrides,
        ]);
    }

    public function savePermissions(Request $request, int $id): Response
    {
        $user = $this->manageable($id);
        $valid = array_flip(array_map('intval', array_column(db()->all('SELECT id FROM {p}permissions'), 'id')));
        $input = (array) $request->input('perm', []);
        $old = db()->all('SELECT permission_id, allow FROM {p}user_permissions WHERE user_id = ?', [$id]);
        db()->transaction(function ($db) use ($id, $input, $valid) {
            $db->delete('user_permissions', 'user_id = ?', [$id]);
            foreach ($input as $pid => $val) {
                $pid = (int) $pid;
                if (isset($valid[$pid]) && in_array($val, ['allow', 'deny'], true)) {
                    $db->insert('user_permissions', ['user_id' => $id, 'permission_id' => $pid, 'allow' => $val === 'allow' ? 1 : 0]);
                }
            }
        });
        AuditService::log('permissions', 'users', $id, $user['name'] . ' की व्यक्तिगत अनुमतियाँ बदलीं', ['overrides' => count($old)], ['overrides' => count(array_filter($input, fn($v) => $v !== 'inherit'))]);
        return $this->toRoute('admin.users.permissions', ['id' => $id])->with('success', 'अनुमतियाँ सेव हो गईं।');
    }

    public function export(Request $request): Response
    {
        $rows = db()->all('SELECT u.id, u.name, u.email, u.mobile, r.name role, u.status, u.last_login_at, u.created_at FROM {p}users u JOIN {p}roles r ON r.id = u.role_id WHERE u.deleted_at IS NULL ORDER BY u.id');
        AuditService::log('export', 'users', null, count($rows) . ' यूज़र एक्सपोर्ट किए');
        return Response::csv('users-' . date('Y-m-d') . '.csv', ['ID', 'नाम', 'ईमेल', 'मोबाइल', 'रोल', 'स्थिति', 'आख़िरी लॉगिन', 'बना'], array_map(fn($r) => [$r['id'], $r['name'], $r['email'], $r['mobile'], $r['role'], User::STATUSES[$r['status']] ?? $r['status'], $r['last_login_at'], $r['created_at']], $rows));
    }

    /* ---------- सहायक ---------- */

    /** यूज़र मौजूद हो और मौजूदा यूज़र उसे संभाल सकता हो (अपने से नीचे के स्तर वाला, या ख़ुद) */
    private function manageable(int $id): array
    {
        $user = User::findWithRole($id) ?? throw new HttpException(404);
        if ($id !== auth()->id() && !app('gate')->outranks((int) $user['role_level'])) {
            throw new HttpException(403, 'आप अपने बराबर या ऊँचे स्तर के यूज़र को नहीं बदल सकते।');
        }
        return $user;
    }

    private function guardRole(int $roleId): void
    {
        $role = Role::find($roleId) ?? throw new HttpException(404);
        if (!app('gate')->outranks((int) $role['level'])) {
            throw new HttpException(403, 'आप अपने बराबर या ऊँचे स्तर का रोल नहीं दे सकते।');
        }
    }

    private function isLastSuperAdmin(array $user): bool
    {
        return $user['role_slug'] === Gate::SUPER_ADMIN && $user['status'] === 'active' && User::activeSuperAdmins() <= 1;
    }

    /** अवतार अपलोड: null = कोई फ़ाइल नहीं, false = त्रुटि, string = पाथ */
    private function avatar(Request $request): string|false|null
    {
        $file = $request->file('avatar');
        if (!$file) {
            return null;
        }
        $r = UploadService::store($file, 'image', 'avatars', BASE_PATH . '/public/uploads', 400);
        if (!$r['ok']) {
            $this->uploadError = $r['error'];
            return false;
        }
        return $r['path'];
    }
}
