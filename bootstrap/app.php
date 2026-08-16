<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| حارس manifest الحزم
|--------------------------------------------------------------------------
| bootstrap/cache/packages.php بيتبني على جهاز التطوير وفيه حزم dev
| (زي collision و ignition). لو الملف ده اترفع للسيرفر واتعمل
| composer install --no-dev، الكلاسات دي مش هتكون موجودة.
|
| ولارافيل بيحمّل الملف ده أثناء الإقلاع — قبل ما يقدر يصلّحه — فالنتيجة
| "Class ... not found" وقفلة كاملة حتى في artisan، فمتقدرش حتى تشغّل
| package:discover عشان تصلّحها.
|
| هنا بنتأكد إن كل provider مذكور موجود فعلًا. لو واحد ناقص بنمسح الـ
| manifest ولارافيل بيعيد بناءه لوحده من vendor الموجود.
*/
$__manifest = __DIR__.'/cache/packages.php';

if (is_file($__manifest)) {
    $__packages = @require $__manifest;

    if (is_array($__packages)) {
        foreach ($__packages as $__pkg) {
            foreach ((array) ($__pkg['providers'] ?? []) as $__provider) {
                if (!class_exists($__provider)) {
                    @unlink($__manifest);
                    @unlink(__DIR__.'/cache/services.php');
                    break 2;
                }
            }
        }
    }

    unset($__packages, $__pkg, $__provider);
}

unset($__manifest);

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
