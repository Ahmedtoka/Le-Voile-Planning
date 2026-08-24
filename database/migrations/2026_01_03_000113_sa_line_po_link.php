<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط سطر إذن الإضافة بسطر طلب الشراء نفسه.
 *
 * قبل كده كنا بنطابق بالخامة واللون — وده بيقع لما اللون يتبدل أو
 * الطلب يبقى فيه سطرين بنفس الخامة. الربط المباشر بيخلي:
 * المطلوب/المستلم/الباقي أرقام مضمونة، وقرار انحراف اللون بيتسجل
 * على السطر الصح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_addition_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_addition_lines', 'po_line_id')) {
                $t->foreignId('po_line_id')->nullable();   // سطر طلب الشراء الأصلي
            }
        });
    }

    public function down(): void
    {
        // إضافات فقط.
    }
};
