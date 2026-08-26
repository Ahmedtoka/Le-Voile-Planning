<?php

namespace App\Support;

trait HasDocumentStatus
{
    /* السيستم من غير اعتمادات: المستند يا مسودة يا خلص.
       «تم» معناها إن آثاره اتنفذت والخطوة اللي بعدها نزلت لصاحبها. */
    public const DEFAULT_STATUSES = [
        'draft'    => 'مسودة',
        'approved' => 'تم',
        'rejected' => 'ملغي',
    ];

    public function statusList(): array
    {
        return defined(static::class.'::STATUSES') ? static::STATUSES : self::DEFAULT_STATUSES;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusList()[$this->status] ?? (string) $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'                                     => 'secondary',
            'pending'                                   => 'warning',
            'approved', 'received', 'closed'            => 'success',
            'rejected', 'cancelled'                     => 'danger',
            default                                     => 'info',
        };
    }

    public function isDraft(): bool    { return $this->status === 'draft'; }
    /** @deprecated مفيش «تحت الاعتماد» في السيستم — موجودة للتوافق بس */
    public function isPending(): bool  { return false; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    /** خلص وآثاره اتنفذت */
    public function isDone(): bool     { return in_array($this->status, ['approved', 'received', 'closed'], true); }

    /** المستند يتقفل عن التعديل بمجرد ما يخرج من المسودة */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
