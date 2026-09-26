<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\UploadService;
use App\Validators\UserValidator;

/** मेरी प्रोफ़ाइल: जानकारी, फ़ोटो, पासवर्ड, लॉगिन हिस्ट्री */
final class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return $this->view('admin/profile/index', ['me' => user(), 'history' => (new UserRepository())->loginHistory((int) auth()->id(), 15)]);
    }

    public function update(Request $request): Response
    {
        $id = (int) auth()->id();
        $old = User::find($id);
        $data = $this->validate($request, UserValidator::profileRules($id), UserValidator::LABELS);
        $data['email'] = strtolower($data['email']);
        if ($file = $request->file('avatar')) {
            $r = UploadService::store($file, 'image', 'avatars', BASE_PATH . '/public/uploads', 400);
            if (!$r['ok']) {
                return $this->back()->withErrors(['avatar' => $r['error']])->withInput($request->post());
            }
            $data['avatar'] = $r['path'];
        }
        User::update($id, $data);
        auth()->refresh();
        AuditService::log('update', 'profile', $id, 'अपनी प्रोफ़ाइल बदली', $old, $data);
        return $this->back()->with('success', 'प्रोफ़ाइल सेव हो गई।');
    }

    public function password(Request $request): Response
    {
        $this->validate($request, UserValidator::passwordRules(), UserValidator::LABELS + ['password' => 'नया पासवर्ड']);
        $id = (int) auth()->id();
        $row = User::find($id);
        if (!password_verify((string) $request->input('current_password'), (string) $row['password'])) {
            return $this->back()->withErrors(['current_password' => 'मौजूदा पासवर्ड ग़लत है।']);
        }
        User::update($id, ['password' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT)]);
        app('session')->regenerate();
        AuditService::log('password_change', 'profile', $id, 'अपना पासवर्ड बदला');
        return $this->back()->with('success', 'पासवर्ड बदल गया।');
    }
}
