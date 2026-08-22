<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نسخ أمر الشغل (Revisions):
 * الأمر المعتمد مش بيتعدل — أي تغيير مؤثر بيفتح نسخة جديدة بترجع
 * للاعتماد، والقديمة بتتعلم «استُبدل بنسخة أحدث» وبتفضل محفوظة بالكامل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('work_orders', 'revision_no'))     $t->unsignedSmallInteger('revision_no')->default(1);
            if (!Schema::hasColumn('work_orders', 'revised_from_id')) $t->foreignId('revised_from_id')->nullable(); // النسخة اللي اتعدلت
            if (!Schema::hasColumn('work_orders', 'revision_reason')) $t->text('revision_reason')->nullable();      // سبب التعديل — إجباري
        });
    }

    public function down(): void
    {
        // إضافات فقط.
    }
};
