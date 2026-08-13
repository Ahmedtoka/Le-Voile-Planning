<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── طلب الشراء ─────────────────────────────────────────────
        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $t) {
                $t->id();
                $t->string('po_no', 40)->unique();            // رقم أمر الشراء
                $t->date('po_date');                           // التاريخ

                /* ── المراحل ──────────────────────────────────────
                 | المستند واحد زي الورقة بالظبط، بس بيمر بثلاث أيادي:
                 |   planning   التخطيط بيكتب الأصناف والكميات ونسبة الزيادة
                 |   purchasing المشتريات بتحدد المورد والأسعار وتاريخ التوريد
                 |   finance    الحسابات بتعلم بالمستحق المتوقع للمورد
                 |   approval   دورة الاعتماد
                 | كل مرحلة ليها صلاحية مختلفة وبتقفل اللي قبلها عن التعديل.
                */
                $t->enum('stage', [
                    'planning', 'purchasing', 'finance', 'approval',
                    'approved', 'receiving', 'closed', 'cancelled',
                ])->default('planning');

                $t->foreignId('requested_by')->nullable();   // المخطط
                $t->timestamp('requested_at')->nullable();
                $t->foreignId('sourced_by')->nullable();     // المشتريات
                $t->timestamp('sourced_at')->nullable();
                $t->foreignId('finance_by')->nullable();     // الحسابات
                $t->timestamp('finance_at')->nullable();
                $t->text('finance_note')->nullable();
                $t->text('planning_note')->nullable();       // سبب الطلب من التخطيط
                $t->foreignId('supplier_id')->nullable();
                $t->foreignId('warehouse_id')->nullable();     // مكان التسليم
                $t->foreignId('employee_id')->nullable();      // اسم الموظف (إدارة المشتريات)
                $t->date('delivery_date')->nullable();         // تاريخ التوريد
                $t->string('delivery_place')->nullable();      // مكان التسليم (نص حر: العبور)
                $t->string('payment_method')->nullable();      // طريقة الدفع

                $t->decimal('subtotal', 15, 2)->default(0);    // الإجمالي
                $t->decimal('discount_pct', 6, 2)->default(0); // نسبة الخصم
                $t->decimal('discount_value', 15, 2)->default(0);
                $t->decimal('tax_pct', 6, 2)->default(0);      // الضريبة
                $t->decimal('tax_value', 15, 2)->default(0);
                $t->decimal('total', 15, 2)->default(0);       // إجمالي القيمة بعد الضريبة
                $t->decimal('total_qty', 15, 3)->default(0);   // إجمالي الكمية

                $t->enum('status', ['draft','pending','approved','rejected','partially_received','received','closed','cancelled'])
                  ->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index('status');
                $t->index('stage');
            });
        }

        if (!Schema::hasTable('purchase_order_lines')) {
            Schema::create('purchase_order_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $t->unsignedSmallInteger('line_no')->default(1);   // م
                $t->foreignId('color_id')->nullable();             // كود اللون
                $t->foreignId('fabric_type_id')->nullable();       // اسم الصنف
                $t->string('item_name')->nullable();               // نص حر لو مش من الكتالوج
                $t->decimal('qty', 15, 3);                          // الكمية
                $t->string('unit', 20)->default('طن');              // الوحدة
                $t->decimal('unit_price', 15, 2)->default(0);      // سعر الوحدة
                $t->decimal('line_total', 15, 2)->default(0);      // الإجمالي
                // نسبة الزيادة المسموح بها % — المورد ممكن يورّد فوق/تحت الكمية في حدودها
                $t->decimal('tolerance_pct', 6, 2)->default(5);
                $t->decimal('received_qty', 15, 3)->default(0);    // المستلم فعليًا
                $t->text('notes')->nullable();                     // ملاحظات (وزن المقطع من 190 إلى 210 جرام)
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
