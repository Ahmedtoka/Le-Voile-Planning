<?php

namespace App\Services;

use App\Models\AppNotification;
use Illuminate\Support\Facades\DB;

/**
 * الإشعارات. دلوقتي بتتخزّن في الداتابيز وبتظهر في الجرس على الداشبورد.
 * نفس الجدول هيغذّي الـ Push في التطبيق لما نعمله.
 */
class Notifier
{
    public static function send(?int $userId, string $type, string $title, ?string $body = null, ?string $link = null, string $severity = 'info'): void
    {
        if (!$userId) return;

        AppNotification::create([
            'user_id'  => $userId,
            'type'     => $type,
            'title'    => $title,
            'body'     => $body,
            'link'     => $link,
            'severity' => $severity,
        ]);
    }

    public static function broadcastToRole(string $roleKey, string $type, string $title, ?string $body = null, ?string $link = null, string $severity = 'info'): void
    {
        $userIds = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.key', $roleKey)
            ->pluck('role_user.user_id');

        foreach ($userIds as $uid) {
            self::send($uid, $type, $title, $body, $link, $severity);
        }
    }
}
