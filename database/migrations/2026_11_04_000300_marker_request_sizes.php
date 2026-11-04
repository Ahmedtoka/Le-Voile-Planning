<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مقاسات طلب الماركر: سطر لكل (موديل + مقاس) بيتختار من دروب داون —
 * بدل خانة «المقاسات / تفاصيل» اللي كانت كلام حر. الباترونيست بياخدها سطور جاهزة في الماركر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marker_request_lines')) return;
        Schema::create('marker_request_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marker_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_model_id')->constrained()->cascadeOnDelete();
            $t->foreignId('size_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['marker_request_id', 'product_model_id', 'size_id'], 'mr_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marker_request_lines');
    }
};
