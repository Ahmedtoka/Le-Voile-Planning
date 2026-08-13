<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\FabricRoll;
use App\Models\CutDeclaration;
use App\Models\FabricInspection;
use App\Models\GoodsReceipt;
use App\Models\LabReport;
use App\Models\ProductionReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * الآثار الجانبية اللي بتحصل لما مستند يتعمد.
 *
 * كل حاجة هنا جوه transaction — لأن تحديث رصيد الحوض وحركة المخزون
 * وسطور أمر الشغل لازم يحصلوا كلهم أو ولا واحد فيهم.
 */
class DocumentEffects
{
    public static function onApproved(Model $doc): void
    {
        match (true) {
            // طلب الشراء: الاعتماد بيخرّجه من مرحلة approval ويبعته للمورد
            $doc instanceof PurchaseOrder     => $doc->forceFill(['stage' => 'approved'])->saveQuietly(),
            // الترتيب الفعلي للدورة: إضافة ⇒ فحص ⇒ معمل ⇒ استلام
            $doc instanceof StockAddition     => self::stockAddition($doc),
            $doc instanceof FabricInspection  => self::inspection($doc),
            $doc instanceof GoodsReceipt      => self::goodsReceipt($doc),
            $doc instanceof LabReport         => self::labReport($doc),
            $doc instanceof WorkOrder         => self::workOrder($doc),
            $doc instanceof CutDeclaration    => self::cutDeclaration($doc),
            $doc instanceof ProductionReceipt => self::productionReceipt($doc),
            default => null,
        };
    }

    /**
     * ① إذن إضافة معتمد ⇒ تكوين الحوض وحجزه تحت الفحص.
     *
     * ده أول مستند في الدورة. القماش بيدخل المخزن **محجوز** —
     * مش متاح لأي أمر شغل لحد ما يتفرج عنه بإذن الاستلام الخام.
     */
    protected static function stockAddition(StockAddition $sa): void
    {
        DB::transaction(function () use ($sa) {
            $sa->load('lines', 'supplier', 'purchaseOrder');

            $fabricLines = $sa->lines->filter(fn ($l) => $l->fabric_type_id);

            // ── تكوين الحوض ──
            if ($fabricLines->isNotEmpty()) {
                $first = $fabricLines->first();

                $no = trim((string) $sa->consignment_no);
                if ($no === '') {
                    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sa->supplier?->code ?? 'CN'), 0, 4)) ?: 'CN';
                    $seq = Consignment::whereDate('arrival_date', $sa->doc_date)->count();
                    $no  = DocNumber::consignmentNo($prefix, $sa->doc_date, $sa->purchaseOrder?->po_no, $seq);
                }

                $totalKg    = (float) $fabricLines->sum('qty');
                $totalRolls = (int) $fabricLines->sum('rolls_count');

                $consignment = Consignment::updateOrCreate(
                    ['consignment_no' => $no],
                    [
                        'arrival_date'      => $sa->doc_date,
                        'purchase_order_id' => $sa->purchase_order_id,
                        'supplier_id'       => $sa->supplier_id,
                        'fabric_type_id'    => $first->fabric_type_id,
                        'color_id'          => $first->color_id,
                        'warehouse_id'      => $sa->warehouse_id,
                        'total_kg'          => $totalKg,
                        'rolls_count'       => $totalRolls,
                        'hold_kg'           => $totalKg,
                        'released_kg'       => 0,
                        'remaining_kg'      => 0,     // ممنوع التشغيل قبل الإفراج
                        'status'            => 'under_inspection',
                        'created_by'        => $sa->created_by,
                    ]
                );

                $sa->forceFill(['consignment_id' => $consignment->id, 'consignment_no' => $no])->saveQuietly();

                // سجل لكل توب — العرض والطول الحقيقيين بييجوا من الفحص
                if ($consignment->rolls()->count() === 0 && $totalRolls > 0) {
                    $avgKg = round($totalKg / $totalRolls, 3);
                    for ($i = 1; $i <= $totalRolls; $i++) {
                        FabricRoll::create([
                            'consignment_id' => $consignment->id,
                            'roll_no'        => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                            'net_kg'         => $avgKg,
                            'status'         => 'in_stock',
                        ]);
                    }
                }

                Notifier::broadcastToRole('inspector', 'inspection_due',
                    'حوض جديد محتاج فحص',
                    'الرسالة ' . $no . ' — ' . number_format($totalKg, 0) . ' كجم · ' . $totalRolls . ' توب',
                    null, 'warning');
            }

