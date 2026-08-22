<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فلو الجوب أوردر — ميتينج 17/8 + شيت Automation Job Order.
 *
 * 1) الموديلات: كود F.P وباركود وصورة — زي ما بيطلعوا في ورقة أمر الشغل.
 * 2) توزيع الاستهلاك على الموديلات المشتركة في نفس الفرشة بالمتوسطات.
 * 3) إذن الإضافة: رقم إذن المورد (اختياري) + انحراف اللون (تسكين/طلب جديد).
 * 4) استلام الحاويات: بدون دورة فحص — الإضافة نفسها هي النهائية.
 *
 * كلها إضافات idempotent — تتشغل بأمان على اللايف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_models', function (Blueprint $t) {
            if (!Schema::hasColumn('product_models', 'fp_code'))   $t->string('fp_code', 20)->nullable();   // O7 / E2 ...
            if (!Schema::hasColumn('product_models', 'barcode'))   $t->string('barcode', 80)->nullable();
            if (!Schema::hasColumn('product_models', 'photo_url')) $t->string('photo_url', 500)->nullable(); // صورة الموديل في أمر الشغل
        });

        Schema::table('work_order_lines', function (Blueprint $t) {
            // سناب شوت متوسط الاستهلاك وقت الإنشاء + نصيب الموديل من الاستهلاك الفعلي
            if (!Schema::hasColumn('work_order_lines', 'avg_consumption_kg'))    $t->decimal('avg_consumption_kg', 12, 5)->nullable();
            if (!Schema::hasColumn('work_order_lines', 'consumption_per_piece')) $t->decimal('consumption_per_piece', 12, 5)->nullable();
            if (!Schema::hasColumn('work_order_lines', 'planned_kg'))            $t->decimal('planned_kg', 15, 3)->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_orders', 'product_model_id')) $t->foreignId('product_model_id')->nullable(); // الطلب لموديل معين
            if (!Schema::hasColumn('purchase_orders', 'inspection_exempt')) $t->boolean('inspection_exempt')->default(false); // حاويات/مستورد — من غير فحص
        });

        Schema::table('suppliers', function (Blueprint $t) {
            if (!Schema::hasColumn('suppliers', 'supplier_type')) $t->string('supplier_type', 20)->default('local'); // مصري/مستورد/الاتنين
        });

        Schema::table('stock_additions', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_additions', 'supplier_doc_no')) $t->string('supplier_doc_no', 60)->nullable(); // رقم إذن المورد — للمطابقة معاه بس
            if (!Schema::hasColumn('stock_additions', 'receipt_type'))    $t->string('receipt_type', 20)->default('normal'); // normal / container
        });

        Schema::table('stock_addition_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_addition_lines', 'po_color_id'))  $t->foreignId('po_color_id')->nullable();   // اللون المطلوب أصلًا في الـPO
            if (!Schema::hasColumn('stock_addition_lines', 'color_action')) $t->string('color_action', 20)->nullable(); // substitute / new_po
        });
    }

    public function down(): void
    {
        // إضافات فقط — مفيش داعي للرجوع، والحذف على اللايف خطر.
    }
};
