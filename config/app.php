<?php

use Illuminate\Support\Facades\Facade;

return [
    'name' => env('APP_NAME', 'LvPlanning'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Cairo'),
    'locale' => env('APP_LOCALE', 'ar'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'ar'),
    'faker_locale' => 'ar_EG',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],

    // مهم: بدون كده Route:: و Str:: مش هيشتغلوا جوه ملفات Blade
    'aliases' => Facade::defaultAliases()->toArray(),
];
