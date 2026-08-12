<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->mediumText('value');
                $t->integer('expiration');
            });
            Schema::create('cache_locks', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->string('owner');
                $t->integer('expiration');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $t) {
                $t->id();
                $t->string('queue')->index();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
            });
            Schema::create('failed_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('uuid')->unique();
                $t->text('connection');
                $t->text('queue');
                $t->longText('payload');
                $t->longText('exception');
                $t->timestamp('failed_at')->useCurrent();
            });
        }

        // سجل الحركة — مين عمل إيه وإمتى (مطلوب لأن الداتا حساسة)
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->nullable();
                $t->string('action', 60);                  // created / updated / approved ...
                $t->string('subject_type')->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->string('title')->nullable();           // وصف مقروء بالعربي
                $t->json('changes')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
                $t->index(['subject_type', 'subject_id']);
            });
        }

        // الإشعارات (هتغذّي التطبيق لاحقًا)
        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->index();
                $t->string('type', 60);          // approval_pending / low_cover / wo_late ...
                $t->string('title');
                $t->text('body')->nullable();
                $t->string('link')->nullable();
                $t->string('severity', 20)->default('info'); // info / warning / danger
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
