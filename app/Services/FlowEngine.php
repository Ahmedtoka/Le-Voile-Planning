<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ══════════════════════════════════════════════════════════════════
 *  محرك الفلو — السيستم من غير اعتمادات
 * ══════════════════════════════════════════════════════════════════
 *
 * القاعدة الوحيدة: **اللي بيعمل المستند هو اللي بيقرره.**
 * مفيش «إرسال للاعتماد» ولا «اعتماد» ولا انتظار توقيع — أول ما المستند
 * يخلص، آثاره بتتنفذ فورًا واللي بعده بينزل عند صاحبه في نفس اللحظة.
 *
 *   طلب شراء  ⇒ المشتريات (تسعير) ⇒ الحسابات (متابعة) + المخزن (متوقع وصوله)
 *   إذن إضافة ⇒ يا فحص يا مخزن مباشرة (حسب قرار المستلم)
 *   فحص      ⇒ المعمل
 *   معمل     ⇒ جاهز لإذن استلام الخام
 *   استلام   ⇒ متاح في المخزن لأوامر الشغل
 *   أمر شغل  ⇒ صرف خام ⇒ بيان قص ⇒ استلام إنتاج ⇒ يقفل
 *
 * كل خطوة بترجع رسالة «تم» + الخطوة اللي بعدها ومين المسؤول عنها.
 */
class FlowEngine
{
    /**
     * إنهاء مستند: بيتعلّم «تم» وآثاره بتتنفذ فورًا.
     *
     * ⚠️ الحماية من التكرار مهمة هنا: آثار المستندات **مش idempotent** —
     * increment على المنصرف، حركة مخزون جديدة، طلب شراء تلقائي للون البديل.
     * لو المستخدم دوس «تم» مرتين بسرعة (أو الشبكة عملت retry)، الطلبين
     * ممكن يعدّوا من `isEditable()` في الكنترولر لأنها قراءة من غير قفل.
     * فبنقفل الصف جوه الترانزاكشن ونتأكد إنه لسه ما اتقفلش قبل ما نطبّق أي أثر.
     *
     * @param  Model  $doc  أي مستند في السيستم
     * @param  string $logMessage نص السجل
     */
    public static function complete(Model $doc, string $logMessage): void
    {
        $applied = DB::transaction(function () use ($doc) {
            $locked = $doc->newQuery()->whereKey($doc->getKey())->lockForUpdate()->first();

            // اتقفل قبل كده من طلب تاني — منعمليش الأثر مرتين
            if (! $locked || $locked->status === 'approved') {
                return false;
            }

            $doc->forceFill(['status' => 'approved'])->save();
            DocumentEffects::onApproved($doc->refresh());

            return true;
        });

        if ($applied) {
            ActivityLogger::log('completed', $doc, $logMessage);
        }
    }

    /**
     * هل المستند خلص؟ (بديل isApproved اللي كان معتمد على دورة الاعتماد)
     */
    public static function isDone(Model $doc): bool
    {
        return in_array($doc->status ?? '', ['approved', 'closed', 'received'], true);
    }
}
