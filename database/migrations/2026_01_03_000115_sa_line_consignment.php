<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الاستلامة الواحدة بتولّد حوض (رسالة) لكل خامة + لون —
 * فكل سطر بيتربط بحوضه، والفحص والحركات بيمشوا عليه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_addition_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_addition_lines', 'consignment_id')) {
                $t->foreignId('consignment_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        // إضافات فقط.
    }
};
