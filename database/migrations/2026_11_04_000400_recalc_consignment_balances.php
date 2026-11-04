<?php

use Illuminate\Database\Migrations\Migration;

/**
 * إعادة حساب رصيد كل الرسايل بعد تعديل قاعدة التخصيص:
 * خامة أمر شغل مفتوح بتحجز الأكبر من (المخطط، المنصرف فعلًا) — الصرف فوق المخطط
 * خرج من الرسالة، فمينفعش يفضل باين متاح. الرصيد متخزن، فبيتعاد حسابه مرة هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        \App\Models\Consignment::query()->orderBy('id')->each(fn ($c) => $c->recalcRemaining());
    }

    public function down(): void {}
};
