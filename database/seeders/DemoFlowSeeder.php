<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\CutDeclarationLine;
use App\Models\FabricInspection;
use App\Models\FabricRoll;
use App\Models\FabricType;
use App\Models\Factory;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InspectionRoll;
use App\Models\LabGsmReading;
use App\Models\LabReport;
use App\Models\Marker;
use App\Models\MarkerLine;
use App\Models\MarkerRequest;
use App\Models\ProductModel;
use App\Models\ProductionReceipt;
use App\Models\ProductionReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SafetyStock;
use App\Models\SalesSnapshot;
use App\Models\Size;
use App\Models\StockAddition;
use App\Models\StockAdditionLine;
use App\Models\StockSnapshot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use App\Services\ApprovalEngine;
use App\Services\DocumentEffects;
use App\Services\ForecastService;
use App\Services\PlanningEngine;
use Illuminate\Database\Seeder;

/**
 * داتا ديمو بتمشي الفلو كامل من أوله لآخره:
 *
 *   طلب شراء → إذن استلام (بيولّد الحوض والأتواب) → إذن إضافة
 *   → تقرير فحص → تقرير معمل → طلب ماركر → ماركر
 *   → أمر شغل (بالحسبة) → بيان قص → استلامات إنتاج → قفل
 *
 * وكمان: مبيعات وأرصدة ونسب ألوان وفوركاست ومخزون أمان،
 * عشان شاشة أيام التغطية تشتغل من أول لحظة.
 */
