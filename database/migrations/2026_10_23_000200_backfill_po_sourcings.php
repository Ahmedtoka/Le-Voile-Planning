<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الطلبات اللي اتسعّرت قبل التسكين الجزئي (الميتينج 37): بنعمل لكل واحدة تسكينة واحدة
 * من بيانات الهيدر (المورد والسعر وتاريخ التوريد) وبنعلّم بنودها «اتسكّنت كلها» —
 * وإلا هتظهر كأنها مستنية تسعير. idempotent: ما بيلمسش طلب عنده تسكينات.
 */
return new class extends Migration
{
    public function up(): void
    {
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
                'created_at'        => now(), 'updated_at' => now(),
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
                    'created_at'             => now(), 'updated_at' => now(),
                ]);
                DB::table('purchase_order_lines')->where('id', $l->id)->update(['sourced_qty' => $l->qty]);
            }
        }
    }

    public function down(): void {}
};
