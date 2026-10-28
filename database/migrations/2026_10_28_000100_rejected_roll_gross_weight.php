<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قرار 28 أكتوبر 2026: وزن التوب المرفوض في الفحص بيتكتب «قائم» زي ما طلع على
 * الميزان. السيستم بيخصم منه كيس وماسورة الرسالة ⇒ الصافي (rejected_kg) هو اللي
 * بيتخصم من الرصيد، والقائم (rejected_gross_kg) هو اللي بيتخصم من طلب الشراء
 * وبيتطالب بيه المورد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fabric_inspections', function (Blueprint $t) {
            if (!Schema::hasColumn('fabric_inspections', 'rejected_gross_kg')) {
                $t->decimal('rejected_gross_kg', 15, 3)->nullable()->after('rejected_kg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fabric_inspections', function (Blueprint $t) {
            if (Schema::hasColumn('fabric_inspections', 'rejected_gross_kg')) $t->dropColumn('rejected_gross_kg');
        });
    }
};
