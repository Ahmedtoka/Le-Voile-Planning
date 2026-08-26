<?php

namespace App\Support;

/**
 * هوية المستند: نوعه ورقمه.
 *
 * كان اسمه HasDocumentIdentity أيام ما كان فيه دورة اعتماد. الاعتمادات اتشالت،
 * بس الميثودين دول لسه شغالين في السجل والإشعارات ورسايل «تم».
 */
trait HasDocumentIdentity
{
    public function docType(): string
    {
        return defined(static::class.'::DOC_TYPE') ? static::DOC_TYPE : class_basename($this);
    }

    /** رقم المستند للعرض — أول عمود ترقيم موجود */
    public function docNumber(): string
    {
        foreach (['doc_no', 'po_no', 'wo_no', 'consignment_no', 'code'] as $field) {
            if (!empty($this->{$field})) return (string) $this->{$field};
        }

        return '#'.$this->getKey();
    }
}
