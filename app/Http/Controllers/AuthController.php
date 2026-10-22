<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\UserSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /** بصمة بنحطها في الجلسة عشان نتأكد إنها بتتحفظ فعلًا */
    private const PROBE = 'lv_session_probe';

    public function showLogin(Request $request)
    {
        if (auth()->check()) return redirect()->route('dashboard');

        $request->session()->put(self::PROBE, 1);

        return view('auth.login', [
            // زراير الدخول التجريبي — بتظهر بس لما الدخول التجريبي شغّال
            'demoUsers' => self::demoLoginEnabled() ? UserSeeder::demoUsers() : collect(),
        ]);
    }

    /**
     * الدخول التجريبي السريع شغّال؟
     * على local تلقائيًا، أو بـ LV_DEMO_LOGIN=true في .env (config/lvplanning.php).
     */
    public static function demoLoginEnabled(): bool
    {
        return config('app.env') === 'local' || (bool) config('lvplanning.demo_login');
    }

    /**
     * دخول تجريبي بدور معين من زرار في شاشة اللوجين — من غير كلمة مرور.
     * مسموح بس لحسابات الديمو اللي في UserSeeder، ولما الدخول التجريبي شغّال.
     */
    public function loginAs(Request $request, User $user)
    {
        abort_unless(self::demoLoginEnabled(), 404);

        $demo = array_column(UserSeeder::USERS, 0);
        abort_unless($user->is_active && in_array($user->username, $demo, true), 403, 'الحساب ده مش من حسابات الديمو.');

        if (auth()->check()) Auth::logout();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::PROBE);
        ActivityLogger::log('login', $user, 'دخول تجريبي سريع — ' . $user->name . ' (' . $user->job_title . ')');

        return redirect()->route('dashboard');
    }

    public function login(Request $request)
    {
        /*
         | أشهر مشكلة بعد الرفع: الجلسة مش بتتحفظ (كوكي secure على http،
         | أو SESSION_DOMAIN غلط، أو storage مش قابل للكتابة). النتيجة إن
         | الصفحة بترجعك للوجين من غير أي رسالة. البصمة دي بتكشفها بالاسم.
        */
        if (!$request->session()->has(self::PROBE)) {
            return back()->withInput($request->only('username'))->withErrors(['username' =>
                'الجلسة مش بتتحفظ على السيرفر، فالدخول مش هيكمل. '
                . 'غالبًا SESSION_SECURE_COOKIE=true والموقع بيفتح بـ http، '
                . 'أو مجلد storage مش قابل للكتابة. شغّل: php artisan lv:doctor']);
        }

        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'اسم الدخول', 'password' => 'كلمة المرور']);

        if (!Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => 1], $request->boolean('remember'))) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'اسم الدخول أو كلمة المرور غير صحيحة.']);
        }

        $request->session()->regenerate();
        $request->session()->forget(self::PROBE);
        ActivityLogger::log('login', auth()->user(), 'تسجيل دخول');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLogger::log('logout', auth()->user(), 'تسجيل خروج');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
