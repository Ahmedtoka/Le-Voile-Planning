<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * التسكينة = أمر شراء لمورد (PO-2026-00001 / 2).
 * طلب واحد اتشرى من موردين = أمرين: كل واحد بمورده وأسعاره وإجماليه —
 * والحسابات بتسجّل علمها بكل أمر لوحده، و«حسب المورد» بيجمع إجمالي كل مورد الحقيقي.
 *
 * 1) علم الحسابات بقى على التسكينة (finance_by / finance_at / finance_note).
 * 2) الطلب اللي الحسابات علمت بيه قبل كده: تسكيناته اللي اتعملت قبل العلم بتاخد نفس العلم.
 * 3) طلب متسعّر من غير تسكينة (اتعمل قبل التسكين أو اتمسحت تسكيناته الفاضلة): بياخد تسكينة
 *    واحدة من بيانات الهيدر وسطوره — عشان كل طلب متسعّر يبقى له أمر شراء واحد على الأقل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_sourcings', function (Blueprint $t) {
            if (!Schema::hasColumn('po_sourcings', 'finance_by'))   $t->foreignId('finance_by')->nullable()->after('sourced_at');
            if (!Schema::hasColumn('po_sourcings', 'finance_at'))   $t->timestamp('finance_at')->nullable()->after('finance_by');
            if (!Schema::hasColumn('po_sourcings', 'finance_note')) $t->text('finance_note')->nullable()->after('finance_at');
        });

        // (3) طلبات متسعّرة من غير تسكينة
        $pos = DB::table('purchase_orders')
            ->whereNotNull('sourced_at')->whereNotNull('supplier_id')
            ->whereNotIn('id', DB::table('po_sourcings')->select('purchase_order_id'))
            ->get();

        foreach ($pos as $po) {
            $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $po->id)->get();
            if ($lines->isEmpty()) continue;

            $sid = DB::table('po_sourcings')->insertGetId([
                'purchase_order_id' => $po->id,
                'seq'               => 1,
                'supplier_id'       => $po->supplier_id,
                'warehouse_id'      => $po->warehouse_id,
                'delivery_date'     => $po->delivery_date,
                'payment_method'    => $po->payment_method,
                'discount_pct'      => $po->discount_pct ?? 0,
                'tax_pct'           => $po->tax_pct ?? 0,
                'subtotal'          => $po->subtotal ?? 0,
                'total'             => $po->total ?? 0,
                'sourced_by'        => $po->sourced_by,
                'sourced_at'        => $po->sourced_at,
                'created_at'        => $po->sourced_at ?? now(), 'updated_at' => now(),
            ]);

            foreach ($lines as $l) {
                DB::table('po_sourcing_lines')->insert([
                    'po_sourcing_id'         => $sid,
                    'purchase_order_line_id' => $l->id,
                    'qty'                    => $l->qty,
                    'unit'                   => $l->unit,
                    'unit_price'             => $l->unit_price ?? 0,
                    'line_total'             => $l->line_total ?? 0,
                    'received_qty'           => $l->received_qty ?? 0,
                    'created_at'             => $po->sourced_at ?? now(), 'updated_at' => now(),
                ]);
                DB::table('purchase_order_lines')->where('id', $l->id)->update(['sourced_qty' => $l->qty]);
            }
            // الأذون اللي اتعملت على الطلب ده بتتربط بأمره الوحيد
            DB::table('stock_additions')->where('purchase_order_id', $po->id)->whereNull('po_sourcing_id')->update(['po_sourcing_id' => $sid]);
        }

        // (4) سعر سطر الطلب كان «آخر سعر» — بقى متوسط مرجّح من أوامره (إجمالي ÷ كمية)
        $avg = DB::table('po_sourcing_lines')->selectRaw('purchase_order_line_id, SUM(line_total) t, SUM(qty) q')
            ->groupBy('purchase_order_line_id')->havingRaw('SUM(qty) > 0')->get();
        foreach ($avg as $a) {
            DB::table('purchase_order_lines')->where('id', $a->purchase_order_line_id)
                ->update(['unit_price' => round($a->t / $a->q, 2), 'line_total' => $a->t]);
        }

        // (2) علم الطلب ⇒ علم تسكيناته اللي كانت موجودة وقتها
        foreach (DB::table('purchase_orders')->whereNotNull('finance_at')->get(['id', 'finance_by', 'finance_at', 'finance_note']) as $po) {
            DB::table('po_sourcings')->where('purchase_order_id', $po->id)->whereNull('finance_at')
                ->where(fn ($q) => $q->whereNull('sourced_at')->orWhere('sourced_at', '<=', $po->finance_at))
                ->update(['finance_by' => $po->finance_by, 'finance_at' => $po->finance_at, 'finance_note' => $po->finance_note]);
        }
    }

    public function down(): void
    {
        Schema::table('po_sourcings', function (Blueprint $t) {
            $t->dropColumn(['finance_by', 'finance_at', 'finance_note']);
        });
    }
};
