<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.user' => \App\Http\Middleware\EnsureAuthenticated::class,
            'can.do'    => \App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 419 = الجلسة انتهت أو الكوكي مش بيتحفظ — أشهر مصدر لصفحة بيضا
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()->route('login')->withErrors(['username' =>
                'انتهت الجلسة أو الكوكي مش بيتحفظ. جرّب تاني — ولو تكررت، شغّل php artisan lv:doctor']);
        });
    })->create();
