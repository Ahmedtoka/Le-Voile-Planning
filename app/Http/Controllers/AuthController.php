<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (auth()->check()) return redirect()->route('dashboard');
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'اسم الدخول', 'password' => 'كلمة المرور']);

        if (!Auth::attempt(['username' => $data['username'], 'password' => $data['password'], 'is_active' => 1], $request->boolean('remember'))) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'اسم الدخول أو كلمة المرور غير صحيحة.']);
        }

        $request->session()->regenerate();
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
