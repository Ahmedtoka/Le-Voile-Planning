<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* المرفوض من المصنع في استلام الإنتاج: القطع رجعت بس معيبة.
       بتتحسب ضمن «المستلم» علشان المتبقي على المصنع يوصل صفر والأمر يتقفل،
       وبتتسجل لوحدها هنا علشان تظهر في التقارير ومتدخلش مخزون التام. */
    public function up(): void
    {
        if (!Schema::hasColumn('work_order_lines', 'rejected_qty')) {
            Schema::table('work_order_lines', fn (Blueprint $t) =>
                $t->unsignedInteger('rejected_qty')->default(0)->after('received_qty'));
        }
        if (!Schema::hasColumn('work_orders', 'rejected_pieces')) {
            Schema::table('work_orders', fn (Blueprint $t) =>
                $t->unsignedInteger('rejected_pieces')->default(0)->after('received_pieces'));
        }
    }

    public function down(): void
    {
        Schema::table('work_order_lines', fn (Blueprint $t) => $t->dropColumn('rejected_qty'));
        Schema::table('work_orders', fn (Blueprint $t) => $t->dropColumn('rejected_pieces'));
    }
};
