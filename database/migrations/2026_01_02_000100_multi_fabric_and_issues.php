<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ══════════════════════════════════════════════════════════════════
 *  الواقع اللي طلع من ورق المصنع
 * ══════════════════════════════════════════════════════════════════
 *
 * ① أمر الشغل بيتعمل من **أكتر من خامة** مع بعض (طرحة تل + بونيه مياي).
 *    كل خامة ليها رسالتها ولونها وطول فرشتها وعرضها وعدد رقاتها
 *    وعدد قطعها في الفرشة واستهلاكها — وحسبتها مستقلة تمامًا.
 *    الخامة اللي بتدي أقل قطع هي اللي بتحكم الإنتاج.
 *
 * ② فيه مستند **إذن صرف خام** بيصرف الخامات من المخزن للمصنع مقابل
 *    أمر الشغل، وممكن يغطي أكتر من أمر في ورقة واحدة.
 *
 * ③ إذن الاستلام بيسجّل **رفض جزئي** (أتواب بوزنها وسببها) و**تعليق ألوان**
 *    لحين رد التخطيط والمشتريات — مش قبول أو رفض للشحنة كلها.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->workOrderFabrics();
        $this->workOrderSheet();
        $this->materialIssues();
        $this->rejections();
    }

    /* ── ① خامات أمر الشغل ─────────────────────────────────────── */
    private function workOrderFabrics(): void
    {
        if (Schema::hasTable('work_order_fabrics')) return;

        Schema::create('work_order_fabrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('line_no')->default(1);

            $t->foreignId('consignment_id')->nullable();   // الرسالة
            $t->foreignId('fabric_type_id')->nullable();
            $t->foreignId('color_id')->nullable();
            $t->foreignId('marker_id')->nullable();
            $t->string('role', 20)->default('main');       // main = الخامة الرئيسية

            /* طريقة الحساب:
               weight = الخامة بتتحسب بالوزن (مياي/سنجل) ⇒ محتاجة بنشر وعرض
               length = الخامة بتتحسب بالطول (تل مستورد) ⇒ الطول وحده كفاية */
            $t->enum('calc_mode', ['weight', 'length'])->default('weight');
            $t->string('unit', 20)->default('كجم');

            $t->decimal('issued_qty', 15, 3)->default(0);      // المنصرف من المخزن
            $t->decimal('planned_qty', 15, 3)->default(0);     // المخطط صرفه

            // مدخلات الماركر — بتتصوّر هنا عشان التقارير ما تتغيّرش بعدين
            $t->decimal('spread_length_m', 10, 3)->nullable();       // طول الفرشة
            $t->decimal('spread_length_safe_m', 10, 3)->nullable();  // الطول بالأمان ← ده اللي بيتحسب بيه
            $t->decimal('fabric_width_m', 8, 3)->nullable();         // عرض القماش بالمتر
            $t->decimal('gsm_kg_m2', 10, 4)->nullable();             // البنشر كجم/م²
            $t->unsignedInteger('pieces_per_spread')->nullable();    // القطع في الفرشة

            // ── حسبة السيستم ──
            $t->decimal('ply_weight_kg', 12, 4)->nullable();        // وزن الراق
            $t->decimal('consumption_per_piece', 12, 5)->nullable();// استهلاك القطعة (بوحدة الخامة)
            $t->unsignedInteger('calc_plies')->nullable();          // الرقات النظرية
            $t->unsignedInteger('calc_pieces')->nullable();         // القص النظري

            /* ── اللي بيتكتب على ورقة المصنع ──
             | متحقق من ورقة KB106: المعادلة بتدي 30 رقة للمياي و97 للتل،
             | والمخطط كتب 29 و98. يعني الرقم اللي بيروح للمصنع بحكم بشري
             | (هامش أمان، حالة القماش، خبرة). السيستم بيحسب ويعرض،
             | والمخطط بيعتمد رقمه، والفرق بيفضل ظاهر.
            */
            $t->unsignedInteger('plies')->nullable();               // عدد الرقات المعتمد
            $t->unsignedInteger('expected_pieces')->nullable();     // القص المتوقع المعتمد
            $t->boolean('is_governing')->default(false);            // هي اللي بتحكم الإنتاج؟

            $t->decimal('actual_spread_length_m', 10, 3)->nullable();
            $t->unsignedInteger('actual_plies')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['work_order_id', 'consignment_id']);
        });
    }

    /* ── بيانات ورقة أمر الشغل اللي بتروح للمصنع ────────────────── */
    private function workOrderSheet(): void
    {
        if (!Schema::hasTable('work_orders')) return;

        $add = [
            'product_code'    => fn ($t) => $t->string('product_code', 60)->nullable(),
            'qb_code'         => fn ($t) => $t->string('qb_code', 60)->nullable(),
            'product_title'   => fn ($t) => $t->string('product_title')->nullable(),
            'marker_copies'   => fn ($t) => $t->unsignedSmallInteger('marker_copies')->default(1),
            'receive_date'    => fn ($t) => $t->date('receive_date')->nullable(),
            'planner_id'      => fn ($t) => $t->unsignedBigInteger('planner_id')->nullable(),
            'cutting_notes'   => fn ($t) => $t->text('cutting_notes')->nullable(),
            'barcode'         => fn ($t) => $t->string('barcode', 80)->nullable(),
            'governing_qty'   => fn ($t) => $t->unsignedInteger('governing_qty')->nullable(),
            'approved_qty'    => fn ($t) => $t->unsignedInteger('approved_qty')->nullable(),
            'approved_qty_reason' => fn ($t) => $t->text('approved_qty_reason')->nullable(),
        ];

        $missing = array_filter($add, fn ($cb, $c) => !Schema::hasColumn('work_orders', $c), ARRAY_FILTER_USE_BOTH);
        if (!$missing) return;

        Schema::table('work_orders', function (Blueprint $t) use ($missing) {
            foreach ($missing as $cb) $cb($t);
        });
    }

    /* ── ② إذن صرف خام ─────────────────────────────────────────── */
    private function materialIssues(): void
    {
        if (!Schema::hasTable('material_issues')) {
            Schema::create('material_issues', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->string('paper_serial', 40)->nullable();   // 1303774
                $t->date('doc_date');
                $t->foreignId('warehouse_id')->nullable();    // مخزن العبور
                $t->foreignId('factory_id')->nullable();      // منصرف إلى
                $t->string('issued_to')->nullable();          // الاسم زي ما مكتوب
                $t->string('receiver_name')->nullable();      // المستلم
                $t->decimal('total_qty', 15, 3)->default(0);
                $t->unsignedInteger('total_rolls')->default(0);
                $t->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->index('status');
            });
        }

        if (!Schema::hasTable('material_issue_lines')) {
            Schema::create('material_issue_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('material_issue_id')->constrained()->cascadeOnDelete();
                $t->foreignId('work_order_id')->nullable();        // ورقة واحدة تغطي أكتر من أمر
                $t->foreignId('work_order_fabric_id')->nullable(); // الخامة بعينها في الأمر
                $t->foreignId('consignment_id')->nullable();
                $t->foreignId('fabric_type_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->string('item_code', 40)->nullable();           // 14810091
                $t->string('unit', 20)->default('كجم');
                $t->decimal('width_cm', 8, 2)->nullable();
                $t->unsignedInteger('rolls_count')->default(0);    // ع. أتواب
                $t->decimal('qty', 15, 3)->default(0);
                $t->string('consignment_no', 60)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['material_issue_id', 'work_order_id']);
            });
        }
    }

    /* ── ③ الرفض الجزئي وتعليق الألوان ─────────────────────────── */
    private function rejections(): void
    {
        if (Schema::hasTable('goods_receipt_rejections')) return;

        Schema::create('goods_receipt_rejections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goods_receipt_id')->nullable();
            $t->foreignId('fabric_inspection_id')->nullable();
            $t->foreignId('consignment_id')->nullable();
            $t->foreignId('color_id')->nullable();
            $t->string('color_code', 40)->nullable();    // زي ما مكتوب على الورقة
            $t->string('lot_label')->nullable();          // «الحوض الأخضر» / «الحوض البمبي»

            /* rejected = مرفوض نهائي من الجودة
               on_hold  = معلّق لحين رد التخطيط والمشتريات (لون غير مطابق) */
            $t->enum('kind', ['rejected', 'on_hold'])->default('rejected');

            $t->unsignedInteger('rolls_count')->default(0);
            $t->decimal('qty', 15, 3)->default(0);
            $t->string('unit', 20)->default('كجم');
            $t->string('party', 40)->default('quality');  // quality / planning / purchasing
            $t->text('reason')->nullable();

            // قفل التعليق
            $t->enum('resolution', ['open', 'accepted', 'rejected', 'returned'])->default('open');
            $t->text('resolution_note')->nullable();
            $t->foreignId('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();

            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->index(['consignment_id', 'kind', 'resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_rejections');
        Schema::dropIfExists('material_issue_lines');
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('work_order_fabrics');
    }
};
