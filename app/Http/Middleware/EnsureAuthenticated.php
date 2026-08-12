<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }
        if (!auth()->user()->is_active) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['username' => 'الحساب موقوف. كلّم مدير النظام.']);
        }
        return $next($request);
    }
}
