<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * راوتس CRUD الموحّدة لشاشات البيانات الأساسية.
 *
 * القراءة والكتابة ليهم صلاحيتين مختلفتين — عشان دور زي "فني معمل"
 * يقدر يشوف الموديلات والألوان من غير ما يعدّل فيها.
 */
class CrudRoutes
{
    public static function make(string $name, string $controller, string $viewPerm = 'master.view', string $managePerm = 'master.manage'): void
    {
        Route::middleware("can.do:{$viewPerm}")->group(function () use ($name, $controller) {
            Route::get($name, [$controller, 'index'])->name("{$name}.index");
        });

        Route::middleware("can.do:{$managePerm}")->group(function () use ($name, $controller) {
            Route::get("{$name}/create",    [$controller, 'create'])->name("{$name}.create");
            Route::post($name,              [$controller, 'store'])->name("{$name}.store");
            Route::get("{$name}/{id}/edit", [$controller, 'edit'])->name("{$name}.edit");
            Route::put("{$name}/{id}",      [$controller, 'update'])->name("{$name}.update");
            Route::delete("{$name}/{id}",   [$controller, 'destroy'])->name("{$name}.destroy");
        });
    }
}
