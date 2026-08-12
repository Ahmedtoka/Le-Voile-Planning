<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── الحوض / الرسالة ───────────────────────────────────────
         | وحدة الشغل الأساسية في السيستم كله.
         | الحوض = مجموعة أتواب اتنسجت واتصبغت مع بعض ⇒ نفس اللون بالضبط
         | ونفس البنشر. القاعدة الذهبية: ممنوع خلط حوضين في قطعة واحدة.
         | رقم الرسالة نمطه: SL30-090826-196-00
        */
        if (!Schema::hasTable('consignments')) {
            Schema::create('consignments', function (Blueprint $t) {
                $t->id();
                $t->string('consignment_no', 60)->unique();   // رقم الرسالة
                $t->date('arrival_date');
                $t->foreignId('purchase_order_id')->nullable();
                $t->foreignId('supplier_id')->nullable();
                $t->foreignId('fabric_type_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->foreignId('warehouse_id')->nullable();

                $t->decimal('total_kg', 15, 3)->default(0);      // إجمالي الوزن
                $t->unsignedInteger('rolls_count')->default(0);  // ع. أتواب
                $t->decimal('total_length_m', 15, 2)->default(0);

                // نتائج الفحص والمعمل — بتتحدث تلقائيًا وبتغذّي محرك الحسابات
                $t->decimal('min_width_cm', 8, 2)->nullable();   // ★ أقل عرض — الماركر بيتبني عليه
                $t->decimal('avg_width_cm', 8, 2)->nullable();
                $t->decimal('max_width_cm', 8, 2)->nullable();
                $t->decimal('avg_gsm', 8, 2)->nullable();        // ★ متوسط البنشر
                $t->decimal('defect_pct', 8, 3)->nullable();
                $t->decimal('shrink_len_pct', 6, 2)->nullable();
                $t->decimal('shrink_width_pct', 6, 2)->nullable();
                $t->boolean('color_match_ok')->nullable();

                $t->decimal('allocated_kg', 15, 3)->default(0);  // المخصص لأوامر شغل
                $t->decimal('remaining_kg', 15, 3)->default(0);  // المتبقي

                $t->enum('status', [
                    'received',     // مستلم
                    'inspecting',   // تحت الفحص
                    'inspected',    // اتفحص
                    'lab_pending',  // مستني المعمل
                    'approved',     // معتمد وجاهز للتشغيل
                    'rejected',     // مرفوض
                    'in_production',// دخل تشغيل
                    'closed',       // اتقفل
                ])->default('received');

                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index(['status', 'color_id']);
            });
        }

        // ── الأتواب ────────────────────────────────────────────────
        if (!Schema::hasTable('fabric_rolls')) {
            Schema::create('fabric_rolls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('consignment_id')->constrained()->cascadeOnDelete();
                $t->string('roll_no', 40);                 // رقم التوب
                $t->string('barcode', 60)->nullable()->unique();
                $t->decimal('length_m', 12, 2)->nullable();
                $t->decimal('width_cm', 8, 2)->nullable();
                $t->decimal('gsm', 8, 2)->nullable();      // وزن البنشر للتوب ده
                $t->decimal('net_kg', 12, 3)->nullable();
                $t->unsignedSmallInteger('defects_count')->default(0);
                $t->decimal('defect_pct', 8, 3)->nullable();
                $t->boolean('is_inspected')->default(false); // الفحص عيّنة مش 100%
                $t->enum('status', ['in_stock','allocated','issued','returned','scrapped'])->default('in_stock');
                $t->foreignId('work_order_id')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->unique(['consignment_id', 'roll_no']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
        Schema::dropIfExists('consignments');
    }
};
