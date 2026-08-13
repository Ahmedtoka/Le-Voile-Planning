<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentComment extends Model
{
    protected $guarded = [];
    protected $casts = ['mentions' => 'array'];

    public const KINDS = [
        'note'     => 'ملاحظة',
        'question' => 'استفسار',
        'answer'   => 'رد',
        'decision' => 'قرار',
        'system'   => 'حركة النظام',
    ];

    public const KIND_COLORS = [
        'note'     => 'secondary',
        'question' => 'warning',
        'answer'   => 'info',
        'decision' => 'success',
        'system'   => 'light',
    ];

    public function commentable() { return $this->morphTo(); }
    public function user()        { return $this->belongsTo(User::class); }
    public function replyTo()     { return $this->belongsTo(DocumentComment::class, 'reply_to_id'); }

    public function getKindNameAttribute(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function getKindColorAttribute(): string
    {
        return self::KIND_COLORS[$this->kind] ?? 'secondary';
    }

    public function getIsImageAttribute(): bool
    {
        return $this->attachment_mime && str_starts_with($this->attachment_mime, 'image/');
    }
}
