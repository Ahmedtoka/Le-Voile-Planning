<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function ok(string $message = 'تم الحفظ بنجاح.')
    {
        return back()->with('success', $message);
    }
}
