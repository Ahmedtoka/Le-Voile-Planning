<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ── تعليقات المستندات ─────────────────────────────────────
         | كل مستند بيبقى زي التيكيت: نقاش رايح جاي بين الأقسام،
         | مع إمكانية إرفاق صورة كإثبات (صورة القماش، الورقة الأصلية،
         | صورة العيب...). التعليقات مش بتتحذف — دي سجل.
        */
        if (!Schema::hasTable('document_comments')) {
            Schema::create('document_comments', function (Blueprint $t) {
                $t->id();
                $t->string('commentable_type');
                $t->unsignedBigInteger('commentable_id');
                $t->foreignId('user_id')->nullable();
                $t->text('body')->nullable();
                $t->string('attachment_path')->nullable();
                $t->string('attachment_name')->nullable();
                $t->string('attachment_mime', 60)->nullable();
                $t->unsignedInteger('attachment_size')->nullable();
                // نوع التعليق: عادي / طلب توضيح / رد / قرار
                $t->enum('kind', ['note', 'question', 'answer', 'decision', 'system'])->default('note');
                $t->foreignId('reply_to_id')->nullable();
                $t->json('mentions')->nullable();     // user ids اللي اتناداهم
                $t->timestamps();
                $t->index(['commentable_type', 'commentable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
