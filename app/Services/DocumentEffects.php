<?php

namespace App\Services;

use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\FabricInspection;
use App\Models\GoodsReceipt;
use App\Models\LabReport;
use App\Models\ProductionReceipt;
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
            $doc instanceof GoodsReceipt      => self::goodsReceipt($doc),
            $doc instanceof StockAddition     => self::stockAddition($doc),
            $doc instanceof FabricInspection  => self::inspection($doc),
            $doc instanceof LabReport         => self::labReport($doc),
            $doc instanceof WorkOrder         => self::workOrder($doc),
            $doc instanceof CutDeclaration    => self::cutDeclaration($doc),
            $doc instanceof ProductionReceipt => self::productionReceipt($doc),
            default => null,
        };
    }

    /** إذن استلام معتمد ⇒ تحديث المستلم في أمر الشراء */
    protected static function goodsReceipt(GoodsReceipt $gr): void
    {
        DB::transaction(function () use ($gr) {
            $gr->load('lines', 'purchaseOrder.lines');

            if ($po = $gr->purchaseOrder) {
                foreach ($gr->lines as $line) {
                    $poLine = $po->lines->first(fn ($l) =>
                        $l->fabric_type_id == $line->fabric_type_id && $l->color_id == $line->color_id
                    );
                    if ($poLine) {
                        // الوحدات: أمر الشراء بالطن، الاستلام بالكيلو
                        $qty = strtolower($line->unit) === 'طن' ? (float) $line->qty : (float) $line->qty / 1000;
                        $poLine->increment('received_qty', $qty);
                    }
                }
                $po->refresh()->load('lines');
                $fully = $po->lines->every(fn ($l) => (float) $l->received_qty >= (float) $l->min_allowed_qty);
                $po->forceFill(['status' => $fully ? 'received' : 'partially_received'])->save();
            }
        });
    }

    /** إذن إضافة معتمد ⇒ حركة مخزون داخلة */
    protected static function stockAddition(StockAddition $sa): void
    {
        DB::transaction(function () use ($sa) {
            $sa->load('lines');
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
                    'qty'            => $line->qty,
                    'unit'           => $line->unit,
                    'source_type'    => StockAddition::class,
                    'source_id'      => $sa->id,
                    'reference'      => $sa->doc_no,
                    'created_by'     => auth()->id(),
                ]);

                if ($line->accessory_id && $line->accessory) {
                    $line->accessory->increment('stock_qty', (float) $line->qty);
                }
            }
        });
    }

    /** فحص معتمد ⇒ نقل أقل عرض ومتوسط العرض ونسبة العيوب للحوض */
    protected static function inspection(FabricInspection $insp): void
    {
        if (!$insp->consignment) return;

        $insp->recalc();
        $c = $insp->consignment;

        $c->forceFill([
            'min_width_cm' => $insp->min_width_cm,
            'avg_width_cm' => $insp->avg_width_cm,
            'max_width_cm' => $insp->max_width_cm,
            'defect_pct'   => $insp->defect_pct,
            'status'       => $c->avg_gsm ? 'approved' : 'lab_pending',
        ])->save();
    }

    /** تقرير معمل معتمد ⇒ نقل متوسط البنشر والانكماش للحوض */
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
            'status'           => $c->min_width_cm ? 'approved' : 'inspected',
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
