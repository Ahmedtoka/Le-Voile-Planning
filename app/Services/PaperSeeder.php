<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricInspection;
use App\Models\FabricRoll;
use App\Models\FabricType;
use App\Models\Factory;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptRejection;
use App\Models\InspectionRoll;
use App\Models\LabGsmReading;
use App\Models\LabReport;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\ModelBom;
use App\Models\ProductModel;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Size;
use App\Models\StockAddition;
use App\Models\StockAdditionLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderFabric;
use App\Models\WorkOrderLine;
use Illuminate\Support\Facades\DB;

/**
 * ══════════════════════════════════════════════════════════════════
 *  الورق الحقيقي
 * ══════════════════════════════════════════════════════════════════
 *
 * بيدخّل المستندات دي بأرقامها الفعلية زي ما هي على الورق:
 *
 *   • طلب شراء 107            (4/7/2026)  — مياي بالكيلو + تل مستورد بالمتر
 *   • إذن استلام خام 1000885  (9/7/2026)  — أنس تكس · MAMI-080726-127-00
 *                                            + 5 قرارات رفض وتعليق
 *   • تقرير فحص قماش 04379    (8/7/2026)  — 3 أتواب · 61.7 كجم · رفض 2 توب
 *   • أمر شغل KB106           (26/7/2026) — الخطيب · خامتين (تل + مياي)
 *   • إذن صرف خام 1303774     (27/7/2026) — الخطيب · KB106 و KB107
 *
 * الغرض: تقارن كل شاشة بالورقة اللي في إيدك وتتأكد إن الأرقام مطابقة.
 */
class PaperSeeder
{
    private $log;

    public function __construct(?callable $log = null)
    {
        $this->log = $log ?? fn (string $m) => null;
    }

    private function say(string $m): void { ($this->log)($m); }

    public function run(): array
    {
        $t0 = microtime(true);

        (new DemoDataService($this->log))->reset();

        $this->say('البيانات الأساسية اللي الورق بيشاور عليها…');
        $this->master();

        $this->say('طلب شراء 107…');
        $po = $this->purchaseOrder();

        $this->say('الرسائل والاستلام والفحص…');
        [$mami, $tufr] = $this->consignments($po);

        $this->say('أمر الشغل KB106 و KB107…');
        [$kb106, $kb107] = $this->workOrders($mami, $tufr);

        $this->say('إذن صرف الخام 1303774…');
        $this->materialIssue($kb106, $kb107, $mami, $tufr);

        $this->say('أمر الشغل KY81 — شيت الجوب أوردر (موديلين في فرشة واحدة)…');
        $this->ky81();

        return [
            'موردين'        => Supplier::count(),
            'مصانع'         => Factory::count(),
            'ألوان'         => Color::count(),
            'خامات'         => FabricType::count(),
            'طلبات شراء'    => PurchaseOrder::count(),
            'أذون إضافة'    => StockAddition::count(),
            'أحواض'         => Consignment::count(),
            'تقارير فحص'    => FabricInspection::count(),
            'رفض وتعليق'    => GoodsReceiptRejection::count(),
            'أذون استلام'   => GoodsReceipt::count(),
            'أوامر شغل'     => WorkOrder::count(),
            'أذون صرف خام'  => MaterialIssue::count(),
            'الوقت'         => round(microtime(true) - $t0, 1) . ' ث',
        ];
    }

    // ── البيانات الأساسية ───────────────────────────────────────

