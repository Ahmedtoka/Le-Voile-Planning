<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تنضيف التسكينات اللي فضلت بعد مسح البيانات.
 *
 * lv:demo / lv:reset كانوا بيمسحوا الطلبات من غير التسكينات، والـid بيرجع من 1 —
 * فأول طلب جديد ياخد رقم قديم كان بيلاقي تسكينات طلب اتمسح لازقة فيه
 * (طلب اتعمل 4 نوفمبر وفيه تسكينات من 22 و30 أكتوبر، وبالطن).
 *
 * بنمسح بس اللي مستحيل يكون صح:
 *   • تسكينة طلبها مش موجود
 *   • تسكينة اتعملت قبل طلبها نفسه
 *   • سطر تسكينة بيشاور على سطر طلب مش موجود أو تبع طلب تاني
 * والأذون اللي كانت بتشاور عليها بتفضل زي ما هي من غير ربط بالتسكينة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('po_sourcings') || !Schema::hasTable('po_sourcing_lines')) return;

        $stale = DB::table('po_sourcings as s')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 's.purchase_order_id')
            ->where(fn ($q) => $q->whereNull('po.id')
                ->orWhereColumn('s.created_at', '<', 'po.created_at'))
            ->pluck('s.id');

        $badLines = DB::table('po_sourcing_lines as l')
            ->join('po_sourcings as s', 's.id', '=', 'l.po_sourcing_id')
            ->leftJoin('purchase_order_lines as pl', 'pl.id', '=', 'l.purchase_order_line_id')
            ->where(fn ($q) => $q->whereNull('pl.id')
                ->orWhereColumn('pl.purchase_order_id', '!=', 's.purchase_order_id'))
            ->pluck('l.id');

        $lineIds = DB::table('po_sourcing_lines')->whereIn('po_sourcing_id', $stale)->pluck('id')->merge($badLines)->unique();

        DB::transaction(function () use ($stale, $lineIds) {
            if (Schema::hasColumn('stock_addition_lines', 'po_sourcing_line_id')) {
                DB::table('stock_addition_lines')->whereIn('po_sourcing_line_id', $lineIds)->update(['po_sourcing_line_id' => null]);
            }
            if (Schema::hasColumn('stock_additions', 'po_sourcing_id')) {
                DB::table('stock_additions')->whereIn('po_sourcing_id', $stale)->update(['po_sourcing_id' => null]);
            }
            DB::table('po_sourcing_lines')->whereIn('id', $lineIds)->delete();
            DB::table('po_sourcings')->whereIn('id', $stale)->delete();

            // تسكينة فضيت من سطورها؟ ملهاش لازمة
            DB::table('po_sourcings')->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('po_sourcing_lines')->whereColumn('po_sourcing_lines.po_sourcing_id', 'po_sourcings.id'))->delete();
        });

        // نفس المشكلة مع خامات الموديل بعد lv:reset
        if (Schema::hasTable('model_fabrics') && Schema::hasTable('product_models')) {
            DB::table('model_fabrics as mf')
                ->leftJoin('product_models as pm', 'pm.id', '=', 'mf.product_model_id')
                ->whereNull('pm.id')
                ->pluck('mf.id')
                ->chunk(500)->each(fn ($ids) => DB::table('model_fabrics')->whereIn('id', $ids)->delete());
        }
    }

    public function down(): void
    {
        // تنضيف — مفيش رجوع
    }
};
