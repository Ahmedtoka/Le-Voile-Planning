<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ميتينج 37 (2026-10-22):
 *   • الصافي والقائم: الكيس والماسورة على إذن الإضافة والصرف ووزن التحضير
 *   • الوحدة كيلو / متر على بند الطلب وبتمشي على الرسالة والفحص
 *   • التسكين الجزئي: طلب الشراء يتسعّر على أكتر من مورد وأكتر من مرة
 *   • خامات الموديل (BOM) عشان طلب الشراء يطلّعها لوحده
 *   • عرض التشغيل (تأمين العروض) وطلبات التحضير على أكتر من رسالة
 *   • إعدادات قابلة للتعديل من الشاشة (نسبة العيّنة، هوامش الأمان)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── خامات الموديل: الموديل بيتعمل من أنهي خامات وبأي استهلاك للقطعة ──
        if (!Schema::hasTable('model_fabrics')) {
            Schema::create('model_fabrics', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->foreignId('fabric_type_id')->constrained()->cascadeOnDelete();
                $t->foreignId('color_id')->nullable();                 // null = أي لون
                $t->decimal('consumption_per_piece', 12, 4)->nullable(); // كجم أو متر لكل قطعة
                $t->string('unit', 10)->default('كجم');
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['product_model_id', 'fabric_type_id']);
            });
        }

        // ── التسكين الجزئي: كل تسكينة = مورد + تاريخ + طريقة دفع + بنود بكمياتها وأسعارها ──
        if (!Schema::hasTable('po_sourcings')) {
            Schema::create('po_sourcings', function (Blueprint $t) {
                $t->id();
                $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $t->unsignedSmallInteger('seq')->default(1);
                $t->foreignId('supplier_id')->constrained();
                $t->foreignId('warehouse_id')->nullable();
                $t->date('delivery_date')->nullable();
                $t->string('payment_method', 40)->nullable();
                $t->decimal('discount_pct', 5, 2)->default(0);
                $t->decimal('tax_pct', 5, 2)->default(0);
                $t->decimal('subtotal', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->text('notes')->nullable();
                $t->foreignId('sourced_by')->nullable();
                $t->timestamp('sourced_at')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('po_sourcing_lines')) {
            Schema::create('po_sourcing_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('po_sourcing_id')->constrained('po_sourcings')->cascadeOnDelete();
                $t->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
                $t->decimal('qty', 14, 3);
                $t->string('unit', 10)->default('كجم');
                $t->decimal('unit_price', 14, 4)->default(0);
                $t->decimal('line_total', 14, 2)->default(0);
                $t->decimal('received_qty', 14, 3)->default(0);
                $t->timestamps();
            });
        }
        Schema::table('purchase_order_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_order_lines', 'sourced_qty'))   $t->decimal('sourced_qty', 14, 3)->default(0);
            if (!Schema::hasColumn('purchase_order_lines', 'target_pieces')) $t->unsignedInteger('target_pieces')->nullable();
        });
        Schema::table('purchase_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_orders', 'target_pieces')) $t->unsignedInteger('target_pieces')->nullable();
        });
        Schema::table('stock_additions', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_additions', 'po_sourcing_id')) $t->foreignId('po_sourcing_id')->nullable();
        });

        // ── الصافي والقائم على إذن الإضافة ──
        Schema::table('stock_addition_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_addition_lines', 'fabric_form'))        $t->string('fabric_form', 10)->nullable();   // open / closed
            if (!Schema::hasColumn('stock_addition_lines', 'bag_g'))              $t->decimal('bag_g', 8, 2)->nullable();
            if (!Schema::hasColumn('stock_addition_lines', 'tube_g'))             $t->decimal('tube_g', 8, 2)->nullable();
            if (!Schema::hasColumn('stock_addition_lines', 'gross_qty'))          $t->decimal('gross_qty', 14, 3)->nullable();
            if (!Schema::hasColumn('stock_addition_lines', 'packaging_kg'))       $t->decimal('packaging_kg', 14, 3)->default(0);
            if (!Schema::hasColumn('stock_addition_lines', 'po_sourcing_line_id')) $t->foreignId('po_sourcing_line_id')->nullable();
        });

        // ── الرسالة: الوحدة + الكيس والماسورة + القائم + عرض التشغيل ──
        Schema::table('consignments', function (Blueprint $t) {
            if (!Schema::hasColumn('consignments', 'unit'))            $t->string('unit', 10)->default('كجم');
            if (!Schema::hasColumn('consignments', 'fabric_form'))     $t->string('fabric_form', 10)->nullable();
            if (!Schema::hasColumn('consignments', 'bag_g'))           $t->decimal('bag_g', 8, 2)->nullable();
            if (!Schema::hasColumn('consignments', 'tube_g'))          $t->decimal('tube_g', 8, 2)->nullable();
            if (!Schema::hasColumn('consignments', 'gross_kg'))        $t->decimal('gross_kg', 14, 3)->nullable();
            if (!Schema::hasColumn('consignments', 'packaging_kg'))    $t->decimal('packaging_kg', 14, 3)->default(0);
            if (!Schema::hasColumn('consignments', 'usable_width_cm')) $t->decimal('usable_width_cm', 8, 2)->nullable();
        });

        // ── الصرف ووزن التحضير بالقائم والسيستم يطلّع الصافي ──
        Schema::table('material_issue_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('material_issue_lines', 'gross_qty'))    $t->decimal('gross_qty', 14, 3)->nullable();
            if (!Schema::hasColumn('material_issue_lines', 'packaging_kg')) $t->decimal('packaging_kg', 14, 3)->default(0);
        });
        Schema::table('preparation_requests', function (Blueprint $t) {
            if (!Schema::hasColumn('preparation_requests', 'actual_gross_qty')) $t->decimal('actual_gross_qty', 14, 3)->nullable();
            if (!Schema::hasColumn('preparation_requests', 'packaging_kg'))     $t->decimal('packaging_kg', 14, 3)->default(0);
            if (!Schema::hasColumn('preparation_requests', 'batch_key'))        $t->string('batch_key', 40)->nullable()->index();
        });

        // ── الفحص بالمتر: عدد الأمتار المكتوب على التوب ──
        Schema::table('inspection_rolls', function (Blueprint $t) {
            if (!Schema::hasColumn('inspection_rolls', 'labeled_length_m')) $t->decimal('labeled_length_m', 10, 2)->nullable();
        });
        Schema::table('fabric_inspections', function (Blueprint $t) {
            if (!Schema::hasColumn('fabric_inspections', 'usable_width_cm')) $t->decimal('usable_width_cm', 8, 2)->nullable();
        });

        // ── أمر الشغل: الاستهلاك اتحسب على أنهي عرض ──
        Schema::table('work_order_fabrics', function (Blueprint $t) {
            if (!Schema::hasColumn('work_order_fabrics', 'width_basis')) $t->string('width_basis', 10)->default('usable'); // usable / full
        });

        // ── إعدادات قابلة للتعديل من الشاشة ──
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $t) {
                $t->string('key', 80)->primary();
                $t->string('value', 191)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('po_sourcing_lines');
        Schema::dropIfExists('po_sourcings');
        Schema::dropIfExists('model_fabrics');
    }
};