class DemoFlowSeeder extends Seeder
{
    public function run(): void
    {
        $planner  = User::where('username', 'planner')->first();
        $store    = User::where('username', 'store')->first();
        $lab      = User::where('username', 'lab')->first();
        $qc       = User::where('username', 'qc')->first();
        $pattern  = User::where('username', 'pattern')->first();
        $purch    = User::where('username', 'moaz')->first();

        $supplier = Supplier::where('code', '196')->first();
        $marchelo = Supplier::where('code', 'MRC')->first();
        $whOubour = Warehouse::where('code', 'OBR')->first();
        $whFin    = Warehouse::where('code', 'FIN')->first();
        $fabric   = FabricType::where('code', 'VSC-FL')->first();
        $black    = Color::where('code', 'BLK-001')->first();
        $offWhite = Color::where('code', 'OFW-001')->first();
        $factory  = Factory::where('code', 'SIN')->first();

        // ═══ 1) طلب الشراء ═══════════════════════════════════════
        $po = PurchaseOrder::firstOrCreate(['po_no' => 'PO-2026-00100'], [
            'po_date'        => now()->subDays(60)->toDateString(),
            'supplier_id'    => $supplier->id,
            'warehouse_id'   => $whOubour->id,
            'employee_id'    => $purch?->id,
            'delivery_place' => 'العبور',
            'delivery_date'  => now()->subDays(35)->toDateString(),
            'discount_pct'   => 0,
            'tax_pct'        => 14,
            'status'         => 'approved',
            'created_by'     => $purch?->id,
        ]);

        if ($po->lines()->count() === 0) {
            $rows = [
                [$whiteId = Color::where('code','WHT-001')->value('id'), 20, 'أبيض'],
                [$offWhite->id, 30, 'أوف وايت'],
                [$black->id,    40, 'أسود'],
                [Color::where('code','BEG-010')->value('id'), 25, 'ألوان'],
            ];
            foreach ($rows as $i => [$colorId, $qty, $label]) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'line_no'        => $i + 1,
                    'color_id'       => $colorId,
                    'fabric_type_id' => $fabric->id,
                    'qty'            => $qty,
                    'unit'           => 'طن',
                    'unit_price'     => 0,
                    'line_total'     => 0,
                    'tolerance_pct'  => 5,
                    'notes'          => $i === 1 ? 'وزن المقطع من 190 جرام الى 210 جرام' : null,
                ]);
            }
            $po->refresh()->recalcTotals();
        }

        // ═══ 2) إذن استلام خام ⇒ بيولّد الحوض والأتواب ═══════════
        $gr = GoodsReceipt::firstOrCreate(['doc_no' => 'GR-2026-00001'], [
            'paper_serial'      => '1001546',
            'doc_date'          => now()->subDays(30)->toDateString(),
            'warehouse_id'      => $whOubour->id,
            'supplier_id'       => $supplier->id,
            'purchase_order_id' => $po->id,
            'supplier_rep'      => 'مندوب مالك الدملشي',
            'status'            => 'approved',
            'created_by'        => $store?->id,
        ]);

        $consignment = Consignment::firstOrCreate(['consignment_no' => 'SL30-090826-196-00'], [
            'arrival_date'      => $gr->doc_date,
            'purchase_order_id' => $po->id,
            'supplier_id'       => $supplier->id,
            'fabric_type_id'    => $fabric->id,
            'color_id'          => $black->id,
            'warehouse_id'      => $whOubour->id,
            'total_kg'          => 1180,
            'rolls_count'       => 7,
            'total_length_m'    => 6174,       // من تقرير الفحص الفعلي
            'remaining_kg'      => 1180,
            'status'            => 'received',
            'created_by'        => $store?->id,
        ]);

        if ($gr->lines()->count() === 0) {
            GoodsReceiptLine::create([
                'goods_receipt_id' => $gr->id,
                'item_code'        => '17910297',
                'fabric_type_id'   => $fabric->id,
                'color_id'         => $black->id,
                'unit'             => 'كجم',
                'width_cm'         => 185,
                'rolls_count'      => 7,
                'qty'              => 1180,
                'consignment_no'   => $consignment->consignment_no,
            ]);
            $gr->refresh()->recalcTotals();
            $gr->forceFill(['consignment_id' => $consignment->id])->save();
        }

        // الأتواب — من تقرير الفحص: 7 أتواب، الإجمالي 6174 متر
        if ($consignment->rolls()->count() === 0) {
            $lengths = [885, 885, 885, 885, 885, 885, 864];
            foreach ($lengths as $i => $len) {
                FabricRoll::create([
                    'consignment_id' => $consignment->id,
                    'roll_no'        => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'length_m'       => $len,
                    'width_cm'       => 185,
                    'net_kg'         => round(1180 / 7, 3),
                    'is_inspected'   => true,
                    'status'         => 'in_stock',
                ]);
            }
        }

        // ═══ 3) إذن إضافة ════════════════════════════════════════
        $sa = StockAddition::firstOrCreate(['doc_no' => 'SA-2026-00001'], [
            'paper_serial'     => '41456',
            'doc_date'         => now()->subDays(29)->toDateString(),
            'supplier_id'      => $supplier->id,
            'warehouse_id'     => Warehouse::where('code','043')->value('id'),
            'goods_receipt_id' => $gr->id,
            'consignment_id'   => $consignment->id,
            'consignment_no'   => 'BUPL-090826-043-00',
            'status'           => 'approved',
            'created_by'       => $store?->id,
        ]);
        if ($sa->lines()->count() === 0) {
            StockAdditionLine::create([
                'stock_addition_id' => $sa->id,
                'item_code'      => '17910297',
                'item_name'      => 'سنجل ليكرا أسود',
                'fabric_type_id' => $fabric->id,
                'color_id'       => $black->id,
                'qty'            => 1180,
                'unit'           => 'كجم',
            ]);
            $sa->refresh()->recalcTotals();
        }

        // ═══ 4) تقرير فحص القماش ═════════════════════════════════
        $insp = FabricInspection::firstOrCreate(['doc_no' => 'FI-2026-00001'], [
            'paper_serial'   => '04619',
            'doc_date'       => now()->subDays(28)->toDateString(),
            'consignment_id' => $consignment->id,
            'fabric_type_id' => $fabric->id,
            'color_id'       => $black->id,
            'supplier_id'    => $marchelo?->id ?? $supplier->id,
            'inspector_id'   => $qc?->id,
            'total_rolls'    => 7,
            'result'         => 'accepted',
            'status'         => 'approved',
            'created_by'     => $qc?->id,
        ]);

        if ($insp->rolls()->count() === 0) {
            // الأعراض الفعلية من الورقة: كلها 185 تقريبًا مع فروق بسيطة
            $data = [
                ['001', 885, 185, 195, 2, 'بوجرسو + برادة خفيفة'],
                ['002', 885, 184, 193, 1, ''],
                ['003', 885, 185, 197, 0, ''],
                ['004', 885, 183, 204, 1, ''],
                ['005', 885, 185, 195, 0, ''],
                ['006', 885, 184, 197, 1, ''],
                ['007', 864, 185, 195, 0, ''],
            ];
            foreach ($data as [$no, $len, $w, $gsm, $def, $desc]) {
                InspectionRoll::create([
                    'fabric_inspection_id' => $insp->id,
                    'roll_no'       => $no,
                    'length_m'      => $len,
                    'width_cm'      => $w,
                    'gsm'           => $gsm,
                    'defects_count' => $def,
                    'defect_pct'    => round(($def / $len) * 100, 3),
                    'defect_desc'   => $desc ?: null,
                ]);
            }
            $insp->refresh()->recalc();
        }
        DocumentEffects::onApproved($insp->refresh());

        // ═══ 5) تقرير المعمل ═════════════════════════════════════
        $labRep = LabReport::firstOrCreate(['doc_no' => 'LB-2026-00001'], [
            'paper_serial'        => '002192',
            'doc_date'            => now()->subDays(27)->toDateString(),
            'consignment_id'      => $consignment->id,
            'supplier_id'         => $marchelo?->id ?? $supplier->id,
            'fabric_type_id'      => $fabric->id,
            'color_id'            => $black->id,
            'technician_id'       => $lab?->id,
            's1_shrink_len_pct'   => 3.0,
            's1_shrink_width_pct' => 2.0,
            's2_shrink_len_pct'   => 3.5,
            's2_shrink_width_pct' => 2.0,
            'color_match_ok'      => true,
            'status'              => 'approved',
            'created_by'          => $lab?->id,
        ]);

        if ($labRep->readings()->count() === 0) {
            // قراءات البنشر الفعلية من الورقة
            foreach ([195, 192, 197, 203, 195, 197, 195] as $i => $gsm) {
                LabGsmReading::create([
                    'lab_report_id' => $labRep->id,
                    'roll_no'       => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'gsm'           => $gsm,
                ]);
            }
            $labRep->refresh()->recalc();
        }
        DocumentEffects::onApproved($labRep->refresh());

        $consignment->refresh()->forceFill(['status' => 'approved'])->save();

        // ═══ 6) طلب ماركر + الماركر ══════════════════════════════
        $mr = MarkerRequest::firstOrCreate(['doc_no' => 'MR-2026-00001'], [
            'doc_date'         => now()->subDays(26)->toDateString(),
            'consignment_id'   => $consignment->id,
            'factory_id'       => $factory->id,
            'fabric_width_cm'  => $consignment->min_width_cm ?: 183,
            'requested_models' => 'نص بادي كات + بادي سابرينا مقاس 1 و 2 + بنطلون + تلبيسة — كلهم نفس الخامة',
            'assigned_to'      => $pattern?->id,
            'needed_by'        => now()->subDays(24)->toDateString(),
            'status'           => 'delivered',
            'created_by'       => $planner?->id,
        ]);

        $marker = Marker::firstOrCreate(['code' => 'MK-2026-00001'], [
            'name'                  => 'ميني ماركر 4 موديلات — عرض 183',
            'marker_request_id'     => $mr->id,
            'factory_id'            => $factory->id,
            'created_by_patternist' => $pattern?->id,
            'fabric_width_cm'       => 183,
            'marker_width_cm'       => 181,
            'spread_length_m'       => 3.07,
            'pieces_per_spread'     => 10,
            'efficiency_pct'        => 86.5,
            'status'                => 'approved',
            'is_active'             => true,
        ]);

        if ($marker->lines()->count() === 0) {
            $body = ProductModel::where('code','BODY-001')->first();
            $sab  = ProductModel::where('code','SAB-002')->first();
            $pnt  = ProductModel::where('code','PNT-003')->first();
            $tlb  = ProductModel::where('code','TLB-004')->first();
            $s1   = Size::where('code','S')->value('id');
            $s2   = Size::where('code','M')->value('id');

            // الفرشة فيها 4 موديلات — البادي قطعتين لأن الطلب عليه أكتر
            foreach ([
                [$body->id, $s1, 2], [$body->id, $s2, 2],
                [$sab->id,  $s1, 2], [$sab->id,  $s2, 2],
                [$pnt->id,  $s2, 1], [$tlb->id,  $s2, 1],
            ] as [$mid, $sid, $qty]) {
                MarkerLine::create([
                    'marker_id' => $marker->id, 'product_model_id' => $mid,
                    'size_id' => $sid, 'qty_per_spread' => $qty,
                ]);
            }
            $marker->refresh()->recalcPieces();
        }
        $marker->refresh();

        // ═══ 7) أمر الشغل + الحسبة ═══════════════════════════════
        $allocated = 800.0;

        $wo = WorkOrder::firstOrCreate(['wo_no' => 'WO-2026-00001'], [
            'wo_date'        => now()->subDays(25)->toDateString(),
            'consignment_id' => $consignment->id,
            'marker_id'      => $marker->id,
            'factory_id'     => $factory->id,
            'due_date'       => now()->subDays(10)->toDateString(),
            'allocated_kg'   => $allocated,
            'allocated_rolls'=> 5,
            'status'         => 'in_production',
            'created_by'     => $planner?->id,
        ]);

        $calc = PlanningEngine::forWorkOrder($consignment->refresh(), $marker, $allocated);

        $wo->forceFill([
            'input_min_width_cm'      => $consignment->min_width_cm,
            'input_avg_gsm'           => $consignment->avg_gsm,
            'input_spread_length_m'   => $marker->spread_length_m,
            'input_pieces_per_spread' => $marker->pieces_per_spread,
            'ply_weight_kg'           => $calc['ply_weight_kg']   ?? null,
            'kg_per_piece'            => $calc['kg_per_piece']    ?? null,
            'expected_plies'          => $calc['expected_plies']  ?? null,
            'expected_pieces'         => $calc['expected_pieces'] ?? null,
        ])->save();

        if ($wo->lines()->count() === 0) {
            $spreads = (int) floor(($calc['expected_pieces'] ?? 0) / max(1, $marker->pieces_per_spread));
            foreach ($marker->lines as $ml) {
                WorkOrderLine::create([
                    'work_order_id'    => $wo->id,
                    'product_model_id' => $ml->product_model_id,
                    'size_id'          => $ml->size_id,
                    'qty_per_spread'   => $ml->qty_per_spread,
                    'planned_qty'      => $spreads * (int) $ml->qty_per_spread,
                ]);
            }
        }

        foreach (PlanningEngine::explodeAccessories($wo->refresh()) as $accId => $r) {
            \App\Models\AccessoryRequirement::updateOrCreate(
                ['work_order_id' => $wo->id, 'accessory_id' => $accId],
                ['required_qty' => $r['required'], 'shortage_qty' => $r['shortage']]
            );
        }
        $consignment->refresh()->recalcRemaining();

        // ═══ 8) بيان القص — المصنع فرش على 3.09 بدل 3.07 ══════════
        $cd = CutDeclaration::firstOrCreate(['doc_no' => 'CD-2026-00001'], [
            'doc_date'               => now()->subDays(18)->toDateString(),
            'work_order_id'          => $wo->id,
            'factory_id'             => $factory->id,
            'actual_spread_length_m' => 3.09,
            'actual_plies'           => 286,
            'used_kg'                => 795,
            'status'                 => 'approved',
            'variance_reason'        => 'جزء من القماش طلع أعرض شوية واتعمل له تعشيقة تانية.',
            'created_by'             => User::where('username','follow')->value('id'),
        ]);

        if ($cd->lines()->count() === 0) {
            // الفعلي أقل من المتوقع بحوالي 3% — داخل النطاق الطبيعي
            foreach ($wo->lines as $l) {
                CutDeclarationLine::create([
                    'cut_declaration_id' => $cd->id,
                    'product_model_id'   => $l->product_model_id,
                    'size_id'            => $l->size_id,
                    'qty'                => (int) floor($l->planned_qty * 0.97),
                ]);
            }
            $cd->refresh()->load('lines');
            $total = (int) $cd->lines->sum('qty');
            $v = PlanningEngine::variance((float) $wo->expected_pieces, (float) $total);
            $cd->forceFill([
                'total_pieces'        => $total,
                'actual_kg_per_piece' => $total > 0 ? round(795 / $total, 5) : null,
                'variance_pct'        => $v['pct'],
                'variance_flag'       => $v['flag'],
            ])->saveQuietly();

            DocumentEffects::onApproved($cd->refresh());
        }

        // ═══ 9) استلامات الإنتاج — جزئية ومتكررة ═════════════════
        $wo->refresh()->load('lines');

        $pr1 = ProductionReceipt::firstOrCreate(['doc_no' => 'PR-2026-00001'], [
            'doc_date'      => now()->subDays(12)->toDateString(),
            'work_order_id' => $wo->id,
            'factory_id'    => $factory->id,
            'warehouse_id'  => $whFin->id,
            'status'        => 'approved',
            'created_by'    => $store?->id,
        ]);

        if ($pr1->lines()->count() === 0) {
            foreach ($wo->lines as $l) {
                ProductionReceiptLine::create([
                    'production_receipt_id' => $pr1->id,
                    'product_model_id'      => $l->product_model_id,
                    'size_id'               => $l->size_id,
                    'color_id'              => $black->id,
                    'qty'                   => (int) floor($l->cut_qty * 0.6),
                ]);
            }
            $pr1->refresh()->recalcTotals();
            DocumentEffects::onApproved($pr1->refresh());
        }

        $wo->refresh()->load('lines');

        $pr2 = ProductionReceipt::firstOrCreate(['doc_no' => 'PR-2026-00002'], [
            'doc_date'      => now()->subDays(4)->toDateString(),
            'work_order_id' => $wo->id,
            'factory_id'    => $factory->id,
            'warehouse_id'  => $whFin->id,
            'status'        => 'approved',
            'created_by'    => $store?->id,
        ]);

        if ($pr2->lines()->count() === 0) {
            foreach ($wo->lines as $l) {
                ProductionReceiptLine::create([
                    'production_receipt_id' => $pr2->id,
                    'product_model_id'      => $l->product_model_id,
                    'size_id'               => $l->size_id,
                    'color_id'              => $black->id,
                    'qty'                   => (int) $l->remaining_qty,   // الباقي كله ⇒ الأمر هيتقفل
                ]);
            }
            $pr2->refresh()->recalcTotals();
            DocumentEffects::onApproved($pr2->refresh());
        }

        // ═══ 10) حوض تاني لسه في المنتصف — عشان الشاشات ما تبقاش فاضية ═══
        $c2 = Consignment::firstOrCreate(['consignment_no' => 'SL30-120826-196-01'], [
            'arrival_date'   => now()->subDays(6)->toDateString(),
            'supplier_id'    => $supplier->id,
            'fabric_type_id' => $fabric->id,
            'color_id'       => $offWhite->id,
            'warehouse_id'   => $whOubour->id,
            'total_kg'       => 940,
            'rolls_count'    => 6,
            'total_length_m' => 5100,
            'remaining_kg'   => 940,
            'status'         => 'received',
            'created_by'     => $store?->id,
        ]);
        if ($c2->rolls()->count() === 0) {
            for ($i = 1; $i <= 6; $i++) {
                FabricRoll::create([
                    'consignment_id' => $c2->id,
                    'roll_no'        => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'length_m'       => 850,
                    'net_kg'         => round(940 / 6, 3),
                    'status'         => 'in_stock',
                ]);
            }
        }

        // ═══ 11) مستند مستني اعتماد فعلي — عشان صندوق الاعتمادات يشتغل ═══
        $po2 = PurchaseOrder::firstOrCreate(['po_no' => 'PO-2026-00101'], [
            'po_date'        => now()->subDays(2)->toDateString(),
            'supplier_id'    => $marchelo?->id ?? $supplier->id,
            'warehouse_id'   => $whOubour->id,
            'employee_id'    => $purch?->id,
            'delivery_place' => 'العبور',
            'tax_pct'        => 14,
            'status'         => 'draft',
            'created_by'     => $purch?->id,
        ]);
        if ($po2->lines()->count() === 0) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $po2->id, 'line_no' => 1,
                'color_id' => $offWhite->id, 'fabric_type_id' => $fabric->id,
                'qty' => 15, 'unit' => 'طن', 'unit_price' => 0, 'line_total' => 0, 'tolerance_pct' => 5,
            ]);
            $po2->refresh()->recalcTotals();

            if ($purch) {
                ApprovalEngine::submit($po2->refresh(), $purch);
            }
        }

        // ═══ 12) مبيعات وأرصدة ونسب وفوركاست ═════════════════════
        $this->seedSalesAndStock();
        $this->seedForecast($planner?->id);
    }

    /** لقطات مبيعات آخر 3 شهور + آخر رصيد — عشان شاشة التغطية تشتغل */
    private function seedSalesAndStock(): void
    {
        if (SalesSnapshot::count() > 0) return;

        $models = ProductModel::where('is_active', true)->get();
        $volumes = ['BODY-001' => 9000, 'SAB-002' => 6000, 'PNT-003' => 3000, 'TLB-004' => 2000];

        // مبيعات شهرية لآخر 14 شهر (عشان الفوركاست يلاقي سنة أساس)
        for ($m = 14; $m >= 0; $m--) {
            $from = now()->subMonths($m)->startOfMonth();
            $to   = now()->subMonths($m)->endOfMonth();

            foreach ($models as $model) {
                $base = $volumes[$model->code] ?? 2000;
                $qty  = (int) round($base * (0.85 + (($m * 7) % 30) / 100));

                SalesSnapshot::create([
                    'pulled_at'        => $to->copy()->addDays(5)->toDateString(),
                    'period_from'      => $from->toDateString(),
                    'period_to'        => $to->toDateString(),
                    'product_model_id' => $model->id,
                    'qty_pcs'          => $qty,
                    'raw_qty'          => $qty,
                    'raw_unit'         => 'قطعة',
                    'source'           => 'quickbooks_excel',
                    'revision'         => 1,
                    'is_locked'        => $m > 0,
                ]);
            }
        }

        // مبيعات آخر 30 يوم (لحساب المتوسط اليومي)
        $from = now()->subDays(30);
        foreach ($models as $model) {
            $base = $volumes[$model->code] ?? 2000;
            SalesSnapshot::create([
                'pulled_at'        => now()->toDateString(),
                'period_from'      => $from->toDateString(),
                'period_to'        => now()->toDateString(),
                'product_model_id' => $model->id,
                'qty_pcs'          => $base,
                'raw_qty'          => $base,
                'raw_unit'         => 'قطعة',
                'source'           => 'quickbooks_excel',
                'revision'         => 1,
                'is_locked'        => false,
            ]);
        }

        // آخر رصيد — واحد منهم متعمّد يكون منخفض عشان يبان أحمر في التغطية
        $wh = Warehouse::where('code', 'FIN')->first();
        $stock = ['BODY-001' => 2400, 'SAB-002' => 9000, 'PNT-003' => 700, 'TLB-004' => 6500];

        foreach ($models as $model) {
            StockSnapshot::create([
                'pulled_at'        => now()->toDateString(),
                'warehouse_id'     => $wh?->id,
                'product_model_id' => $model->id,
                'qty_pcs'          => $stock[$model->code] ?? 1000,
                'reliability'      => 'book',
                'source'           => 'excel',
            ]);
        }

        // مخزون أمان
        foreach ($models as $model) {
            SafetyStock::updateOrCreate(
                ['product_model_id' => $model->id, 'color_id' => null],
                ['qty_pcs' => 500, 'cover_days' => 15, 'notes' => 'مبدئي — يتراجع كل ربع سنة']
            );
        }
    }

    /** نسب ألوان مبدئية + فوركاست للسنة الجاية */
    private function seedForecast(?int $userId): void
    {
        if (\App\Models\ColorRatio::count() > 0) return;

        $ratios = [
            'BLK-001' => 45,   // الأسود هو الأكبر
            'WHT-001' => 25,
            'OFW-001' => 20,
            'BEG-010' => 10,
        ];

        $year = now()->year;

        foreach (ProductModel::where('is_active', true)->get() as $model) {
            foreach ($ratios as $code => $pct) {
                $colorId = Color::where('code', $code)->value('id');
                if (!$colorId) continue;

                \App\Models\ColorRatio::updateOrCreate(
                    ['product_model_id' => $model->id, 'color_id' => $colorId, 'year' => $year, 'month' => null],
                    ['ratio_pct' => $pct, 'source' => 'manual', 'updated_by' => $userId,
                     'notes' => 'نسب مبدئية — المصدر صرف المخزن، محتاجة مراجعة']
                );
            }
        }

        // فوركاست السنة الجاية لأكبر موديلين بنمو 10%
        foreach (['BODY-001', 'SAB-002'] as $code) {
            $model = ProductModel::where('code', $code)->first();
            if (!$model) continue;

            $r = [];
            foreach ($ratios as $cCode => $pct) {
                $cid = Color::where('code', $cCode)->value('id');
                if ($cid) $r[$cid] = $pct;
            }

            ForecastService::generate($model->id, $year + 1, 10, $r, $userId);
        }
    }
}
