<?php

namespace App\Support;

trait HasDocumentStatus
{
    public const DEFAULT_STATUSES = [
        'draft'    => 'مسودة',
        'pending'  => 'تحت الاعتماد',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
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
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }

    /** المستند يتقفل عن التعديل بمجرد ما يخرج من المسودة */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }
}