    private function master(): void
    {
        foreach ([
            ['127', 'أنس تكس',       'أ/ أنس',   'العبور',      'آجل 30 يوم'],
            ['196', 'مالك الدملشي',  'أ/ مالك',  'العبور',      'آجل 60 يوم'],
            ['043', 'الصنع تكس',     'أ/ سيد',   'العاشر',      'آجل 30 يوم'],
            ['108', 'مورد التل',     'أ/ سامح',  'القاهرة',     'نقدي'],
            ['MRC', 'مارشيلو',       'أ/ مارك',  'المحلة',      'نقدي'],
        ] as [$code, $name, $person, $addr, $terms]) {
            Supplier::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person, 'address' => $addr,
                'payment_terms' => $terms, 'is_active' => true,
            ]);
        }

        foreach ([
            ['KB',  'الخطيب', 4000, 6],
            ['SIN', 'سيني',   4000, 7],
            ['KHD', 'خالد',   2500, 5],
            ['KY',  'كيان',   3000, 6],
        ] as [$c, $n, $cap, $cycle]) {
            Factory::updateOrCreate(['code' => $c], [
                'name' => $n, 'daily_capacity_pcs' => $cap, 'avg_cycle_days' => $cycle, 'is_active' => true,
            ]);
        }

        foreach ([
            ['OBR', 'مخزن العبور',       'fabric'],
            ['ACC', 'مخزن الإكسسوارات',  'accessories'],
            ['FIN', 'مخزن المنتج التام', 'finished'],
            ['RTN', 'مخزن المرتجعات',    'other'],
        ] as [$c, $n, $t]) {
            Warehouse::updateOrCreate(['code' => $c], ['name' => $n, 'type' => $t, 'is_active' => true]);
        }

        // مياي بتتحسب بالوزن · التل بيتحسب بالطول
        FabricType::updateOrCreate(['code' => '14810091'], [
            'name' => 'مايوه ميامي', 'composition' => 'ميامي',
            'spec_width_cm' => 156, 'spec_width_min_cm' => 150,
            'spec_gsm' => 245, 'spec_gsm_min' => 230, 'spec_gsm_max' => 260,
            'max_defect_pct' => 3, 'is_active' => true,
        ]);
        FabricType::updateOrCreate(['code' => '10610034'], [
            'name' => 'تل مستورد', 'composition' => 'تل',
            'spec_width_cm' => 150, 'spec_width_min_cm' => 145,
            'max_defect_pct' => 3, 'is_active' => true,
        ]);
        // فسكوز فل ليكرا — خامة أمر الشغل KY81 (شيت الجوب أوردر)
        FabricType::updateOrCreate(['code' => 'VSF'], [
            'name' => 'فسكوز فل ليكرا', 'composition' => 'فسكوز + ليكرا',
            'spec_width_cm' => 172, 'spec_width_min_cm' => 165,
            'spec_gsm' => 192, 'spec_gsm_min' => 180, 'spec_gsm_max' => 205,
            'max_defect_pct' => 3, 'is_active' => true,
        ]);

        // الأكواد زي ما هي على الورق
        foreach ([
            ['1831', 'أسود',       'أسود',   '#111111', true],
            ['1132', 'بيج',        'بيج',    '#d8c7a9', false],
            ['241',  'بيج 241',    'بيج',    '#d5c3a4', false],
            ['2411', 'أوف وايت',   'أبيض',   '#f2ece2', true],
            ['3794', 'كحلي',       'أزرق',   '#22314f', false],
            ['2501', 'رمادي',      'رمادي',  '#8d8d8d', false],
            ['013',  'بيج تل',     'بيج',    '#ddcdb2', false],
            ['2580', 'روز',        'وردي',   '#c98198', false],
            ['1201', 'نبيتي',      'أحمر',   '#8b2b2b', false],
            ['1842', 'أخضر',       'أخضر',   '#3f6b3f', false],
            ['6737', 'بمبي',       'وردي',   '#e0a8b8', false],
            ['1000', 'أبيض',       'أبيض',   '#ffffff', true],
            ['1500', 'منت',        'أخضر',   '#9fd8c0', false],
            ['1600', 'موف',        'بنفسجي', '#a98bbd', false],
            ['1700', 'لبني',       'أزرق',   '#a8c8e0', false],
            ['1800', 'أحمر',       'أحمر',   '#b5342b', false],
            ['3_125','جريش 3_125', 'رمادي',  '#b8b2a8', false],
        ] as [$code, $name, $family, $hex, $basic]) {
            Color::updateOrCreate(['code' => $code], [
                'name' => $name, 'family' => $family, 'hex' => $hex,
                'is_basic' => $basic, 'status' => 'active',
            ]);
        }

        foreach ([['S', 'مقاس 1', 1], ['M', 'مقاس 2', 2], ['L', 'مقاس 3', 3]] as [$c, $n, $o]) {
            Size::updateOrCreate(['code' => $c], ['name' => $n, 'sort_order' => $o, 'is_active' => true]);
        }

        // المنتج اللي على ورقة المصنع
        $m = ProductModel::updateOrCreate(['code' => 'O1014'], [
            'name' => 'طرحة تل + بونيه رباط مياي', 'category' => 'طرح',
            'fabric_type_id' => FabricType::where('code', '10610034')->value('id'),
            'pcs_per_dozen' => 12, 'is_active' => true,
        ]);
        $m->sizes()->sync(Size::pluck('id'));

        // بيانات تخص المنتج — بالظبط زي الورقة
        foreach ([
            ['TKT-MAIN', 'تكت رئيسي',   'label', 'قطعة', 1],
            ['BAG-S3',   'كيس مقاس 3',  'bag',   'قطعة', 1],
            ['BAG-6040', 'كيس 60*40',   'bag',   'كيلو', 0.00218],   // 1 كيلو لكل 459 قطعة
        ] as [$code, $name, $type, $unit, $per]) {
            $a = Accessory::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'unit' => $unit,
                'stock_qty' => 20000, 'reorder_point' => 2000, 'is_shared' => true, 'is_active' => true,
            ]);
            ModelBom::updateOrCreate(
                ['product_model_id' => $m->id, 'accessory_id' => $a->id, 'size_id' => null],
                ['qty_per_piece' => $per]
            );
        }

        // الكتالوج الحقيقي من شيت الجوب أوردر: 49 موديل + BOM + إكسسوارات بأكوادها
        JobOrderMaster::seed($this->log);

        // متوسطات موديلات KY81 — عشان توزيع الاستهلاك يتحسب زي الميتينج
        ProductModel::where('code', '113A')->update(['std_consumption_kg' => 0.090]); // تلبيسه كباسين
        ProductModel::where('code', '121A')->update(['std_consumption_kg' => 0.080]); // كويتى رباط
    }

    // ── طلب شراء 107 ────────────────────────────────────────────

    private function purchaseOrder(): PurchaseOrder
    {
        $planner = User::where('username', 'planner')->value('id');
        $purch   = User::where('username', 'moaz')->value('id');
        $fin     = User::where('username', 'finance')->value('id');

        $po = PurchaseOrder::create([
            'po_no'          => 'PO-2026-00107',
            'po_date'        => '2026-07-04',
            'supplier_id'    => Supplier::where('code', '127')->value('id'),
            'warehouse_id'   => Warehouse::where('code', 'OBR')->value('id'),
            'employee_id'    => $purch,
            'delivery_place' => 'العبور',
            'delivery_date'  => '2026-07-09',
            'payment_method' => 'آجل 30 يوم',
            'tax_pct'        => 14,
            'stage'          => 'receiving',
            'status'         => 'approved',
            'planning_note'  => 'طلب شراء 107 — مياي بالكيلو وتل مستورد بالمتر',
            'created_by'     => $planner,
            'requested_by'   => $planner, 'requested_at' => '2026-07-03',
            'sourced_by'     => $purch,   'sourced_at'   => '2026-07-04',
            'finance_by'     => $fin,     'finance_at'   => '2026-07-05',
        ]);

        $mia = FabricType::where('code', '14810091')->value('id');
        $tul = FabricType::where('code', '10610034')->value('id');

        // نفس الأسطر والكميات والوحدات زي الورقة بالظبط
        $rows = [
            ['1831', 'مياي', 300,  'كيلو'], ['2411', 'مياي', 140,  'كيلو'], ['3794', 'مياي', 100,  'كيلو'],
            ['1831', 'تل',   4000, 'متر'],  ['2411', 'تل',   1800, 'متر'],  ['3794', 'تل',   1300, 'متر'],
            ['1132', 'مياي', 100,  'كيلو'], ['2501', 'مياي', 100,  'كيلو'], ['1201', 'مياي', 120,  'كيلو'],
            ['1700', 'مياي', 30,   'كيلو'], ['2580', 'مياي', 30,   'كيلو'], ['1500', 'مياي', 45,   'كيلو'],
            ['1600', 'مياي', 30,   'كيلو'], ['1800', 'مياي', 30,   'كيلو'],
            ['1132', 'تل',   1400, 'متر'],  ['2501', 'تل',   1400, 'متر'],  ['1201', 'تل',   1600, 'متر'],
            ['1700', 'تل',   400,  'متر'],  ['2580', 'تل',   400,  'متر'],  ['1500', 'تل',   600,  'متر'],
            ['1600', 'تل',   400,  'متر'],  ['1800', 'تل',   400,  'متر'],
        ];

        foreach ($rows as $i => [$color, $kind, $qty, $unit]) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_no'        => $i + 1,
                'color_id'       => Color::where('code', $color)->value('id'),
                'fabric_type_id' => $kind === 'مياي' ? $mia : $tul,
                'qty'            => $qty,
                'unit'           => $unit,
                'unit_price'     => 0,
                'line_total'     => 0,
                'tolerance_pct'  => 5,
            ]);
        }

        $po->refresh()->recalcTotals();
        return $po;
    }

    // ── الرسائل والاستلام والفحص ────────────────────────────────

    private function consignments(PurchaseOrder $po): array
    {
        $store = User::where('username', 'store')->value('id');
        $qc    = User::where('username', 'qc')->value('id');
        $lab   = User::where('username', 'lab')->value('id');

        $obr = Warehouse::where('code', 'OBR')->value('id');
        $mia = FabricType::where('code', '14810091')->first();
        $tul = FabricType::where('code', '10610034')->first();
        $anas = Supplier::where('code', '127')->value('id');
        $tSup = Supplier::where('code', '108')->value('id');

        // ═══ رسالة المياي: MAMI-080726-127-00 ═══
        $mami = $this->consignment(
            'MAMI-080726-127-00', '2026-07-09', $anas, $mia, '1132', $obr, $po->id,
            rolls: 3, kg: 61.7, lengthM: 768, store: $store,
            saDoc: 'SA-2026-00001', saSerial: '41500', itemCode: '14810091',
            itemName: 'مايوه ميامي 1132'
        );

        // تقرير فحص 04379 — 3 أتواب بطول 256 لكل توب
        $insp = FabricInspection::create([
            'doc_no'         => 'FI-2026-04379',
            'paper_serial'   => '04379',
            'doc_date'       => '2026-07-08',
            'consignment_id' => $mami->id,
            'fabric_type_id' => $mia->id,
            'color_id'       => Color::where('code', '1132')->value('id'),
            'supplier_id'    => $anas,
            'inspector_id'   => $qc,
            'declared_rolls' => 3,
            'counted_rolls'  => 3,
            'counted_kg'     => 61.7,
            'total_rolls'    => 3,
            'result'         => 'accepted_with_notes',
            'status'         => 'approved',
            'notes'          => 'مقبول / إبراهيم محمد. تم رفض 2 توب وزنهم 8.36 كجم بيهم تل غريبة وتناسخ.',
            'created_by'     => $qc,
        ]);

        foreach ([['001', 256, 156, 245, 2, 'بوجرسو + برادة خفيفة + إخانة'],
                  ['002', 256, 156, 245, 0, ''],
                  ['003', 256, 155, 244, 0, '']] as [$no, $len, $w, $gsm, $def, $desc]) {
            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'roll_no' => $no, 'length_m' => $len, 'width_cm' => $w, 'gsm' => $gsm,
                'defects_count' => $def, 'defect_pct' => round($def / $len * 100, 3),
                'defect_desc' => $desc ?: null,
            ]);
        }
        $insp->refresh()->recalc();
        DocumentEffects::onApproved($insp->refresh());

        $labRep = LabReport::create([
            'doc_no' => 'LB-2026-00001', 'doc_date' => '2026-07-08',
            'consignment_id' => $mami->id, 'supplier_id' => $anas,
            'fabric_type_id' => $mia->id, 'color_id' => Color::where('code', '1132')->value('id'),
            'technician_id' => $lab,
            's1_shrink_len_pct' => 3, 's1_shrink_width_pct' => 2,
            's2_shrink_len_pct' => 3.5, 's2_shrink_width_pct' => 2,
            'color_match_ok' => true, 'status' => 'approved', 'created_by' => $lab,
        ]);
        foreach ([245, 246, 244] as $i => $g) {
            LabGsmReading::create(['lab_report_id' => $labRep->id,
                'roll_no' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'gsm' => $g]);
        }
        $labRep->refresh()->recalc();
        DocumentEffects::onApproved($labRep->refresh());

        // ═══ إذن استلام خام 1000885 — بالرفض والتعليق زي الورقة ═══
        $gr = GoodsReceipt::create([
            'doc_no'               => 'GR-2026-1000885',
            'paper_serial'         => '1000885',
            'doc_date'             => '2026-07-09',
            'warehouse_id'         => $obr,
            'supplier_id'          => $anas,
            'purchase_order_id'    => $po->id,
            'consignment_id'       => $mami->id,
            'fabric_inspection_id' => $insp->id,
            'supplier_rep'         => 'مندوب أنس تكس',
            'status'               => 'approved',
            'notes'                => 'أمر المشتريات 829 · كود المورد 127',
            'created_by'           => $store,
        ]);

        foreach ([['1831', 100, 2, 30.7], ['1132', 100, 3, 61.7]] as [$c, $w, $rolls, $qty]) {
            GoodsReceiptLine::create([
                'goods_receipt_id' => $gr->id,
                'item_code'      => '14810091',
                'fabric_type_id' => $mia->id,
                'color_id'       => Color::where('code', $c)->value('id'),
                'unit'           => 'كجم', 'width_cm' => $w,
                'rolls_count'    => $rolls, 'qty' => $qty,
                'consignment_no' => 'MAMI-080726-127-00',
            ]);
        }
        // القرارات الخمسة المكتوبة بالإيد على الورقة
        foreach ([
            ['rejected', '1132', null,             2, 8.36,  'quality',  'مصلحة الجودة'],
            ['on_hold',  '2580', 'اللون الروز',    0, 0,     'planning', 'لون غير مطابق — لحين الرد من إدارة التخطيط والمشتريات'],
            ['rejected', '1201', null,             6, 120.9, 'quality',  'مصلحة الجودة'],
            ['rejected', '1842', 'الحوض الأخضر',   1, 3.9,   'quality',  'مصلحة الجودة'],
            ['rejected', '6737', 'الحوض البمبي',   5, 107.1, 'quality',  'مصلحة الجودة'],
        ] as [$kind, $code, $label, $rolls, $qty, $party, $reason]) {
            GoodsReceiptRejection::create([
                'goods_receipt_id'     => $gr->id,
                'fabric_inspection_id' => $insp->id,
                'consignment_id'       => $mami->id,
                'color_id'             => Color::where('code', $code)->value('id'),
                'color_code'           => $code,
                'lot_label'            => $label,
                'kind'                 => $kind,
                'rolls_count'          => $rolls,
                'qty'                  => $qty,
                'unit'                 => 'كجم',
                'party'                => $party,
                'reason'               => $reason,
                'created_by'           => $store,
            ]);
        }

        // القرارات لازم تتسجل قبل الاعتماد — زي الشاشة، الإفراج بيخصمهم
        $gr->refresh()->recalcTotals();
        DocumentEffects::onApproved($gr->refresh());

        // ═══ رسالة التل: TUFR-230726-108-00 ═══
        $tufr = $this->consignment(
            'TUFR-230726-108-00', '2026-07-23', $tSup, $tul, '013', $obr, $po->id,
            rolls: 20, kg: 1126, lengthM: 1126, store: $store,
            saDoc: 'SA-2026-00002', saSerial: '41501', itemCode: '10610034',
            itemName: 'تل مستورد 013', unit: 'متر'
        );

        $this->quickApprove($tufr, $tul, '013', $tSup, $store, $qc, $lab, $obr, $po->id,
            docFi: 'FI-2026-04380', docLb: 'LB-2026-00002', docGr: 'GR-2026-1000886',
            serialGr: '1000886', rolls: 20, qty: 1126, unit: 'متر', width: 150);

        return [$mami->refresh(), $tufr->refresh()];
    }

    /** إذن إضافة ⇒ حوض تحت الفحص */
    private function consignment(
        string $no, string $date, ?int $supplierId, FabricType $ft, string $colorCode,
        ?int $warehouseId, int $poId, int $rolls, float $kg, float $lengthM, ?int $store,
        string $saDoc, string $saSerial, string $itemCode, string $itemName, string $unit = 'كجم'
    ): Consignment {
        $sa = StockAddition::create([
            'doc_no'            => $saDoc,
            'paper_serial'      => $saSerial,
            'doc_date'          => $date,
            'supplier_id'       => $supplierId,
            'warehouse_id'      => $warehouseId,
            'purchase_order_id' => $poId,
            'consignment_no'    => $no,
            'status'            => 'approved',
            'created_by'        => $store,
        ]);

        StockAdditionLine::create([
            'stock_addition_id' => $sa->id,
            'item_code'      => $itemCode,
            'item_name'      => $itemName,
            'fabric_type_id' => $ft->id,
            'color_id'       => Color::where('code', $colorCode)->value('id'),
            'rolls_count'    => $rolls,
            'qty'            => $kg,
            'unit'           => $unit,
        ]);

        $sa->refresh()->recalcTotals();
        DocumentEffects::onApproved($sa->refresh());

        $c = Consignment::where('consignment_no', $no)->first();
        $c?->forceFill(['total_length_m' => $lengthM])->save();

        // أطوال الأتواب
        if ($c && $rolls > 0) {
            $per = round($lengthM / $rolls, 2);
            foreach ($c->rolls()->orderBy('roll_no')->get() as $r) {
                $r->update(['length_m' => $per, 'width_cm' => $ft->spec_width_cm]);
            }
        }

        return $c;
    }

    /** فحص + معمل + استلام سريع لرسالة التل */
    private function quickApprove(
        Consignment $c, FabricType $ft, string $colorCode, ?int $supplierId,
        ?int $store, ?int $qc, ?int $lab, ?int $wh, int $poId,
        string $docFi, string $docLb, string $docGr, string $serialGr,
        int $rolls, float $qty, string $unit, float $width, float $gsm = 60
    ): void {
        $insp = FabricInspection::create([
            'doc_no' => $docFi, 'doc_date' => $c->arrival_date,
            'consignment_id' => $c->id, 'fabric_type_id' => $ft->id,
            'color_id' => Color::where('code', $colorCode)->value('id'),
            'supplier_id' => $supplierId, 'inspector_id' => $qc,
            'declared_rolls' => $rolls, 'counted_rolls' => $rolls, 'counted_kg' => $qty,
            'total_rolls' => $rolls, 'result' => 'accepted', 'status' => 'approved', 'created_by' => $qc,
        ]);

        $sample = min(6, $rolls);
        foreach ($c->rolls()->orderBy('roll_no')->limit($sample)->get() as $r) {
            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'roll_no' => $r->roll_no, 'length_m' => $r->length_m,
                'width_cm' => $width, 'defects_count' => 0,
            ]);
        }
        $insp->refresh()->recalc();
        DocumentEffects::onApproved($insp->refresh());

        $labRep = LabReport::create([
            'doc_no' => $docLb, 'doc_date' => $c->arrival_date,
            'consignment_id' => $c->id, 'supplier_id' => $supplierId,
            'fabric_type_id' => $ft->id, 'color_id' => Color::where('code', $colorCode)->value('id'),
            'technician_id' => $lab, 's1_shrink_len_pct' => 2, 's1_shrink_width_pct' => 1.5,
            's2_shrink_len_pct' => 2.5, 's2_shrink_width_pct' => 1.5,
            'color_match_ok' => true, 'status' => 'approved', 'created_by' => $lab,
        ]);
        LabGsmReading::create(['lab_report_id' => $labRep->id, 'roll_no' => '001', 'gsm' => $gsm]);
        $labRep->refresh()->recalc();
        DocumentEffects::onApproved($labRep->refresh());

        $gr = GoodsReceipt::create([
            'doc_no' => $docGr, 'paper_serial' => $serialGr, 'doc_date' => $c->arrival_date,
            'warehouse_id' => $wh, 'supplier_id' => $supplierId, 'purchase_order_id' => $poId,
            'consignment_id' => $c->id, 'fabric_inspection_id' => $insp->id,
            'status' => 'approved', 'created_by' => $store,
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $gr->id, 'item_code' => $ft->code,
            'fabric_type_id' => $ft->id, 'color_id' => Color::where('code', $colorCode)->value('id'),
            'unit' => $unit, 'width_cm' => $width, 'rolls_count' => $rolls, 'qty' => $qty,
            'consignment_no' => $c->consignment_no,
        ]);
        $gr->refresh()->recalcTotals();
        DocumentEffects::onApproved($gr->refresh());
    }

    // ── أوامر الشغل ─────────────────────────────────────────────

    private function workOrders(Consignment $mami, Consignment $tufr): array
    {
        $planner = User::where('username', 'planner')->value('id');
        $kb      = Factory::where('code', 'KB')->value('id');
        $model   = ProductModel::where('code', 'O1014')->first();

        /* KB106 — الأرقام زي ورقة المصنع بالحرف:
           مياي: فرشة 3.7 (3.75 بالأمان) · عرض 1.56 · بنشر 0.245 · 29 رقة · 16 قطعة/فرشة
                 ⇒ راق 1.433 · استهلاك 0.09 · متوقع 459
           تل:   فرشة 4.05 · عرض 1.5 · 98 رقة · 4 قطع/فرشة ⇒ استهلاك 1.01 · متوقع 392 */
        $kb106 = WorkOrder::create([
            'wo_no'         => 'KB106',
            'wo_date'       => '2026-07-26',
            'factory_id'    => $kb,
            'receive_date'  => '2026-08-01',
            'due_date'      => '2026-08-01',
            'product_title' => 'طرحه تل + بونيه رباط مياي (بيج 241)',
            'product_code'  => '10613077324195',
            'qb_code'       => 'O1014',
            'marker_copies' => 2,
            'planner_id'    => $planner,
            'approved_qty'  => 459,
            'approved_qty_reason' => 'الكمية المعتمدة على الورقة 459 — التل بيدي 392 بس، والفرق هيتغطى بتوريد إضافي.',
            'status'        => 'sent_to_factory',
            'created_by'    => $planner,
        ]);

        $this->fabric($kb106, 1, $mami, 'weight', 'كجم', 43.1, 3.7, 3.75, 1.56, 0.245, 16, 29, 459);
        $this->fabric($kb106, 2, $tufr, 'length', 'متر', 396,  4.05, null, 1.5,  null,  4,  98, 392);

        WorkOrderLine::create([
            'work_order_id' => $kb106->id, 'product_model_id' => $model?->id,
            'size_id' => null, 'qty_per_spread' => 1, 'planned_qty' => 459,
        ]);

        $kb106->refresh()->recalc();
        DocumentEffects::onApproved($kb106->refresh());

        // KB107 — نفس المنتج بألوان تانية (من إذن الصرف)
        $kb107 = WorkOrder::create([
            'wo_no'         => 'KB107',
            'wo_date'       => '2026-07-26',
            'factory_id'    => $kb,
            'receive_date'  => '2026-08-05',
            'due_date'      => '2026-08-05',
            'product_title' => 'طرحه تل + بونيه رباط مياي (كحلي 3794)',
            'qb_code'       => 'O1014',
            'marker_copies' => 2,
            'planner_id'    => $planner,
            'status'        => 'sent_to_factory',
            'created_by'    => $planner,
        ]);

        $this->fabric($kb107, 1, $mami, 'weight', 'كجم', 23.4, 3.7, 3.75, 1.56, 0.245, 16, 16, 256);
        $this->fabric($kb107, 2, $tufr, 'length', 'متر', 730,  4.05, null, 1.5,  null,  4, 180, 720);

        WorkOrderLine::create([
            'work_order_id' => $kb107->id, 'product_model_id' => $model?->id,
            'size_id' => null, 'qty_per_spread' => 1, 'planned_qty' => 256,
        ]);

        $kb107->refresh()->recalc();
        DocumentEffects::onApproved($kb107->refresh());

        return [$kb106->refresh(), $kb107->refresh()];
    }

    private function fabric(
        WorkOrder $wo, int $line, Consignment $c, string $mode, string $unit,
        float $qty, float $spread, ?float $safe, ?float $width, ?float $gsm,
        int $pps, int $plies, int $expected
    ): void {
        $calc = PlanningEngine::computeFabric([
            'calc_mode' => $mode, 'spread_length_m' => $spread, 'spread_length_safe_m' => $safe,
            'fabric_width_m' => $width, 'gsm_kg_m2' => $gsm,
            'pieces_per_spread' => $pps, 'available' => $qty,
        ]);

        WorkOrderFabric::create([
            'work_order_id'        => $wo->id,
            'line_no'              => $line,
            'consignment_id'       => $c->id,
            'fabric_type_id'       => $c->fabric_type_id,
            'color_id'             => $c->color_id,
            'role'                 => $line === 1 ? 'main' : 'secondary',
            'calc_mode'            => $mode,
            'unit'                 => $unit,
            'planned_qty'          => $qty,
            'spread_length_m'      => $spread,
            'spread_length_safe_m' => $safe,
            'fabric_width_m'       => $width,
            'gsm_kg_m2'            => $gsm,
            'pieces_per_spread'    => $pps,
            'ply_weight_kg'        => $calc['ply_weight_kg'] ?? null,
            'consumption_per_piece'=> $calc['consumption_per_piece'] ?? null,
            'calc_plies'           => $calc['plies'] ?? null,
            'calc_pieces'          => $calc['expected_pieces'] ?? null,
            // الرقم اللي على الورقة — بحكم المخطط مش بالمعادلة
            'plies'                => $plies,
            'expected_pieces'      => $expected,
        ]);
    }

    // ── إذن صرف الخام 1303774 ───────────────────────────────────

    private function materialIssue(WorkOrder $kb106, WorkOrder $kb107, Consignment $mami, Consignment $tufr): void
    {
        $store = User::where('username', 'store')->value('id');

        $mi = MaterialIssue::create([
            'doc_no'        => 'MI-2026-1303774',
            'paper_serial'  => '1303774',
            'doc_date'      => '2026-07-27',
            'warehouse_id'  => Warehouse::where('code', 'OBR')->value('id'),
            'factory_id'    => Factory::where('code', 'KB')->value('id'),
            'issued_to'     => 'أ/ الخطيب',
            'receiver_name' => 'مندوب الخطيب',
            'status'        => 'approved',
            'created_by'    => $store,
        ]);

        $f106 = $kb106->fabrics->keyBy('line_no');
        $f107 = $kb107->fabrics->keyBy('line_no');

        // نفس أسطر الورقة بالظبط
        foreach ([
            [$kb106, $f106[1] ?? null, $mami, '14810091', 'كجم', 100, 2,  41.6],
            [$kb106, $f106[2] ?? null, $tufr, '10610034', 'متر', 150, 8,  396],
            [$kb107, $f107[1] ?? null, $mami, '14810091', 'كجم', 160, 6,  23.4],
            [$kb107, $f107[2] ?? null, $tufr, '10610034', 'متر', 160, 12, 730],
        ] as [$wo, $wf, $c, $code, $unit, $width, $rolls, $qty]) {
            MaterialIssueLine::create([
                'material_issue_id'    => $mi->id,
                'work_order_id'        => $wo->id,
                'work_order_fabric_id' => $wf?->id,
                'consignment_id'       => $c->id,
                'fabric_type_id'       => $c->fabric_type_id,
                'color_id'             => $c->color_id,
                'item_code'            => $code,
                'unit'                 => $unit,
                'width_cm'             => $width,
                'rolls_count'          => $rolls,
                'qty'                  => $qty,
                'consignment_no'       => $c->consignment_no,
            ]);
        }

        $mi->refresh()->recalcTotals();
        DocumentEffects::onApproved($mi->refresh());
    }

    /* ── أمر الشغل KY81 — شيت «Automation Job Order» بالحرف ─────────
     |
     | رسالة VSFL_110826_127_04 · فسكوز فل ليكرا · جريش 3_125 · 152.9 كجم
     | فرشة 3.1 م × عرض 1.72 م × مقطع 0.192 ⇒ استهلاك القطعة 0.08531
     | موديلين في نفس الفرشة (12 قطعة): 6 تلبيسه كباسين + 6 كويتى رباط
     | الشيت كان بيعمّم الاستهلاك — هنا بيتوزّع بالمتوسطات (90/80 جم).
    */
    private function ky81(): void
    {
        $planner = User::where('username', 'planner')->value('id');
        $purch   = User::where('username', 'moaz')->value('id');
        $store   = User::where('username', 'store')->value('id');
        $qc      = User::where('username', 'qc')->value('id');
        $lab     = User::where('username', 'lab')->value('id');
        $obr     = Warehouse::where('code', 'OBR')->value('id');
        $sup     = Supplier::where('code', '043')->value('id');
        $vsf     = FabricType::where('code', 'VSF')->first();
        $ky      = Factory::where('code', 'KY')->value('id');

        // طلب الشراء بتاع الخامة
        $po = PurchaseOrder::create([
            'po_no'          => 'PO-2026-00108',
            'po_date'        => '2026-08-05',
            'supplier_id'    => $sup,
            'warehouse_id'   => $obr,
            'employee_id'    => $purch,
            'delivery_date'  => '2026-08-11',
            'payment_method' => 'آجل 30 يوم',
            'stage'          => 'approved',
            'status'         => 'approved',
            'planning_note'  => 'فسكوز فل ليكرا لأمر الشغل KY81',
            'created_by'     => $planner,
            'requested_by'   => $planner, 'requested_at' => '2026-08-05',
            'sourced_by'     => $purch,   'sourced_at'   => '2026-08-06',
        ]);
        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id, 'line_no' => 1,
            'fabric_type_id' => $vsf->id,
            'color_id'       => Color::where('code', '3_125')->value('id'),
            'qty' => 152.9, 'unit' => 'كجم',
            'unit_price' => 120, 'line_total' => 18348, 'tolerance_pct' => 5,
        ]);
        $po->refresh()->recalcTotals();

        // الرسالة + الفحص + الإفراج
        $vsfl = $this->consignment(
            'VSFL_110826_127_04', '2026-08-11', $sup, $vsf, '3_125', $obr, $po->id,
            rolls: 8, kg: 152.9, lengthM: 463, store: $store,
            saDoc: 'SA-2026-00003', saSerial: '41502', itemCode: '118100231701125',
            itemName: 'فسكوز فل ليكرا جريش 3_125'
        );
        $this->quickApprove($vsfl, $vsf, '3_125', $sup, $store, $qc, $lab, $obr, $po->id,
            docFi: 'FI-2026-04381', docLb: 'LB-2026-00003', docGr: 'GR-2026-1000887',
            serialGr: '1000887', rolls: 8, qty: 152.9, unit: 'كجم', width: 172, gsm: 192);
        $vsfl->refresh();

        // أمر الشغل
        $wo = WorkOrder::create([
            'wo_no'         => 'KY81',
            'wo_date'       => '2026-08-15',
            'factory_id'    => $ky,
            'receive_date'  => '2026-08-30',
            'due_date'      => '2026-08-30',
            'product_title' => 'تلبيسه كباسين + كويتى رباط (جريش 3_125)',
            'barcode'       => '10522424012500',
            'marker_copies' => 3,
            'planner_id'    => $planner,
            'cutting_notes' => 'كل قطعة في كيس - كل دستة في كيس · عمل فواصل لوجود اختلاف لون',
            'status'        => 'sent_to_factory',
            'created_by'    => $planner,
        ]);

        // فرشة 3.1 × 1.72 × 0.192 ⇒ استهلاك 0.08531 · 149 رقة · 1788 قطعة
        $this->fabric($wo, 1, $vsfl, 'weight', 'كجم', 152.9, 3.1, null, 1.72, 0.192, 12, 149, 1788);

        // الموديلين — التوزيع بالمتوسطات بدل تعميم الشيت
        $m113 = ProductModel::where('code', '113A')->first();   // تلبيسه كباسين — متوسط 90 جم
        $m121 = ProductModel::where('code', '121A')->first();   // كويتى رباط — متوسط 80 جم
        $fab  = $wo->fabrics()->first();

        $split = PlanningEngine::splitConsumption([
            ['product_model_id' => $m113?->id, 'label' => 'تلبيسه كباسين', 'pieces_in_spread' => 6, 'avg_kg' => 0.090],
            ['product_model_id' => $m121?->id, 'label' => 'كويتى رباط',    'pieces_in_spread' => 6, 'avg_kg' => 0.080],
        ], (float) $fab->consumption_per_piece, (int) $fab->plies);

        foreach ($split['rows'] as $r) {
            WorkOrderLine::create([
                'work_order_id'         => $wo->id,
                'product_model_id'      => $r['product_model_id'],
                'size_id'               => null,
                'qty_per_spread'        => $r['pieces_in_spread'],
                'planned_qty'           => $r['expected_pieces'],
                'avg_consumption_kg'    => $r['avg_kg'],
                'consumption_per_piece' => $r['per_piece'],
                'planned_kg'            => $r['planned_kg'],
            ]);
        }

        $wo->refresh()->recalc();
        DocumentEffects::onApproved($wo->refresh());
    }
}
