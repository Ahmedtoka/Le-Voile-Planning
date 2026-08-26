<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Services\MenuCounters;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrapFive();
        setlocale(LC_TIME, 'ar_EG.UTF-8', 'ar_EG', 'ar');

        // بيانات الشريط العلوي — الجرس وعدّاد الاعتمادات
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with([
                'navUnread'    => $user ? AppNotification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
                // «اللي مستني مني» — رقم جنب كل شاشة فيها شغل عليّا
                'navCounters'  => MenuCounters::for($user),
            ]);
        });
    }
}
