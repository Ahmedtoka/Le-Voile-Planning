<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* السيستم بقى من غير اعتمادات — اللي بيعمل المستند هو اللي بيقرره.
       جداول دورة الاعتماد القديمة مالهاش أي استخدام، فبنشيلها.
       المستندات العالقة «تحت الاعتماد» بتتقفل بـ php artisan lv:finish-pending */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_flow_steps');
        Schema::dropIfExists('approval_flows');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // مفيش رجوع — دورة الاعتماد اتشالت من الكود
    }
};
