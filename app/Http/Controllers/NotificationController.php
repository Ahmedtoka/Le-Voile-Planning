<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;

class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications', [
            'title' => 'الإشعارات',
            'rows'  => AppNotification::where('user_id', auth()->id())->latest('id')->paginate(50),
        ]);
    }

    public function markRead($id)
    {
        AppNotification::where('user_id', auth()->id())->where('id', $id)->update(['read_at' => now()]);
        return back();
    }

    public function markAllRead()
    {
        AppNotification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('success', 'تم تعليم الكل كمقروء.');
    }
}
