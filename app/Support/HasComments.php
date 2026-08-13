<?php

namespace App\Support;

use App\Models\DocumentComment;

/**
 * نقاش المستند.
 *
 * أي مستند بياخد الترايت ده بيبقى زي التيكيت: نقاش بين الأقسام
 * مع مرفقات كإثبات. التعليقات ما بتتحذفش — دي جزء من سجل المستند.
 */
trait HasComments
{
    public function comments()
    {
        return $this->morphMany(DocumentComment::class, 'commentable')->orderBy('id');
    }

    public function commentsCount(): int
    {
        return $this->comments()->count();
    }

    /** تسجيل حركة نظام في نفس الخيط — عشان النقاش والحركة يبقوا في مكان واحد */
    public function logSystemComment(string $body): void
    {
        $this->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $body,
            'kind'    => 'system',
        ]);
    }
}
