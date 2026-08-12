<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── طلب ماركر للباترونست ──────────────────────────────────
         | المخطط بيقول: "محتاج موديل X عند مصنع Y على عرض 185"
         | الباترونست بيدخل بنفسه ويرفع الماركر ببياناته.
        */
        if (!Schema::hasTable('marker_requests')) {
            Schema::create('marker_requests', function (Blueprint $t) {
                $t->id();
                $t->string('doc_no', 40)->unique();
                $t->date('doc_date');
                $t->foreignId('consignment_id')->nullable();
                $t->foreignId('factory_id')->nullable();
                $t->decimal('fabric_width_cm', 8, 2);        // العرض المتاح (أقل عرض)
                $t->text('requested_models')->nullable();    // وصف المطلوب
                $t->foreignId('assigned_to')->nullable();    // الباترونست
                $t->date('needed_by')->nullable();
                $t->foreignId('marker_id')->nullable();      // الماركر اللي اتسلّم
                $t->enum('status', ['open','in_progress','delivered','cancelled'])->default('open');
                $t->text('notes')->nullable();
                $t->foreignId('created_by')->nullable();
                $t->timestamps();
            });
        }

        /* ── الماركر (التعشيقة) ────────────────────────────────────
         | الماركر ممكن يشيل أكتر من موديل وأكتر من مقاس بنِسَب مختلفة،
         | لأن المكملات بتتقص خامة واحدة لموديلات كتير.
         | قاعدة: عرض الماركر لازم يكون <= أقل عرض قماش، وإلا هنحرق الجنب.
        */
        if (!Schema::hasTable('markers')) {
            Schema::create('markers', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->string('name')->nullable();
                $t->foreignId('marker_request_id')->nullable();
                $t->foreignId('factory_id')->nullable();
                $t->foreignId('created_by_patternist')->nullable();

                $t->decimal('fabric_width_cm', 8, 2);        // ★ عرض القماش اللي اتعمل عليه
                $t->decimal('marker_width_cm', 8, 2)->nullable(); // عرض التعشيقة الفعلي
                $t->decimal('spread_length_m', 10, 3);       // ★ طول الفرشة (3.07)
                $t->unsignedInteger('pieces_per_spread');    // ★ عدد القطع في الفرشة
                $t->decimal('efficiency_pct', 6, 2)->nullable(); // كفاءة التعشيق
                $t->string('file_path')->nullable();          // ملف/صورة الميني ماركر
                $t->boolean('is_active')->default(true);
                $t->enum('status', ['draft','pending','approved','rejected'])->default('draft');
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // سطور الماركر: كل موديل/مقاس وعدد قطعه في الفرشة
        if (!Schema::hasTable('marker_lines')) {
            Schema::create('marker_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('marker_id')->constrained()->cascadeOnDelete();
                $t->foreignId('product_model_id');
                $t->foreignId('size_id')->nullable();
                $t->unsignedInteger('qty_per_spread')->default(1); // عدد قطع الموديل/المقاس في الفرشة
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['marker_id', 'product_model_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marker_lines');
        Schema::dropIfExists('markers');
        Schema::dropIfExists('marker_requests');
    }
};
