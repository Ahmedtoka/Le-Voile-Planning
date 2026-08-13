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

        /*
        | صفحة الخطأ التفصيلية بتاعة Symfony بتستدعي highlight_file().
        | استضافات كتير (Cloudways من ضمنها) بتقفل الدالة دي في disable_functions،
        | فصفحة الخطأ نفسها بتقع وتعمل تكرار لا نهائي والخطأ الحقيقي بيتخبّى.
        |
        | هنا بنعرض صفحة بديلة بسيطة فيها الخطأ الحقيقي ومكانه.
        */
        $exceptions->render(function (\Throwable $e, $request) {
            if (!config('app.debug') || function_exists('highlight_file')) {
                return null;   // كمّل بالسلوك الطبيعي
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'type'    => get_class($e),
                    'file'    => $e->getFile() . ':' . $e->getLine(),
                ], 500);
            }

            return response()->view('errors.debug', ['e' => $e], 500);
        });
    })->create();
