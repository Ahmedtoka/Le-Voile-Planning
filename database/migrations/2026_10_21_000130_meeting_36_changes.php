<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تعديلات ميتينج 2026-10-20 (تسجيل 36) — docs/meetings/2026-10-21-recording-36.md
 *
 *  • طلب الشراء لأكتر من موديل + قفل البند اللي اكتمل
 *  • رفض على مستوى التوب داخل الفحص (بوزنه)
 *  • المعمل: 4 عينات انكماش + علامة الفواصل/اختلاف اللون + تملية الأتواب من الفحص
 *  • الرسالة: تاريخ الإفراج + علامة الفواصل
 *  • المرفوضات: تصنيف السبب (قابل/غير قابل للتفاوض) + قبول بخصم
 *  • طلب الماركر: موديلات بالاختيار + عدد النسخ + ماركر مقترح
 *  • مستند جديد: طلب تحضير (تقسيم الرسالة) بين التخطيط والمخزن
 *  • أمر الشغل: ربط بطلب التحضير
 */
return new class extends Migration
{
    public function up(): void
    {
        // ① طلب الشراء ⇐ أكتر من موديل
        if (!Schema::hasTable('purchase_order_models')) {
            Schema::create('purchase_order_models', function (Blueprint $t) {
                $t->id();
                $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->unique(['purchase_order_id', 'product_model_id'], 'po_model_unique');
            });
        }

        Schema::table('purchase_order_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_order_lines', 'is_closed')) {
                $t->boolean('is_closed')->default(false);   // البند اكتمل استلامه — مقفول لوحده
            }
            if (!Schema::hasColumn('purchase_order_lines', 'closed_at')) {
                $t->timestamp('closed_at')->nullable();
            }
        });

        Schema::table('stock_additions', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_additions', 'tolerance_alerted_at')) {
                $t->timestamp('tolerance_alerted_at')->nullable();   // الإشعار بتجاوز النسبة اتبعت مرة
            }
        });

        // ② الفحص ⇐ رفض على مستوى التوب بوزنه
        Schema::table('inspection_rolls', function (Blueprint $t) {
            if (!Schema::hasColumn('inspection_rolls', 'status')) {
                $t->string('status', 12)->default('ok');       // ok / rejected
            }
            if (!Schema::hasColumn('inspection_rolls', 'net_kg')) {
                $t->decimal('net_kg', 12, 3)->nullable();        // وزن التوب — إجباري للمرفوض
            }
        });

        Schema::table('fabric_inspections', function (Blueprint $t) {
            if (!Schema::hasColumn('fabric_inspections', 'rejected_rolls')) {
                $t->unsignedInteger('rejected_rolls')->default(0);
            }
            if (!Schema::hasColumn('fabric_inspections', 'rejected_kg')) {
                $t->decimal('rejected_kg', 15, 3)->default(0);
            }
        });

        // ③ المعمل ⇐ 4 عينات + الفواصل
        Schema::table('lab_reports', function (Blueprint $t) {
            foreach (['s3_shrink_len_pct', 's3_shrink_width_pct', 's4_shrink_len_pct', 's4_shrink_width_pct'] as $c) {
                if (!Schema::hasColumn('lab_reports', $c)) $t->decimal($c, 6, 2)->nullable();
            }
            if (!Schema::hasColumn('lab_reports', 'has_color_break')) {
                $t->boolean('has_color_break')->default(false);   // فواصل / اختلاف لون داخل الرسالة
            }
            if (!Schema::hasColumn('lab_reports', 'color_break_roll_no')) {
                $t->string('color_break_roll_no', 120)->nullable();
            }
            if (!Schema::hasColumn('lab_reports', 'color_break_note')) {
                $t->string('color_break_note', 191)->nullable();
            }
            if (!Schema::hasColumn('lab_reports', 'gsm_out_of_spec')) {
                $t->boolean('gsm_out_of_spec')->default(false);
            }
        });

        // ④ الرسالة ⇐ تاريخ الإفراج + الفواصل
        Schema::table('consignments', function (Blueprint $t) {
            if (!Schema::hasColumn('consignments', 'released_at')) {
                $t->date('released_at')->nullable();   // تاريخ الإضافة بعد الفحص (الإفراج)
            }
            if (!Schema::hasColumn('consignments', 'has_color_break')) {
                $t->boolean('has_color_break')->default(false);
            }
            if (!Schema::hasColumn('consignments', 'color_break_note')) {
                $t->string('color_break_note', 191)->nullable();
            }
            if (!Schema::hasColumn('consignments', 'reserved_kg')) {
                $t->decimal('reserved_kg', 15, 3)->default(0);   // محجوز لطلبات تحضير لسه ما بقتش أمر شغل
            }
        });

        // ⑤ المرفوضات ⇐ تصنيف السبب + قبول بخصم
        Schema::table('goods_receipt_rejections', function (Blueprint $t) {
            if (!Schema::hasColumn('goods_receipt_rejections', 'reason_category')) {
                $t->string('reason_category', 20)->default('other');   // width / gsm / shrinkage / color / other
            }
            if (!Schema::hasColumn('goods_receipt_rejections', 'discount_pct')) {
                $t->decimal('discount_pct', 6, 2)->nullable();
            }
        });
        // قرار جديد: «اتقبل بخصم من المورد»
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE goods_receipt_rejections MODIFY resolution ENUM('open','accepted','accepted_with_discount','rejected','returned') NOT NULL DEFAULT 'open'");
        }

        // ⑥ طلب الماركر ⇐ موديلات بالاختيار + نسخ + مقترح
        if (!Schema::hasTable('marker_request_models')) {
            Schema::create('marker_request_models', function (Blueprint $t) {
                $t->id();
                $t->foreignId('marker_request_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->unique(['marker_request_id', 'product_model_id'], 'mr_model_unique');
            });
        }
        Schema::table('marker_requests', function (Blueprint $t) {
            if (!Schema::hasColumn('marker_requests', 'copies')) {
                $t->unsignedSmallInteger('copies')->default(2);   // عدد نسخ الماركر المطبوعة
            }
            if (!Schema::hasColumn('marker_requests', 'avg_gsm')) {
                $t->decimal('avg_gsm', 8, 2)->nullable();          // من المعمل — للعلم
            }
            if (!Schema::hasColumn('marker_requests', 'suggested_marker_id')) {
                $t->foreignId('suggested_marker_id')->nullable();   // ماركر قديم السيستم اقترحه
            }
            if (!Schema::hasColumn('marker_requests', 'preparation_request_id')) {
                $t->foreignId('preparation_request_id')->nullable();
            }
            if (!Schema::hasColumn('marker_requests', 'sizes_note')) {
                $t->string('sizes_note', 191)->nullable();
            }
        });

        // ⑦ طلب التحضير (تقسيم الرسالة) — التخطيط ⇄ المخزن
        if (!Schema::hasTable('preparation_requests')) {
            Schema::create('preparation_requests', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->date('doc_date');
                $t->foreignId('consignment_id');
                $t->foreignId('factory_id')->nullable();          // اختياري — ممكن يتغير بعدين
                $t->decimal('requested_qty', 15, 3)->default(0);   // الكمية التقريبية اللي التخطيط طلبها
                $t->boolean('is_remainder')->default(false);       // آخر تقسيمة — تاخد الباقي من غير وزن
                $t->decimal('actual_qty', 15, 3)->nullable();      // الوزن الفعلي اللي المخزن ردّ بيه
                $t->unsignedInteger('actual_rolls')->nullable();
                $t->string('unit', 20)->default('كجم');
                $t->text('models_note')->nullable();               // الموديلات المطلوبة (للمخزن على سبيل العلم)
                $t->string('status', 20)->default('open');         // open / weighed / used / cancelled
                $t->foreignId('work_order_id')->nullable();         // أمر الشغل اللي اتعمل عليها
                $t->foreignId('marker_request_id')->nullable();     // طلب الماركر الموازي
                $t->text('notes')->nullable();
                $t->text('store_note')->nullable();
                $t->foreignId('requested_by')->nullable();
                $t->foreignId('weighed_by')->nullable();
                $t->timestamp('weighed_at')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index(['consignment_id', 'status']);
            });
        }
        if (!Schema::hasTable('preparation_request_models')) {
            Schema::create('preparation_request_models', function (Blueprint $t) {
                $t->id();
                $t->foreignId('preparation_request_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->unique(['preparation_request_id', 'product_model_id'], 'pr_model_unique');
            });
        }

        Schema::table('work_order_fabrics', function (Blueprint $t) {
            if (!Schema::hasColumn('work_order_fabrics', 'preparation_request_id')) {
                $t->foreignId('preparation_request_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_request_models');
        Schema::dropIfExists('preparation_requests');
        Schema::dropIfExists('marker_request_models');
        Schema::dropIfExists('purchase_order_models');
    }
};
