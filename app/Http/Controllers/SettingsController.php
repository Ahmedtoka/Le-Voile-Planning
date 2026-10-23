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
            'rows'  => User::with('roles')->orderBy('id', 'desc')->paginate(50),
            'roles' => Role::orderBy('name')->get(),
            'factories' => \App\Models\Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
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
            'factory_id'=> ['nullable', 'exists:factories,id'],
            'roles'     => ['array'],
        ], [], ['name' => 'الاسم', 'username' => 'اسم الدخول', 'password' => 'كلمة المرور']);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name'      => $data['name'],
                'username'  => $data['username'],
                'email'     => $data['email'] ?? null,
                'phone'     => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'factory_id'=> $data['factory_id'] ?? null,
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
            'factory_id'=> ['nullable', 'exists:factories,id'],
            'is_active' => ['boolean'],
            'roles'     => ['array'],
        ], [], ['name' => 'الاسم']);

        DB::transaction(function () use ($user, $data, $request) {
            $payload = [
                'name'      => $data['name'],
                'email'     => $data['email'] ?? null,
                'phone'     => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'factory_id'=> $data['factory_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ];
            if (!empty($data['password'])) $payload['password'] = $data['password'];

            $user->update($payload);
            $user->roles()->sync($data['roles'] ?? []);
        });

        return back()->with('success', 'تم التعديل.');
    }

    // ── الأدوار والصلاحيات ──

    // ── قواعد الحساب (الميتينج 37): قابلة للتعديل من الشاشة ──

    public const RULES = [
        'min_inspection_sample_pct'  => ['label' => 'الحد الأدنى لعيّنة الفحص', 'unit' => '% من الأتواب', 'step' => '1', 'default' => 10,
                                         'hint' => '100 توب ⇐ على الأقل 10 أتواب تتفحص. أقل من كده الفحص ما يتحفظش.'],
        'width_margin_cm_per_side'   => ['label' => 'تأمين العروض من كل جانب', 'unit' => 'سم', 'step' => '0.5', 'default' => 2.5,
                                         'hint' => 'عرض التشغيل اللي بيروح للباترونست = أقل عرض مقاس − الرقم ده × 2.'],
        'safety_margin_cm'           => ['label' => 'هامش الأمان على طول الفرشة', 'unit' => 'سم', 'step' => '0.5', 'default' => 5,
                                         'hint' => 'بيتضاف تلقائيًا على طول فرشة الماركر في أمر الشغل لو المخطط سابه فاضي.'],
        'default_po_tolerance_pct'   => ['label' => 'نسبة الزيادة الافتراضية على طلب الشراء', 'unit' => '%', 'step' => '0.5', 'default' => 5,
                                         'hint' => 'أقصى زيادة مسموح المورد يبعتها فوق الكمية — بتظهر في طلب الشراء وتقدر تغيّرها لكل بند.'],
    ];

    public function rules()
    {
        $rules = [];
        foreach (self::RULES as $k => $r) $rules[$k] = $r + ['value' => lv_setting($k, $r['default'])];
        return view('settings.rules', ['title' => 'قواعد الحساب', 'rules' => $rules]);
    }

    public function saveRules(Request $request)
    {
        $data = $request->validate(['rules' => ['required', 'array']]);
        $changed = [];
        foreach (self::RULES as $k => $r) {
            if (!isset($data['rules'][$k]) || !is_numeric($data['rules'][$k])) continue;
            $v = (string) (float) $data['rules'][$k];
            $old = (string) (float) lv_setting($k, $r['default']);
            DB::table('app_settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
            if ($old !== $v) $changed[] = $r['label'] . ': ' . $old . ' ⇐ ' . $v;
        }
        if ($changed) \App\Services\ActivityLogger::log('settings', null, 'تعديل قواعد الحساب — ' . implode(' · ', $changed));
        return back()->with('success', $changed ? 'اتحفظت القواعد: ' . implode(' · ', $changed) : 'مفيش حاجة اتغيرت.');
    }

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
