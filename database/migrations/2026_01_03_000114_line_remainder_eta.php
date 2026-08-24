<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * موعد الباقي لاين لاين:
 * كل صنف اتستلم جزئي بياخد تاريخه وملاحظته على سطره —
 * مش تاريخ واحد عام على الإذن كله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_addition_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('stock_addition_lines', 'remainder_eta'))  $t->date('remainder_eta')->nullable();
            if (!Schema::hasColumn('stock_addition_lines', 'remainder_note')) $t->string('remainder_note', 191)->nullable();
        });
    }

    public function down(): void
    {
        // إضافات فقط.
    }
};
