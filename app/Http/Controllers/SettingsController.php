<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    // ── المستخدمين ──

    public function users(Request $request)
    {
        return view('settings.users', [
            'title' => 'المستخدمين',
            'rows'  => User::with('roles')->orderBy('name')->paginate(50),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:191'],
            'username'  => ['required', 'string', 'max:191', 'unique:users,username'],
            'email'     => ['nullable', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'password'  => ['required', 'string', 'min:6'],
            'roles'     => ['array'],
        ], [], ['name' => 'الاسم', 'username' => 'اسم الدخول', 'password' => 'كلمة المرور']);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'username'  => $data['username'],
                'email'     => $data['email'] ?? null,
                'phone'     => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'password'  => $data['password'],
                'is_active' => true,
            ]);
            $user->roles()->sync($data['roles'] ?? []);
        });

        return back()->with('success', 'تمت إضافة المستخدم.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:191'],
            'email'     => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'password'  => ['nullable', 'string', 'min:6'],
            'is_active' => ['boolean'],
            'roles'     => ['array'],
        ], [], ['name' => 'الاسم']);

        DB::transaction(function () use ($user, $data, $request) {
            $payload = [
                'name'      => $data['name'],
                'email'     => $data['email'] ?? null,
                'phone'     => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ];
            if (!empty($data['password'])) $payload['password'] = $data['password'];

            $user->update($payload);
            $user->roles()->sync($data['roles'] ?? []);
        });

        return back()->with('success', 'تم التعديل.');
    }

    // ── الأدوار والصلاحيات ──

    public function roles()
    {
        return view('settings.roles', [
            'title'       => 'الأدوار والصلاحيات',
            'roles'       => Role::with('permissions')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('key')->get()->groupBy('group'),
        ]);
    }

    public function saveRolePermissions(Request $request, Role $role)
    {
        $role->permissions()->sync($request->input('permissions', []));
        return back()->with('success', 'تم حفظ صلاحيات الدور «' . $role->name . '».');
    }

    // ── سجل الحركة ──

    public function activity(Request $request)
    {
        $q = ActivityLog::with('user')->latest('id');
        if ($a = $request->get('action'))  $q->where('action', $a);
        if ($u = $request->get('user_id')) $q->where('user_id', $u);

        return view('settings.activity', [
            'title'   => 'سجل الحركة',
            'rows'    => $q->paginate(60)->withQueryString(),
            'users'   => User::orderBy('name')->pluck('name', 'id'),
            'actions' => ActivityLog::distinct()->pluck('action'),
        ]);
    }
}