            // ── حركة المخزون: داخل ومحجوز ──
            foreach ($sa->lines as $line) {
                StockMovement::create([
                    'moved_at'       => $sa->doc_date,
                    'warehouse_id'   => $sa->warehouse_id,
                    'item_type'      => $line->accessory_id ? 'accessory' : 'fabric',
                    'fabric_type_id' => $line->fabric_type_id,
                    'color_id'       => $line->color_id,
                    'accessory_id'   => $line->accessory_id,
                    'consignment_id' => $sa->consignment_id,
                    'direction'      => 'in',
                    'quality_state'  => $line->accessory_id ? 'released' : 'hold',
                    'qty'            => $line->qty,
                    'unit'           => $line->unit,
                    'source_type'    => StockAddition::class,
                    'source_id'      => $sa->id,
                    'reference'      => $sa->doc_no,
                    'created_by'     => auth()->id(),
                ]);

                // الإكسسوارات مش بتتفحص — بتدخل متاحة على طول
                if ($line->accessory_id && $line->accessory) {
                    $line->accessory->increment('stock_qty', (float) $line->qty);
                }
            }
        });
    }

    /**
     * ④ إذن استلام خام معتمد ⇒ الإفراج عن الحوض.
     *
     * دي اللحظة اللي القماش بيبقى فيها متاح فعليًا لأوامر الشغل،
     * وبتتحدث فيها كمية أمر الشراء المستلمة.
     */
    protected static function goodsReceipt(GoodsReceipt $gr): void
    {
        DB::transaction(function () use ($gr) {
            $gr->load('lines', 'purchaseOrder.lines', 'consignment');

            $consignment = $gr->consignment;

            // ── الإفراج عن الحوض ──
            if ($consignment) {
                // الأسطر ممكن تيجي بالطن — بنوحّدها كيلو، وبنجمع مش بنستبدل
                // عشان الإفراج الجزئي على دفعات ما يمسحش اللي قبله.
                $releasedKg = (float) $gr->lines->sum(fn ($l) => self::toUnit($l->qty, $l->unit, 'كجم'));
                if ($releasedKg <= 0) $releasedKg = (float) $consignment->total_kg;

                $consignment->forceFill([
                    'status'      => 'released',
                    'released_kg' => min((float) $consignment->total_kg,
                                         (float) $consignment->released_kg + $releasedKg),
                    'hold_kg'     => 0,
                ])->save();

                $consignment->recalcRemaining();

                // فك الحجز في حركة المخزون: خروج محجوز + دخول مفرج عنه
                foreach ($gr->lines as $line) {
                    foreach ([['out', 'hold'], ['in', 'released']] as [$dir, $state]) {
                        StockMovement::create([
                            'moved_at'       => $gr->doc_date,
                            'warehouse_id'   => $gr->warehouse_id,
                            'item_type'      => 'fabric',
                            'fabric_type_id' => $line->fabric_type_id,
                            'color_id'       => $line->color_id,
                            'consignment_id' => $consignment->id,
                            'direction'      => $dir,
                            'quality_state'  => $state,
                            'qty'            => $line->qty,
                            'unit'           => $line->unit,
                            'source_type'    => GoodsReceipt::class,
                            'source_id'      => $gr->id,
                            'reference'      => $gr->doc_no,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                Notifier::broadcastToRole('planner', 'consignment_released',
                    'حوض جاهز للتشغيل',
                    'الرسالة ' . $consignment->consignment_no . ' اتفرج عنها — أقل عرض '
                        . $consignment->min_width_cm . ' سم · بنشر ' . $consignment->avg_gsm,
                    null, 'info');
            }

            // ── تحديث المستلم في أمر الشراء ──
            if ($po = $gr->purchaseOrder) {
                foreach ($gr->lines as $line) {
                    $poLine = $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id && $l->color_id == $line->color_id
                    );
                    if ($poLine) {
                        // الوحدات ممكن تختلف بين سطر الطلب وسطر الاستلام —
                        // بنعدّي على الكيلو الأول وبعدين نحوّل لوحدة سطر الطلب.
                        $poLine->increment('received_qty', self::toUnit($line->qty, $line->unit, $poLine->unit));
                    }
                }

                $po->refresh()->load('lines');
                $fully = $po->lines->every(fn ($l) => (float) $l->received_qty >= (float) $l->min_allowed_qty);

                $po->forceFill([
                    'status' => $fully ? 'received' : 'partially_received',
                    'stage'  => $fully ? 'closed' : 'receiving',
                ])->save();
            }
        });
    }

    /** تحويل كمية من وحدة لوحدة — الكيلو هو الوحدة الوسيطة */
    public static function toUnit(float|string $qty, ?string $from, ?string $to): float
    {
        $kg = strtolower(trim((string) $from)) === 'طن' ? (float) $qty * 1000 : (float) $qty;
        return strtolower(trim((string) $to)) === 'طن' ? $kg / 1000 : $kg;
    }

    /**
     * ② فحص معتمد ⇒ نقل نتيجة الجرد وأقل عرض للحوض.
     * الحوض بيفضل محجوز — الإفراج مالوش علاقة بالفحص، ده بيحصل
     * بإذن الاستلام الخام.
     */
    protected static function inspection(FabricInspection $insp): void
    {
        if (!$insp->consignment) return;

        $insp->recalc();
        $insp->refresh();
        $c = $insp->consignment;

        $payload = [
            'min_width_cm' => $insp->min_width_cm,
            'avg_width_cm' => $insp->avg_width_cm,
            'max_width_cm' => $insp->max_width_cm,
            'defect_pct'   => $insp->defect_pct,
        ];

        // الجرد بيصحّح عدد الأتواب والوزن لو فيه فرق عن اللي المورد قال عليه
        if ($insp->counted_rolls > 0)  $payload['rolls_count']    = $insp->counted_rolls;
        if ($insp->total_length_m > 0) $payload['total_length_m'] = $insp->total_length_m;
        if ($insp->counted_kg > 0)     $payload['total_kg']       = $insp->counted_kg;

        $payload['status'] = $insp->result === 'rejected'
            ? 'rejected'
            : ($c->avg_gsm ? 'lab_done' : 'inspected');

        $c->forceFill($payload)->save();

        if ($insp->rolls_variance != 0) {
            Notifier::broadcastToRole('stock_controller', 'roll_variance',
                'فرق في جرد الأتواب',
                'الرسالة ' . $c->consignment_no . ' — ' . $insp->rolls_variance_label
                    . ' (المورد قال ' . $insp->declared_rolls . '، المجرود ' . $insp->counted_rolls . ')',
                null, 'danger');
        }

        if ($insp->result !== 'rejected') {
            Notifier::broadcastToRole('storekeeper', 'receipt_due',
                'حوض متفحص — جاهز لإذن الاستلام',
                'الرسالة ' . $c->consignment_no,
                null, 'info');
        }
    }

    /** ③ تقرير معمل معتمد ⇒ نقل متوسط البنشر والانكماش للحوض */
    protected static function labReport(LabReport $lab): void
    {
        if (!$lab->consignment) return;

        $lab->recalc();
        $c = $lab->consignment;

        $c->forceFill([
            'avg_gsm'          => $lab->avg_gsm,
            'shrink_len_pct'   => $lab->avg_shrink_len_pct,
            'shrink_width_pct' => $lab->avg_shrink_width_pct,
            'color_match_ok'   => $lab->color_match_ok,
            // الحوض بيفضل محجوز — الإفراج بإذن الاستلام بس
            'status'           => $c->status === 'rejected'
                                    ? 'rejected'
                                    : ($c->min_width_cm ? 'lab_done' : 'under_inspection'),
        ])->save();
    }

    /** أمر شغل معتمد ⇒ خصم الكيلوهات من الحوض + حجز الأتواب */
    protected static function workOrder(WorkOrder $wo): void
    {
        DB::transaction(function () use ($wo) {
            $wo->consignment?->recalcRemaining();
            $wo->consignment?->forceFill(['status' => 'in_production'])->save();

            // تسجيل الإكسسوارات المطلوبة
            foreach (PlanningEngine::explodeAccessories($wo) as $accId => $row) {
                \App\Models\AccessoryRequirement::updateOrCreate(
                    ['work_order_id' => $wo->id, 'accessory_id' => $accId],
                    ['required_qty' => $row['required'], 'shortage_qty' => $row['shortage']]
                );
            }
        });
    }

    /** بيان قص معتمد ⇒ تحديث المقصوص + حساب الانحراف */
    protected static function cutDeclaration(CutDeclaration $cd): void
    {
        DB::transaction(function () use ($cd) {
            $cd->load('lines');
            $wo = $cd->workOrder;
            if (!$wo) return;

            foreach ($cd->lines as $line) {
                $woLine = $wo->lines()
                    ->where('product_model_id', $line->product_model_id)
                    ->where('size_id', $line->size_id)
                    ->first();

                if ($woLine) {
                    $woLine->increment('cut_qty', (int) $line->qty);
                    $woLine->refresh()->syncRemaining();
                }
            }

            $wo->refresh()->recalc();

            $v = PlanningEngine::variance((float) $wo->expected_pieces, (float) $wo->cut_pieces);

            $wo->forceFill([
                'actual_spread_length_m' => $cd->actual_spread_length_m,
                'actual_plies'           => $cd->actual_plies,
                'variance_pct'           => $v['pct'],
                'variance_flag'          => $v['flag'],
                'status'                 => 'in_production',
            ])->save();

            if ($v['flag'] === 'danger') {
                Notifier::broadcastToRole('planner', 'variance_alert',
                    'انحراف خارج الحدود',
                    'أمر الشغل ' . $wo->wo_no . ' انحرافه ' . $v['pct'] . '% — محتاج مراجعة.',
                    null, 'danger');
            }
        });
    }

    /** استلام إنتاج معتمد ⇒ تحديث المستلم + حركة مخزون + قفل تلقائي */
    protected static function productionReceipt(ProductionReceipt $pr): void
    {
        DB::transaction(function () use ($pr) {
            $pr->load('lines');
            $wo = $pr->workOrder;

            foreach ($pr->lines as $line) {
                if ($wo) {
                    $woLine = $wo->lines()
                        ->where('product_model_id', $line->product_model_id)
                        ->where('size_id', $line->size_id)
                        ->first();
                    if ($woLine) {
                        $woLine->increment('received_qty', (int) $line->qty);
                        $woLine->refresh()->syncRemaining();
                    }
                }

                StockMovement::create([
                    'moved_at'         => $pr->doc_date,
                    'warehouse_id'     => $pr->warehouse_id,
                    'item_type'        => 'finished',
                    'product_model_id' => $line->product_model_id,
                    'size_id'          => $line->size_id,
                    'color_id'         => $line->color_id,
                    'consignment_id'   => $wo?->consignment_id,
                    'direction'        => 'in',
                    'qty'              => $line->qty,
                    'unit'             => 'قطعة',
                    'source_type'      => ProductionReceipt::class,
                    'source_id'        => $pr->id,
                    'reference'        => $pr->doc_no,
                    'created_by'       => auth()->id(),
                ]);
            }

            if ($wo) {
                $wo->refresh()->recalc();
                $wo->refresh();

                $status = $wo->outstanding_pieces <= 0 && $wo->cut_pieces > 0
                    ? 'closed'
                    : 'partially_received';

                $wo->forceFill(['status' => $status])->save();

                if ($status === 'closed') {
                    $wo->consignment?->recalcRemaining();
                }
            }
        });
    }
}
