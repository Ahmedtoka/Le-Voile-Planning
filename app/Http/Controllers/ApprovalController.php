<?php

namespace App\Http\Controllers;

/**
 * @deprecated السيستم بقى من غير اعتمادات — كل مستند بيخلص ويدفع اللي بعده.
 * الكلاس ده متسايب فاضي عشان أي راوت أو لينك قديم ما يكسرش.
 */
class ApprovalController extends Controller
{
    public function index()
    {
        return redirect()->route('dashboard');
    }
}
