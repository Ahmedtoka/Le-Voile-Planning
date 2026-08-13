<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ══════════════════════════════════════════════════════════════════
 *  ترقيع الفروق في السكيما
 * ══════════════════════════════════════════════════════════════════
 *
 * الميجريشنات الأصلية اتعدّلت أثناء التطوير. أي داتابيز اتعملها migrate
 * قبل التعديل هتبقى ناقصة أعمدة — و Laravel مش هيعيد تشغيل ميجريشن
 * اتسجّل قبل كده.
 *
 * الملف ده بيمشي على كل الأعمدة والـ enums الجديدة ويضيف الناقص بس،
 * فهو آمن سواء على داتابيز قديمة أو على migrate:fresh.
 *
 * ⚠ مفيش أي مسح داتا هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->columns();
        $this->enums();
        $this->backfill();
    }

    /** ── الأعمدة الناقصة ─────────────────────────────────────── */
    private function columns(): void
    {
        $add = function (string $table, array $defs) {
            if (!Schema::hasTable($table)) return;

            $missing = array_filter($defs, fn ($cb, $col) => !Schema::hasColumn($table, $col), ARRAY_FILTER_USE_BOTH);
            if (!$missing) return;

            Schema::table($table, function (Blueprint $t) use ($missing) {
                foreach ($missing as $cb) $cb($t);
            });
        };

        // طلب الشراء — المراحل التلاتة
        $add('purchase_orders', [
            'stage'         => fn ($t) => $t->string('stage', 20)->default('planning')->index(),
            'requested_by'  => fn ($t) => $t->unsignedBigInteger('requested_by')->nullable(),
            'requested_at'  => fn ($t) => $t->timestamp('requested_at')->nullable(),
            'sourced_by'    => fn ($t) => $t->unsignedBigInteger('sourced_by')->nullable(),
            'sourced_at'    => fn ($t) => $t->timestamp('sourced_at')->nullable(),
            'finance_by'    => fn ($t) => $t->unsignedBigInteger('finance_by')->nullable(),
            'finance_at'    => fn ($t) => $t->timestamp('finance_at')->nullable(),
            'finance_note'  => fn ($t) => $t->text('finance_note')->nullable(),
            'planning_note' => fn ($t) => $t->text('planning_note')->nullable(),
        ]);

        // الحوض — الحجز والإفراج
        $add('consignments', [
            'hold_kg'     => fn ($t) => $t->decimal('hold_kg', 15, 3)->default(0),
            'released_kg' => fn ($t) => $t->decimal('released_kg', 15, 3)->default(0),
        ]);

        // حركة المخزون — حالة الجودة
        $add('stock_movements', [
            'quality_state' => fn ($t) => $t->string('quality_state', 20)->default('n/a'),
        ]);

        // إذن الإضافة — بقى بداية الدورة
        $add('stock_additions', [
            'purchase_order_id' => fn ($t) => $t->unsignedBigInteger('purchase_order_id')->nullable(),
            'total_rolls'       => fn ($t) => $t->unsignedInteger('total_rolls')->default(0),
        ]);
        $add('stock_addition_lines', [
            'rolls_count' => fn ($t) => $t->unsignedInteger('rolls_count')->default(0),
        ]);

        // إذن الاستلام — بقى الإفراج
        $add('goods_receipts', [
            'stock_addition_id'    => fn ($t) => $t->unsignedBigInteger('stock_addition_id')->nullable(),
            'fabric_inspection_id' => fn ($t) => $t->unsignedBigInteger('fabric_inspection_id')->nullable(),
        ]);

        // الفحص — الجرد
        $add('fabric_inspections', [
            'declared_rolls' => fn ($t) => $t->unsignedInteger('declared_rolls')->default(0),
            'counted_rolls'  => fn ($t) => $t->unsignedInteger('counted_rolls')->default(0),
            'rolls_variance' => fn ($t) => $t->integer('rolls_variance')->default(0),
            'counted_kg'     => fn ($t) => $t->decimal('counted_kg', 15, 3)->nullable(),
        ]);
    }

    /**
     * ── توسيع الـ enums ──────────────────────────────────────────
     * القيم الجديدة لازم تتضاف، وإلا MySQL في وضع strict هيرمي
     * «Data truncated for column». بنحوّلهم لـ VARCHAR بدل enum عشان
     * أي قيمة جديدة بعد كده ما تحتاجش ميجريشن.
     */
    private function enums(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $widen = function (string $table, string $column, string $default) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) return;

            $type = DB::selectOne(
                'SELECT DATA_TYPE dt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );

            if (!$type || strtolower($type->dt) !== 'enum') return;

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` VARCHAR(30) NOT NULL DEFAULT %s',
                $table, $column, DB::getPdo()->quote($default)
            ));
        };

        $widen('consignments',       'status',        'under_inspection');
        $widen('purchase_orders',    'status',        'draft');
        $widen('purchase_orders',    'stage',         'planning');
        $widen('work_orders',        'status',        'draft');
        $widen('stock_movements',    'quality_state', 'n/a');
        $widen('fabric_inspections', 'result',        'pending');
        $widen('document_comments',  'kind',          'note');
    }

    /** ── تحويل الحالات القديمة للجديدة ─────────────────────────── */
    private function backfill(): void
    {
        if (Schema::hasTable('consignments')) {
            // الأسماء القديمة ⇒ الجديدة
            foreach ([
                'received'    => 'under_inspection',
                'inspecting'  => 'under_inspection',
                'lab_pending' => 'inspected',
                'approved'    => 'released',
            ] as $old => $new) {
                DB::table('consignments')->where('status', $old)->update(['status' => $new]);
            }

            // الأرصدة: المفرج عنه مقابل المحجوز
            // المفرج عنه: released = total، والمتاح = المفرج − المخصص.
            // من غير إعادة حساب remaining_kg الحوض بيفضل مخفي عن أوامر الشغل.
            DB::table('consignments')
                ->whereIn('status', ['released', 'in_production', 'closed'])
                ->whereRaw('released_kg = 0')
                ->update([
                    'released_kg'  => DB::raw('total_kg'),
                    'hold_kg'      => 0,
                    'remaining_kg' => DB::raw('GREATEST(total_kg - allocated_kg, 0)'),
                ]);

            DB::table('consignments')
                ->whereIn('status', ['under_inspection', 'inspected', 'lab_done'])
                ->update(['hold_kg' => DB::raw('total_kg'), 'released_kg' => 0, 'remaining_kg' => 0]);
        }

        // مراحل طلبات الشراء القديمة
        if (Schema::hasTable('purchase_orders')) {
            DB::table('purchase_orders')->where('stage', '')->orWhereNull('stage')
                ->update(['stage' => 'planning']);

            foreach ([
                'approved'           => 'approved',
                'partially_received' => 'receiving',
                'received'           => 'closed',
                'closed'             => 'closed',
                'cancelled'          => 'cancelled',
            ] as $status => $stage) {
                DB::table('purchase_orders')
                    ->where('status', $status)->where('stage', 'planning')
                    ->update(['stage' => $stage]);
            }
        }

        // جرد الأتواب للتقارير القديمة
        if (Schema::hasTable('fabric_inspections') && Schema::hasColumn('fabric_inspections', 'total_rolls')) {
            DB::table('fabric_inspections')->where('counted_rolls', 0)
                ->update([
                    'counted_rolls'  => DB::raw('total_rolls'),
                    'declared_rolls' => DB::raw('total_rolls'),
                    'rolls_variance' => 0,
                ]);
        }
    }

    public function down(): void
    {
        // ترقيع — مفيش رجوع. الرجوع معناه فقدان أعمدة فيها داتا.
    }
};
