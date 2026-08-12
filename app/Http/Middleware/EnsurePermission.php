<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = auth()->user();
        if (!$user) return redirect()->route('login');

        foreach ($permissions as $p) {
            if ($user->can2($p)) return $next($request);
        }

        abort(403, 'مالكش صلاحية على الشاشة دي.');
    }
}
