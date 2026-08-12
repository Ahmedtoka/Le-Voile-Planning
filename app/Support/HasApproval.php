<?php

namespace App\Support;

use App\Models\Approval;

trait HasApproval
{
    public function approvals()
    {
        return $this->morphMany(Approval::class, 'subject', 'subject_type', 'subject_id')->latest('id');
    }

    public function approval()
    {
        return $this->morphOne(Approval::class, 'subject', 'subject_type', 'subject_id')->latestOfMany();
    }

    public function docType(): string
    {
        return defined(static::class.'::DOC_TYPE') ? static::DOC_TYPE : class_basename($this);
    }

    /** رقم المستند للعرض في شاشة الاعتمادات */
    public function docNumber(): string
    {
        foreach (['doc_no', 'po_no', 'wo_no', 'consignment_no', 'code'] as $field) {
            if (!empty($this->{$field})) return (string) $this->{$field};
        }
        return '#'.$this->getKey();
    }
}
