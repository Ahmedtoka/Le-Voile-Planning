<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id();
                $t->string('name');                       // الاسم
                $t->string('username')->unique();         // اسم الدخول
                $t->string('email')->nullable()->unique();
                $t->string('phone', 30)->nullable();
                $t->string('password');
                $t->string('job_title')->nullable();      // المسمى الوظيفي
                $t->foreignId('factory_id')->nullable();  // للمستخدم الخارجي (مصنع)
                $t->foreignId('supplier_id')->nullable(); // للمستخدم الخارجي (مورد)
                $t->boolean('is_active')->default(true);
                $t->rememberToken();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $t) {
                $t->string('email')->primary();
                $t->string('token');
                $t->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $t) {
                $t->string('id')->primary();
                $t->foreignId('user_id')->nullable()->index();
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->longText('payload');
                $t->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $t) {
                $t->id();
                $t->string('key')->unique();   // planner, storekeeper ...
                $t->string('name');            // الاسم بالعربي
                $t->text('description')->nullable();
                $t->boolean('is_system')->default(false);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $t) {
                $t->id();
                $t->string('key')->unique();   // purchase_orders.approve
                $t->string('name');
                $t->string('group')->nullable(); // المجموعة في شاشة الصلاحيات
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $t) {
                $t->id();
                $t->foreignId('role_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->unique(['role_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $t) {
                $t->id();
                $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $t->foreignId('role_id')->constrained()->cascadeOnDelete();
                $t->unique(['permission_id', 'role_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
