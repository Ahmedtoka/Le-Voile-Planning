<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── إذن استلام خام ─────────────────────────────────────────
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();          // رقم الإذن في السيستم
                $t->string('paper_serial', 40)->nullable();  // المسلسل المطبوع على الدفتر (1001546)
                $t->date('doc_date');
                $t->foreignId('warehouse_id')->nullable();   // مخزن العبور
                $t->foreignId('supplier_id')->nullable();    // وارد من
                $t->foreignId('purchase_order_id')->nullable(); // أمر المشتريات (196)
                $t->foreignId('consignment_id')->nullable();    // الرسالة (اتعملت في إذن الإضافة)
                $t->foreignId('stock_addition_id')->nullable(); // إذن الإضافة الأصلي
                $t->foreignId('fabric_inspection_id')->nullable(); // تقرير الفحص اللي بنفرج بناءً عليه
                $t->string('supplier_rep')->nullable();      // مندوب المورد
                $t->decimal('total_qty', 15, 3)->default(0);
                $t->unsignedInteger('total_rolls')->default(0);
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('goods_receipt_lines')) {
            Schema::create('goods_receipt_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
                $t->string('item_code', 40)->nullable();      // الكود
                $t->foreignId('fabric_type_id')->nullable();  // الصنف
                $t->foreignId('color_id')->nullable();        // اللون
                $t->string('unit', 20)->default('كجم');       // الوحدة
                $t->decimal('width_cm', 8, 2)->nullable();    // العرض
                $t->unsignedInteger('rolls_count')->default(0); // ع. أتواب
                $t->decimal('qty', 15, 3)->default(0);        // الكمية
                $t->string('consignment_no', 60)->nullable(); // رقم الرسالة
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── إذن إضافة ──────────────────────────────────────────────
        if (!Schema::hasTable('stock_additions')) {
            Schema::create('stock_additions', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->string('paper_serial', 40)->nullable();   // 41456
                $t->date('doc_date');
                $t->foreignId('supplier_id')->nullable();     // اسم المورد
                $t->foreignId('warehouse_id')->nullable();    // إسم/كود المخزن (043)
                $t->foreignId('consignment_id')->nullable();
                $t->string('consignment_no', 60)->nullable(); // BUPL-090826-043-00
                $t->foreignId('purchase_order_id')->nullable();
                $t->unsignedInteger('total_rolls')->default(0);
                $t->decimal('total_qty', 15, 3)->default(0);
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('stock_addition_lines')) {
            Schema::create('stock_addition_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('stock_addition_id')->constrained()->cascadeOnDelete();
                $t->string('item_code', 40)->nullable();      // كود الصنف
                $t->string('item_name')->nullable();          // اسم الصنف
                $t->foreignId('fabric_type_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->foreignId('accessory_id')->nullable();
                $t->unsignedInteger('rolls_count')->default(0);  // ع. أتواب — الفحص هيجرد عليه
                $t->decimal('qty', 15, 3)->default(0);
                $t->string('unit', 20)->default('كجم');
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── حركة المخزون الموحّدة (كل زيادة/نقص بتعدّي من هنا) ─────
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $t) {
                $t->id();
                $t->date('moved_at');
                $t->foreignId('warehouse_id')->nullable();
                $t->enum('item_type', ['fabric', 'accessory', 'finished'])->default('fabric');
                $t->foreignId('fabric_type_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->foreignId('accessory_id')->nullable();
                $t->foreignId('product_model_id')->nullable();
                $t->foreignId('size_id')->nullable();
                $t->foreignId('consignment_id')->nullable();
                $t->enum('direction', ['in', 'out'])->default('in');
                // hold = دخل بإذن إضافة وتحت الفحص · released = اتفرج عنه بإذن الاستلام
                $t->enum('quality_state', ['hold', 'released', 'rejected', 'n/a'])->default('n/a');
                $t->decimal('qty', 15, 3)->default(0);
                $t->string('unit', 20)->default('كجم');
                $t->string('source_type')->nullable();   // المستند المصدر
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('reference')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index(['item_type', 'warehouse_id', 'moved_at']);
                $t->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['stock_movements','stock_addition_lines','stock_additions',
                  'goods_receipt_lines','goods_receipts'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
