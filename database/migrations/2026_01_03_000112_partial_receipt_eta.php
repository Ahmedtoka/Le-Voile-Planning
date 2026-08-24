<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الاستلام الجزئي: «طالب 50 ووصل 30 — الباقي 20 هيوصل إمتى؟»
 *
 * الرقم ده كان بيضيع في التليفونات. دلوقتي بيتسجّل على الإذن نفسه
 * وبيتنقل للطلب عشان يفضل ظاهر في طابور الاستلام وطابور الفحص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_additions', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_additions', 'remainder_eta'))   $t->date('remainder_eta')->nullable();
            if (!Schema::hasColumn('stock_additions', 'remainder_note'))  $t->string('remainder_note', 191)->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $t) {
            // تاريخ التوريد الأصلي بيفضل زي ما هو — ده موعد الباقي بعد آخر استلام
            if (!Schema::hasColumn('purchase_orders', 'remainder_eta')) $t->date('remainder_eta')->nullable();
        });
    }

    public function down(): void
    {
        // إضافات فقط.
    }
};
