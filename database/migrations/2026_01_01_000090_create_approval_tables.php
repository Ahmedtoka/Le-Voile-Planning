<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── محرك الاعتمادات الموحّد ───────────────────────────────
         | جدول واحد بيخدم كل المستندات. الدورة نفسها بتتعرّف من شاشة
         | إعدادات (approval_flows + steps) — تغيير الدورة مش محتاج كود.
        */
        if (!Schema::hasTable('approval_flows')) {
            Schema::create('approval_flows', function (Blueprint $t) {
                $t->id();
                $t->string('doc_type', 60)->unique();  // purchase_order / goods_receipt / ...
                $t->string('name');                     // الاسم بالعربي
                $t->boolean('is_active')->default(true);
                $t->boolean('allow_skip_if_same_user')->default(false);
                $t->text('description')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('approval_flow_steps')) {
            Schema::create('approval_flow_steps', function (Blueprint $t) {
                $t->id();
                $t->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
                $t->unsignedSmallInteger('step_no');
                $t->string('title');                    // اعتماد مدير المشتريات
                $t->foreignId('role_id')->nullable();   // الدور المسؤول
                $t->foreignId('user_id')->nullable();   // أو مستخدم بعينه
                $t->boolean('is_mandatory')->default(true);
                $t->decimal('min_amount', 15, 2)->nullable(); // الخطوة تشتغل فوق مبلغ معين
                $t->timestamps();
                $t->unique(['approval_flow_id', 'step_no']);
            });
        }

        // نسخة حيّة من الدورة على كل مستند
        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $t) {
                $t->id();
                $t->string('doc_type', 60);
                $t->string('subject_type');
                $t->unsignedBigInteger('subject_id');
                $t->string('subject_no', 60)->nullable();   // رقم المستند للعرض
                $t->unsignedSmallInteger('current_step')->default(1);
                $t->unsignedSmallInteger('total_steps')->default(1);
                $t->enum('status', ['pending','approved','rejected','cancelled'])->default('pending');
                $t->foreignId('requested_by')->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->timestamps();
                $t->index(['subject_type', 'subject_id']);
                $t->index(['doc_type', 'status']);
            });
        }

        if (!Schema::hasTable('approval_steps')) {
            Schema::create('approval_steps', function (Blueprint $t) {
                $t->id();
                $t->foreignId('approval_id')->constrained()->cascadeOnDelete();
                $t->unsignedSmallInteger('step_no');
                $t->string('title');
                $t->foreignId('role_id')->nullable();
                $t->foreignId('user_id')->nullable();       // المطلوب منه
                $t->foreignId('acted_by')->nullable();      // اللي عمل الأكشن فعلًا
                $t->enum('status', ['waiting','pending','approved','rejected','skipped'])->default('waiting');
                $t->text('comment')->nullable();
                $t->timestamp('acted_at')->nullable();
                $t->timestamps();
                $t->index(['approval_id', 'step_no']);
            });
        }
    }

    public function down(): void
    {
        foreach (['approval_steps','approvals','approval_flow_steps','approval_flows'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
