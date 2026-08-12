<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── لقطات المبيعات ────────────────────────────────────────
         | مشكلة معروفة: QuickBooks بيتعدّل فيه طول الشهر. عشان كده كل
         | سحب بيتخزّن كلقطة برقم مراجعة، ومبيعات الشهر ما تُعتمدش
         | (locked) غير يوم 5 من الشهر التالي.
        */
        if (!Schema::hasTable('sales_snapshots')) {
            Schema::create('sales_snapshots', function (Blueprint $t) {
                $t->id();
                $t->date('pulled_at');                       // تاريخ السحب
                $t->date('period_from');
                $t->date('period_to');
                $t->foreignId('product_model_id')->nullable();
                $t->foreignId('color_id')->nullable();       // غالبًا null — المصدر مش بيدي لون
                $t->decimal('qty_pcs', 15, 2)->default(0);   // بالقطعة (بعد التحويل من دستة)
                $t->decimal('raw_qty', 15, 2)->nullable();   // الرقم الخام قبل التحويل
                $t->string('raw_unit', 20)->nullable();      // دستة / قطعة
                $t->string('source', 40)->default('quickbooks_excel');
                $t->unsignedSmallInteger('revision')->default(1);
                $t->boolean('is_locked')->default(false);    // مقفول = معتمد للتقارير
                $t->boolean('unit_warning')->default(false); // رقم شاذ — يحتمل خطأ دستة/قطعة
                $t->foreignId('imported_by')->nullable();
                $t->timestamps();
                $t->index(['product_model_id', 'period_from', 'period_to']);
            });
        }

        // ── لقطات الأرصدة اليومية ──────────────────────────────────
        if (!Schema::hasTable('stock_snapshots')) {
            Schema::create('stock_snapshots', function (Blueprint $t) {
                $t->id();
                $t->date('pulled_at');
                $t->foreignId('warehouse_id')->nullable();
                $t->foreignId('product_model_id')->nullable();
                $t->foreignId('color_id')->nullable();
                $t->foreignId('size_id')->nullable();
                $t->decimal('qty_pcs', 15, 2)->default(0);
                $t->enum('reliability', ['counted','book','estimated'])->default('book'); // مجرود / دفتري
                $t->string('source', 40)->default('excel');
                $t->foreignId('imported_by')->nullable();
                $t->timestamps();
                $t->index(['pulled_at', 'product_model_id']);
            });
        }

        /* ── نسب الألوان ───────────────────────────────────────────
         | المبيعات مش متاحة باللون، فبنستنتج النسب من صرف المخزن الرئيسي.
         | النسب دي قابلة للتعديل اليدوي من شاشة، وكل تعديل بيتسجّل.
        */
        if (!Schema::hasTable('color_ratios')) {
            Schema::create('color_ratios', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_model_id');
                $t->foreignId('color_id');
                $t->unsignedSmallInteger('year');
                $t->unsignedTinyInteger('month')->nullable();  // null = النسبة السنوية
                $t->decimal('ratio_pct', 8, 3)->default(0);
                $t->enum('source', ['issues','manual','sales'])->default('issues'); // صرف المخزن / يدوي
                $t->foreignId('updated_by')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->unique(['product_model_id', 'color_id', 'year', 'month'], 'color_ratio_unique');
            });
        }

        // ── الفوركاست ──────────────────────────────────────────────
        if (!Schema::hasTable('forecasts')) {
            Schema::create('forecasts', function (Blueprint $t) {
                $t->id();
                $t->unsignedSmallInteger('year');
                $t->unsignedTinyInteger('month');
                $t->foreignId('product_model_id');
                $t->foreignId('color_id')->nullable();
                $t->decimal('base_qty', 15, 2)->default(0);      // الأساس (مبيعات نفس الفترة السنة اللي فاتت)
                $t->decimal('growth_pct', 8, 3)->default(0);     // نسبة الزيادة المتوقعة
                $t->decimal('forecast_qty', 15, 2)->default(0);  // المتوقع
                $t->decimal('actual_qty', 15, 2)->default(0);    // الفعلي (بيتحدّث من اللقطات)
                $t->decimal('achievement_pct', 8, 2)->nullable();// نسبة التحقق
                $t->enum('source', ['generated','manual'])->default('generated');
                $t->enum('status', ['draft','pending','approved'])->default('draft');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
                $t->unique(['year','month','product_model_id','color_id'], 'forecast_unique');
            });
        }

        // ── المخزون الأمان ─────────────────────────────────────────
        if (!Schema::hasTable('safety_stocks')) {
            Schema::create('safety_stocks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_model_id');
                $t->foreignId('color_id')->nullable();
                $t->decimal('qty_pcs', 15, 2)->default(0);      // كمية الأمان
                $t->unsignedSmallInteger('cover_days')->nullable(); // أو محسوبة بأيام تغطية
                $t->text('notes')->nullable();
                $t->foreignId('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['product_model_id', 'color_id'], 'safety_stock_unique');
            });
        }

        // ── تحميل المصانع (لقطة محسوبة للعرض السريع) ───────────────
        if (!Schema::hasTable('factory_loads')) {
            Schema::create('factory_loads', function (Blueprint $t) {
                $t->id();
                $t->date('as_of');
                $t->foreignId('factory_id');
                $t->unsignedInteger('open_work_orders')->default(0);
                $t->unsignedInteger('planned_pieces')->default(0);
                $t->unsignedInteger('cut_pieces')->default(0);
                $t->unsignedInteger('received_pieces')->default(0);
                $t->unsignedInteger('outstanding_pieces')->default(0);
                $t->unsignedInteger('late_work_orders')->default(0);
                $t->timestamps();
                $t->unique(['as_of', 'factory_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['factory_loads','safety_stocks','forecasts','color_ratios',
                  'stock_snapshots','sales_snapshots'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
