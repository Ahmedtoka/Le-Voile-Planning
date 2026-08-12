<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── أمر الشغل ─────────────────────────────────────────────
         | يربط: حوض واحد + ماركر واحد + مصنع واحد.
         | الأرقام المحسوبة هنا "متوقّعة" مبنية على متوسطات الفحص —
         | الفعلي بيظهر في بيان القص، والفرق المقبول 2-4%.
        */
        if (!Schema::hasTable('work_orders')) {
            Schema::create('work_orders', function (Blueprint $t) {
                $t->id();
                $t->string('wo_no', 40)->unique();            // رقم أمر الشغل
                $t->date('wo_date');
                $t->foreignId('consignment_id')->nullable();   // الحوض
                $t->foreignId('marker_id')->nullable();        // الماركر
                $t->foreignId('factory_id')->nullable();       // المصنع
                $t->date('due_date')->nullable();

                // ── مدخلات الحسبة (مصوّرة لحظة الإنشاء عشان التقارير ما تتغيّرش) ──
                $t->decimal('input_min_width_cm', 8, 2)->nullable();
                $t->decimal('input_avg_gsm', 8, 2)->nullable();
                $t->decimal('input_spread_length_m', 10, 3)->nullable();
                $t->unsignedInteger('input_pieces_per_spread')->nullable();
                $t->decimal('allocated_kg', 15, 3)->default(0);   // الكيلوهات المخصصة من الحوض
                $t->unsignedInteger('allocated_rolls')->default(0);

                // ── مخرجات محرك الحسابات ──
                $t->decimal('ply_weight_kg', 12, 4)->nullable();     // وزن الرِقّة
                $t->decimal('kg_per_piece', 12, 5)->nullable();      // استهلاك القطعة
                $t->unsignedInteger('expected_plies')->nullable();   // عدد الرِقّات المتوقع
                $t->unsignedInteger('expected_pieces')->nullable();  // القطع المتوقعة

                // ── الفعلي ──
                $t->decimal('actual_spread_length_m', 10, 3)->nullable(); // اللي المصنع فرش عليه فعلًا
                $t->unsignedInteger('actual_plies')->nullable();
                $t->unsignedInteger('cut_pieces')->default(0);        // المقصوص فعليًا
                $t->unsignedInteger('received_pieces')->default(0);   // المستلم تام
                $t->decimal('variance_pct', 8, 3)->nullable();        // الانحراف
                $t->enum('variance_flag', ['ok','warn','danger'])->nullable();
                $t->text('variance_reason')->nullable();              // إجباري لو danger

                $t->enum('status', [
                    'draft','pending','approved','rejected','sent_to_factory',
                    'cutting','cut_declared','in_production',
                    'partially_received','closed','cancelled',
                ])->default('draft');

                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index(['status', 'factory_id']);
            });
        }

        // الموديلات/المقاسات المخططة في أمر الشغل
        if (!Schema::hasTable('work_order_lines')) {
            Schema::create('work_order_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id');
                $t->foreignId('size_id')->nullable();
                $t->unsignedInteger('qty_per_spread')->default(1);
                $t->unsignedInteger('planned_qty')->default(0);   // المخطط
                $t->unsignedInteger('cut_qty')->default(0);       // المقصوص
                $t->unsignedInteger('received_qty')->default(0);  // المستلم
                $t->unsignedInteger('remaining_qty')->default(0); // المتبقي = المقصوص - المستلم
                $t->timestamps();
                $t->index(['work_order_id', 'product_model_id']);
            });
        }

        // ── بيان القص الوارد من المصنع ─────────────────────────────
        if (!Schema::hasTable('cut_declarations')) {
            Schema::create('cut_declarations', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->date('doc_date');
                $t->foreignId('work_order_id')->nullable();
                $t->foreignId('factory_id')->nullable();
                $t->decimal('actual_spread_length_m', 10, 3)->nullable(); // ★ لو زاد بياكل رِقّات
                $t->unsignedInteger('actual_plies')->nullable();
                $t->decimal('used_kg', 15, 3)->default(0);
                $t->unsignedInteger('total_pieces')->default(0);
                $t->decimal('actual_kg_per_piece', 12, 5)->nullable();
                $t->decimal('variance_pct', 8, 3)->nullable();
                $t->enum('variance_flag', ['ok','warn','danger'])->nullable();
                $t->text('variance_reason')->nullable();
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('cut_declaration_lines')) {
            Schema::create('cut_declaration_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cut_declaration_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id');
                $t->foreignId('size_id')->nullable();
                $t->unsignedInteger('qty')->default(0);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── استلامات المنتج التام (جزئية ومتكررة) ──────────────────
        if (!Schema::hasTable('production_receipts')) {
            Schema::create('production_receipts', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->date('doc_date');
                $t->foreignId('work_order_id')->nullable();
                $t->foreignId('factory_id')->nullable();
                $t->foreignId('warehouse_id')->nullable();
                $t->unsignedInteger('total_pieces')->default(0);
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('production_receipt_lines')) {
            Schema::create('production_receipt_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('production_receipt_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id');
                $t->foreignId('size_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->unsignedInteger('qty')->default(0);
                $t->unsignedInteger('rejected_qty')->default(0);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── صرف الإكسسوارات لأمر الشغل (انفجار الـ BOM) ────────────
        if (!Schema::hasTable('accessory_requirements')) {
            Schema::create('accessory_requirements', function (Blueprint $t) {
                $t->id();
                $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $t->foreignId('accessory_id');
                $t->decimal('required_qty', 15, 3)->default(0);  // المطلوب حسب الـ BOM
                $t->decimal('issued_qty', 15, 3)->default(0);    // المنصرف
                $t->decimal('shortage_qty', 15, 3)->default(0);  // الناقص
                $t->timestamps();
                $t->unique(['work_order_id', 'accessory_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['accessory_requirements','production_receipt_lines','production_receipts',
                  'cut_declaration_lines','cut_declarations','work_order_lines','work_orders'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
