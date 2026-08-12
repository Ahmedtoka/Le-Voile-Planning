<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── الموردين ───────────────────────────────────────────────
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();        // كود المورد (ظاهر في إذن الاستلام)
                $t->string('name');                       // اسم المورد
                $t->string('contact_person')->nullable(); // الشخص المسؤول
                $t->string('phone', 40)->nullable();
                $t->string('address')->nullable();
                $t->string('payment_terms')->nullable();  // طريقة الدفع
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── المصانع الخارجية ───────────────────────────────────────
        if (!Schema::hasTable('factories')) {
            Schema::create('factories', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name');                          // سيني / خالد ...
                $t->string('contact_person')->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('address')->nullable();
                $t->unsignedInteger('daily_capacity_pcs')->nullable(); // الطاقة اليومية بالقطعة
                $t->unsignedSmallInteger('avg_cycle_days')->nullable(); // متوسط دورة التشغيل
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── المخازن ────────────────────────────────────────────────
        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();   // 043 ...
                $t->string('name');                  // مخزن العبور ...
                $t->enum('type', ['fabric', 'accessories', 'finished', 'other'])->default('fabric');
                $t->string('location')->nullable();
                $t->date('last_stock_count_at')->nullable(); // آخر جرد — عشان نعرف الرصيد موثوق قد إيه
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // ── الخامات + المواصفة المعتمدة ────────────────────────────
        if (!Schema::hasTable('fabric_types')) {
            Schema::create('fabric_types', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name');                      // فسكوز فل ليكرا ...
                $t->string('composition')->nullable();   // التركيب
                $t->decimal('spec_width_cm', 8, 2)->nullable();   // العرض المعياري
                $t->decimal('spec_gsm', 8, 2)->nullable();        // البنشر المعياري
                $t->decimal('spec_gsm_min', 8, 2)->nullable();    // حد القبول الأدنى
                $t->decimal('spec_gsm_max', 8, 2)->nullable();    // حد القبول الأعلى
                $t->decimal('spec_width_min_cm', 8, 2)->nullable();
                $t->decimal('max_shrink_len_pct', 6, 2)->nullable();   // أقصى انكماش طول مقبول
                $t->decimal('max_shrink_width_pct', 6, 2)->nullable();
                $t->decimal('max_defect_pct', 6, 2)->nullable();       // أقصى نسبة عيوب مقبولة
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        /* ── الألوان ───────────────────────────────────────────────
         | أخطر جدول في السيستم.
         | عندهم ~3000 كود لون لأن كل صبغة رجعت بكود جديد، وحصل دمج وإلغاء.
         | القاعدة: ممنوع الحذف النهائي. الدمج بيتسجّل ويفضل الكود القديم
         | موجود وبيشاور على الجديد، عشان الداتا التاريخية تفضل قابلة للتتبّع.
        */
        if (!Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();        // كود اللون
                $t->string('name');                       // أسود / أوف وايت / بني كود 5 ...
                $t->string('family')->nullable();         // العائلة اللونية (بني / أزرق ...)
                $t->string('hex', 10)->nullable();        // للعرض على الشاشة
                $t->boolean('is_basic')->default(false);  // أساسي (أبيض/أسود/أوف وايت) — له داتا تاريخية
                $t->enum('status', ['active', 'merged', 'retired'])->default('active');
                $t->foreignId('merged_into_id')->nullable(); // الكود اللي اندمج فيه
                $t->text('merge_note')->nullable();
                $t->timestamp('merged_at')->nullable();
                $t->foreignId('merged_by')->nullable();
                $t->string('legacy_code', 60)->nullable(); // الكود في السيستم القديم
                $t->timestamps();
                $t->index('status');
                $t->index('merged_into_id');
            });
        }

        // سجل دمج الألوان — تاريخ كامل لا يُحذف
        if (!Schema::hasTable('color_merges')) {
            Schema::create('color_merges', function (Blueprint $t) {
                $t->id();
                $t->foreignId('from_color_id');
                $t->foreignId('to_color_id');
                $t->foreignId('user_id')->nullable();
                $t->text('reason')->nullable();
                $t->timestamps();
            });
        }

        // ── المقاسات ───────────────────────────────────────────────
        if (!Schema::hasTable('sizes')) {
            Schema::create('sizes', function (Blueprint $t) {
                $t->id();
                $t->string('code', 20)->unique();
                $t->string('name');
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // ── الموديلات (المكملات) ───────────────────────────────────
        if (!Schema::hasTable('product_models')) {
            Schema::create('product_models', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name');                      // بادي كات / سابرينا / بنطلون ...
                $t->string('category')->nullable();      // الفئة
                $t->foreignId('fabric_type_id')->nullable(); // الخامة الافتراضية
                $t->unsignedSmallInteger('pcs_per_dozen')->default(12); // حارس الدستة/القطعة
                $t->decimal('std_consumption_kg', 10, 4)->nullable();   // استهلاك معياري مرجعي
                $t->boolean('is_active')->default(true);
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // المقاسات المتاحة لكل موديل
        if (!Schema::hasTable('model_sizes')) {
            Schema::create('model_sizes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->foreignId('size_id')->constrained()->cascadeOnDelete();
                $t->decimal('size_factor', 8, 4)->default(1); // معامل الاستهلاك النسبي للمقاس
                $t->unique(['product_model_id', 'size_id']);
            });
        }

        // ── الإكسسوارات ────────────────────────────────────────────
        if (!Schema::hasTable('accessories')) {
            Schema::create('accessories', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name');                       // كيس مقاس 2 / استيكر / زرار / سوستة ...
                $t->enum('type', ['bag', 'sticker', 'label', 'button', 'zipper', 'thread', 'carton', 'other'])->default('other');
                $t->string('unit', 20)->default('قطعة');
                $t->decimal('stock_qty', 15, 3)->default(0);
                $t->decimal('reorder_point', 15, 3)->default(0);
                $t->boolean('is_shared')->default(false); // مشترك بين موديلات (زي كيس مقاس 2 و 3)
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // ── قائمة الإكسسوارات لكل موديل/مقاس (BOM) ─────────────────
        if (!Schema::hasTable('model_boms')) {
            Schema::create('model_boms', function (Blueprint $t) {
                $t->id();
                $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
                $t->foreignId('size_id')->nullable();     // null = لكل المقاسات
                $t->foreignId('accessory_id')->constrained()->cascadeOnDelete();
                $t->decimal('qty_per_piece', 12, 4)->default(1); // الكمية لكل قطعة منتج
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['product_model_id', 'size_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['model_boms','accessories','model_sizes','product_models','sizes',
                  'color_merges','colors','fabric_types','warehouses','factories','suppliers'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
