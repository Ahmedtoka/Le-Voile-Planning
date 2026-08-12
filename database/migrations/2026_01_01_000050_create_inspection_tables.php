<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── تقرير فحص قماش ────────────────────────────────────────
         | الفحص عيّنة مش 100% (6-7 أتواب من 40). لازم نخزّن حجم العيّنة
         | ونظهره على أي حسبة مبنية عليه، لأن كل الأرقام اللي بعده متوقّعة.
        */
        if (!Schema::hasTable('fabric_inspections')) {
            Schema::create('fabric_inspections', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->string('paper_serial', 40)->nullable();     // 04619
                $t->date('doc_date');
                $t->foreignId('consignment_id')->nullable();     // الرسالة
                $t->foreignId('fabric_type_id')->nullable();     // الصنف
                $t->foreignId('color_id')->nullable();           // كود اللون
                $t->foreignId('supplier_id')->nullable();        // اسم المورد
                $t->foreignId('work_order_id')->nullable();      // أمر التشغيل (لو الفحص بعد التخصيص)
                $t->foreignId('inspector_id')->nullable();       // الفاحص

                $t->unsignedInteger('total_rolls')->default(0);   // إجمالي أتواب الحوض
                $t->unsignedInteger('sampled_rolls')->default(0); // عدد الأتواب المفحوصة ★
                $t->decimal('sample_pct', 6, 2)->default(0);

                $t->decimal('total_length_m', 15, 2)->default(0); // إجمالي الكمية
                $t->decimal('min_width_cm', 8, 2)->nullable();    // ★ يغذّي الماركر
                $t->decimal('avg_width_cm', 8, 2)->nullable();
                $t->decimal('max_width_cm', 8, 2)->nullable();
                $t->decimal('width_spread_cm', 8, 2)->nullable(); // الفرق بين أكبر وأصغر عرض
                $t->unsignedInteger('total_defects')->default(0);
                $t->decimal('defect_pct', 8, 3)->default(0);

                $t->enum('result', ['pending','accepted','accepted_with_notes','rejected'])->default('pending');
                $t->boolean('width_alert')->default(false);       // فرق العرض تعدّى الحد المسموح
                $t->text('notes')->nullable();
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        // سطر لكل توب مفحوص: الطول | العيب | ملاحظات
        if (!Schema::hasTable('inspection_rolls')) {
            Schema::create('inspection_rolls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('fabric_inspection_id')->constrained()->cascadeOnDelete();
                $t->foreignId('fabric_roll_id')->nullable();
                $t->string('roll_no', 40)->nullable();       // رقم التوب
                $t->decimal('length_m', 12, 2)->nullable();  // طول التوب
                $t->decimal('width_cm', 8, 2)->nullable();   // العرض
                $t->decimal('gsm', 8, 2)->nullable();
                $t->unsignedSmallInteger('defects_count')->default(0); // عدد العيوب
                $t->decimal('defect_pct', 8, 3)->nullable();           // النسبة
                $t->text('defect_desc')->nullable();          // العيب (بوجرسو / برادة خفيفة ...)
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        /* ── تقرير انكماش قماش ومطابقة ألوان (المعمل) ──────────────── */
        if (!Schema::hasTable('lab_reports')) {
            Schema::create('lab_reports', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->string('paper_serial', 40)->nullable();   // 002192
                $t->date('doc_date');
                $t->foreignId('consignment_id')->nullable();   // الرسالة
                $t->foreignId('supplier_id')->nullable();      // اسم المورد
                $t->foreignId('fabric_type_id')->nullable();   // اسم الخامة
                $t->foreignId('color_id')->nullable();         // اللون
                $t->foreignId('technician_id')->nullable();    // فني المعمل

                $t->decimal('avg_gsm', 8, 2)->nullable();      // ★ متوسط وزن البنشر
                $t->decimal('min_gsm', 8, 2)->nullable();
                $t->decimal('max_gsm', 8, 2)->nullable();

                // نسبة الانكماش — عينتين، طول وعرض
                $t->decimal('s1_shrink_len_pct', 6, 2)->nullable();
                $t->decimal('s1_shrink_width_pct', 6, 2)->nullable();
                $t->decimal('s2_shrink_len_pct', 6, 2)->nullable();
                $t->decimal('s2_shrink_width_pct', 6, 2)->nullable();
                $t->decimal('avg_shrink_len_pct', 6, 2)->nullable();
                $t->decimal('avg_shrink_width_pct', 6, 2)->nullable();

                $t->boolean('color_match_ok')->nullable();     // مطابقة اللون
                $t->string('color_swatch_path')->nullable();   // صورة العينة الملصوقة
                $t->text('notes')->nullable();
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        // قراءات البنشر (195, 193, 197, 204 ...) — قراءة لكل توب
        if (!Schema::hasTable('lab_gsm_readings')) {
            Schema::create('lab_gsm_readings', function (Blueprint $t) {
                $t->id();
                $t->foreignId('lab_report_id')->constrained()->cascadeOnDelete();
                $t->string('roll_no', 40)->nullable();
                $t->decimal('gsm', 8, 2);                     // وزن البنشر
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['lab_gsm_readings','lab_reports','inspection_rolls','fabric_inspections'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
