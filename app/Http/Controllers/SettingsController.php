<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
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

    // ── دورات الاعتماد ──

    public function approvalFlows()
    {
        return view('settings.approval_flows', [
            'title' => 'دورات الاعتماد',
            'flows' => ApprovalFlow::with(['steps.role', 'steps.user'])->orderBy('id')->get(),
            'roles' => Role::orderBy('name')->pluck('name', 'id'),
            'users' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function addFlowStep(Request $request, ApprovalFlow $flow)
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:191'],
            'role_id'      => ['nullable', 'exists:roles,id'],
            'user_id'      => ['nullable', 'exists:users,id'],
            'min_amount'   => ['nullable', 'numeric', 'min:0'],
            'is_mandatory' => ['boolean'],
        ], [], ['title' => 'اسم الخطوة']);

        if (empty($data['role_id']) && empty($data['user_id'])) {
            return back()->withErrors(['msg' => 'لازم تحدد دور أو مستخدم للخطوة.']);
        }

        ApprovalFlowStep::create($data + [
            'approval_flow_id' => $flow->id,
            'step_no'          => (int) $flow->steps()->max('step_no') + 1,
            'is_mandatory'     => $request->boolean('is_mandatory', true),
        ]);

        return back()->with('success', 'تمت إضافة الخطوة.');
    }

    public function deleteFlowStep(ApprovalFlow $flow, ApprovalFlowStep $step)
    {
        $step->delete();

        // إعادة ترقيم الخطوات
        foreach ($flow->steps()->orderBy('step_no')->get() as $i => $s) {
            $s->update(['step_no' => $i + 1]);
        }

        return back()->with('success', 'تم حذف الخطوة.');
    }

    public function toggleFlow(ApprovalFlow $flow)
    {
        $flow->update(['is_active' => !$flow->is_active]);
        return back()->with('success', 'تم تغيير حالة الدورة.');
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
