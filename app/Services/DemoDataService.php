<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\CutDeclarationLine;
use App\Models\DocumentComment;
use App\Models\FabricInspection;
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
use App\Models\ModelBom;
use App\Models\ProductionReceipt;
use App\Models\ProductionReceiptLine;
use App\Models\ProductModel;
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
use Illuminate\Support\Facades\DB;

/**
 * مولّد الداتا الديمو.
 *
 * الغرض منه إنك تفتح السيستم وتلاقيه «شغّال» — كل شاشة فيها بيانات،
 * وكل دور يلاقي شغل مستني منه، والفلو ماشي من طلب الشراء لحد قفل
 * أمر الشغل. ده للتجربة والفهم — مش للاستخدام الفعلي.
 *
 * reset() بيمسح كل حركة الشغل ويسيب المستخدمين والأدوار ودورات الاعتماد.
 */
class DemoDataService
{
    /** الجداول اللي بتتمسح — المستخدمين والأدوار والصلاحيات ودورات الاعتماد بيفضلوا */
    public const BUSINESS_TABLES = [
        'document_comments', 'approval_steps', 'approvals',
        'production_receipt_lines', 'production_receipts',
        'cut_declaration_lines', 'cut_declarations',
        'material_issue_lines', 'material_issues',
        'goods_receipt_rejections', 'work_order_fabrics',
        'accessory_requirements', 'work_order_lines', 'work_orders',
        'marker_lines', 'markers', 'marker_requests',
        'lab_gsm_readings', 'lab_reports',
        'inspection_rolls', 'fabric_inspections',
        'goods_receipt_lines', 'goods_receipts',
        'stock_addition_lines', 'stock_additions',
        'stock_movements', 'fabric_rolls', 'consignments',
        'purchase_order_lines', 'purchase_orders',
        'forecasts', 'color_ratios', 'safety_stocks',
        'sales_snapshots', 'stock_snapshots', 'factory_loads',
        'model_boms', 'model_sizes', 'product_models', 'accessories',
        'color_merges', 'colors', 'fabric_types', 'sizes',
        'warehouses', 'factories', 'suppliers',
        'activity_logs', 'app_notifications',
    ];

    private $log;

    public function __construct(?callable $log = null)
    {
        $this->log = $log ?? fn (string $m) => null;
    }

    private function say(string $m): void { ($this->log)($m); }

    // ════════════════════════════════════════════════════════════
    //  المسح
    // ════════════════════════════════════════════════════════════

    public function reset(): void
    {
        $this->say('بمسح بيانات الشغل…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::BUSINESS_TABLES as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->say('اتمسح كل شيء ما عدا المستخدمين والأدوار ودورات الاعتماد.');
    }

    // ════════════════════════════════════════════════════════════
    //  التوليد
    // ════════════════════════════════════════════════════════════

    /**
     * ① الديمو الكامل — السيستم كله وهو شغّال.
     *
     * طلبات في كل مرحلة، أحواض في كل حالة، أوامر شغل مقفولة ومفتوحة،
     * ومبيعات 18 شهر. للتجربة والفهم — مش للشغل الحقيقي.
     *
     * بيمسح الأول دايمًا: أرقام المستندات متسلسلة، فالتوليد فوق داتا
     * موجودة بيعمل تضارب في الأرقام الفريدة ومضاعفة في المبيعات.
     */
    public function generate(): array
    {
        $t0 = microtime(true);

        $this->reset();

        $this->masterData();
        $this->purchaseOrders();
        $this->fabricCycle();
        $this->markers();
        $this->production();
        $this->planningData();
        $this->discussions();

        $stats = [
            'موردين'          => Supplier::count(),
            'مصانع'           => Factory::count(),
            'ألوان'           => Color::count(),
            'موديلات'         => ProductModel::count(),
            'إكسسوارات'       => Accessory::count(),
            'طلبات شراء'      => PurchaseOrder::count(),
            'أذون إضافة'      => StockAddition::count(),
            'أحواض'           => Consignment::count(),
            'تقارير فحص'      => FabricInspection::count(),
            'تقارير معمل'     => LabReport::count(),
            'أذون استلام'     => GoodsReceipt::count(),
            'ماركرات'         => Marker::count(),
            'أوامر شغل'       => WorkOrder::count(),
            'بيانات قص'       => CutDeclaration::count(),
            'استلامات إنتاج'  => ProductionReceipt::count(),
            'تعليقات'         => DocumentComment::count(),
            'لقطات مبيعات'    => SalesSnapshot::count(),
        ];

        $stats['الوقت'] = round(microtime(true) - $t0, 1) . ' ث';
        return $stats;
    }

    /**
     * ② البيانات الأساسية بس — نقطة البداية للشغل الحقيقي.
     *
     * موردين ومصانع ومخازن وخامات وألوان وموديلات وإكسسوارات و BOM.
     * **من غير** أي طلب شراء ولا حوض ولا أمر شغل ولا مبيعات — عشان تمشي
     * الدورة بنفسك من الصفر ببيانات حقيقية.
     *
     * بيمسح بيانات الشغل الأول برضه، عشان تبدأ نضيف.
     */
    public function generateMasterOnly(): array
    {
        $t0 = microtime(true);

        $this->reset();
        $this->masterData();

        return [
            'موردين'     => Supplier::count(),
            'مصانع'      => Factory::count(),
            'مخازن'      => Warehouse::count(),
            'خامات'      => FabricType::count(),
            'ألوان'      => Color::count(),
            'موديلات'    => ProductModel::count(),
            'مقاسات'     => Size::count(),
            'إكسسوارات'  => Accessory::count(),
            'سطور BOM'   => ModelBom::count(),
            'الوقت'      => round(microtime(true) - $t0, 1) . ' ث',
        ];
    }

    // ── البيانات الأساسية ───────────────────────────────────────

    private function masterData(): void
    {
        $this->say('البيانات الأساسية…');

        $suppliers = [
            ['196','مالك الدملشي','أ/ مالك','العبور','آجل 60 يوم'],
            ['043','الصنع تكس','أ/ سيد','العاشر من رمضان','آجل 30 يوم'],
            ['MRC','مارشيلو','أ/ مارك','المحلة','نقدي'],
            ['NSG','النصر للغزل','أ/ رأفت','المحلة الكبرى','آجل 45 يوم'],
            ['DLT','دلتا تكس','أ/ سامي','طنطا','آجل 30 يوم'],
            ['ALX','الإسكندرية للنسيج','أ/ هشام','برج العرب','آجل 60 يوم'],
            ['GLD','جولدن تكستايل','أ/ وليد','العبور','نقدي'],
            ['SHR','الشرقية للنسيج','أ/ ماجد','الزقازيق','آجل 30 يوم'],
            ['MSR','مصر للغزل','أ/ طارق','كفر الدوار','آجل 90 يوم'],
            ['UNI','يونيتكس','أ/ كريم','السادس من أكتوبر','آجل 45 يوم'],
            ['ACC','النور للإكسسوارات','أ/ رمضان','شبرا','نقدي'],
            ['PKG','بكرة للتغليف','أ/ عصام','العبور','آجل 30 يوم'],
        ];
        foreach ($suppliers as $i => [$code,$name,$person,$addr,$terms]) {
            Supplier::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person,
                'phone' => '010' . str_pad((string) (10000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'address' => $addr, 'payment_terms' => $terms, 'is_active' => true,
            ]);
        }

        $factories = [
            ['SIN','سيني',4000,7],   ['KHD','خالد',2500,5],   ['NSR','النصر',6000,10],
            ['AMN','الأمين',3200,6], ['HRM','الهرم',5000,9],  ['SFA','الصفا',1800,4],
            ['MDN','المدينة',4500,8],['RWD','الرواد',2200,5],
        ];
        foreach ($factories as $i => [$code,$name,$cap,$cycle]) {
            Factory::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => 'أ/ مدير ' . $name,
                'phone' => '011' . str_pad((string) (20000000 + $i * 211), 8, '0', STR_PAD_LEFT),
                'daily_capacity_pcs' => $cap, 'avg_cycle_days' => $cycle, 'is_active' => true,
            ]);
        }

        foreach ([
            ['OBR','مخزن العبور','fabric'], ['043','مخزن الخامات 043','fabric'],
            ['SLM','مخزن السلام','fabric'], ['ACC','مخزن الإكسسوارات','accessories'],
            ['FIN','مخزن المنتج التام','finished'], ['RTN','مخزن المرتجعات','other'],
        ] as $i => [$code,$name,$type]) {
            Warehouse::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'is_active' => true,
                'last_stock_count_at' => now()->subDays(10 + $i * 6)->toDateString(),
            ]);
        }

        $fabrics = [
            ['VSC-FL','فسكوز فل ليكرا','95% Viscose / 5% Lycra',185,170,200,190,210],
            ['CTN-LY','قطن ليكرا','92% Cotton / 8% Lycra',175,165,180,170,195],
            ['SNG-JR','سنجل جيرسيه','100% Cotton',180,168,190,180,200],
            ['MLT-SP','ملتون سبور','80% Cotton / 20% Poly',170,160,280,265,295],
            ['RIB-22','ريب 2×2','95% Cotton / 5% Lycra',90,84,240,225,255],
            ['LYC-SP','ليكرا سبور','88% Poly / 12% Lycra',150,142,210,200,225],
            ['VIS-JR','فسكوز جيرسيه','100% Viscose',185,172,165,155,178],
            ['INT-LK','إنترلوك','100% Cotton',190,178,220,208,235],
        ];
        foreach ($fabrics as [$c,$n,$comp,$w,$wmin,$g,$gmin,$gmax]) {
            FabricType::updateOrCreate(['code' => $c], [
                'name' => $n, 'composition' => $comp,
                'spec_width_cm' => $w, 'spec_width_min_cm' => $wmin,
                'spec_gsm' => $g, 'spec_gsm_min' => $gmin, 'spec_gsm_max' => $gmax,
                'max_shrink_len_pct' => 5, 'max_shrink_width_pct' => 4, 'max_defect_pct' => 3,
                'is_active' => true,
            ]);
        }

        $this->colors();
        $this->sizesModelsAccessories();
    }

    /** ~260 كود لون — بنفس مشكلة الواقع: أكواد مكررة لنفس اللون */
    private function colors(): void
    {
        foreach ([
            ['BLK-001','أسود','أسود','#111111'],
            ['WHT-001','أبيض','أبيض','#ffffff'],
            ['OFW-001','أوف وايت','أبيض','#f2ece2'],
        ] as [$c,$n,$f,$h]) {
            Color::updateOrCreate(['code' => $c], [
                'name' => $n, 'family' => $f, 'hex' => $h, 'is_basic' => true, 'status' => 'active',
            ]);
        }

        $families = [
            'بني'   => ['BRN', '#6b4f2a', ['بني','بني فاتح','بني غامق','كراميل','موكا','شوكولت','بيج داكن','جملي']],
            'أزرق'  => ['BLU', '#22314f', ['كحلي','لبني','أزرق سماوي','بترولي','تركواز','جينز','نيلي']],
            'رمادي' => ['GRY', '#8d8d8d', ['رمادي','رمادي فاتح','رمادي غامق','فضي','أسمنتي']],
            'أخضر'  => ['GRN', '#3f6b3f', ['زيتي','أخضر غامق','منت','زمردي','بسته']],
            'أحمر'  => ['RED', '#8b2b2b', ['نبيتي','أحمر','خمري','بورجندي','طوبي']],
            'وردي'  => ['PNK', '#c98198', ['وردي','روز','بودرة','فوشيا']],
            'بيج'   => ['BEG', '#d8c7a9', ['بيج','رملي','كريمي','شامبانيا','لاتيه']],
            'بنفسجي'=> ['PRP', '#6d2a5f', ['موف','بنفسجي','لافندر','باذنجاني']],
        ];

        $created = [];
        foreach ($families as $family => [$prefix, $hex, $names]) {
            foreach ($names as $ni => $nm) {
                // كل لون بياخد من 3 لـ 5 أكواد — زي اللي حصل فعلًا مع الصباغة
                $variants = 3 + ($ni % 3);
                $parent = null;
                for ($v = 0; $v < $variants; $v++) {
                    $code = sprintf('%s-%03d', $prefix, $ni * 10 + $v);
                    $c = Color::updateOrCreate(['code' => $code], [
                        'name'        => $nm . ($v ? ' كود ' . ($ni * 10 + $v) : ''),
                        'family'      => $family,
                        'hex'         => $hex,
                        'is_basic'    => false,
                        'status'      => 'active',
                        'legacy_code' => (string) (1000 + $ni * 10 + $v),
                    ]);
                    if ($v === 0) { $parent = $c; continue; }
                    // ندمج جزء من التكرارات — والباقي يفضل نشط عشان يبان حجم المشكلة
                    if ($v % 2 === 1 && $parent && $c->status === 'active') {
                        Color::merge($c, $parent, null, 'نفس اللون — فرق صباغة. دمج ضمن تنظيف الأكواد.');
                    }
                }
                $created[] = $parent;
            }
        }

        // شوية أكواد موقوفة
        Color::where('status', 'active')->where('is_basic', false)
            ->inRandomOrder()->limit(12)->update(['status' => 'retired']);
    }

    private function sizesModelsAccessories(): void
    {
        foreach ([['S','مقاس 1',1],['M','مقاس 2',2],['L','مقاس 3',3],['XL','مقاس 4',4],['XXL','مقاس 5',5]] as [$c,$n,$o]) {
            Size::updateOrCreate(['code' => $c], ['name' => $n, 'sort_order' => $o, 'is_active' => true]);
        }
        $sizeIds = Size::pluck('id');

        $cats = [
            'بادي'    => ['بادي كات','بادي سابرينا','بادي نص كم','بادي بدون أكمام','بادي رقبة عالية','بادي كلوش'],
            'بنطلون'  => ['بنطلون سادة','بنطلون واسع','بنطلون سبور','بنطلون ستريت','ليجن'],
            'تلبيسة'  => ['تلبيسة قطن','تلبيسة فسكوز','تلبيسة سبور','تلبيسة شتوي'],
            'إسدال'   => ['إسدال سادة','إسدال مطرز','إسدال صلاة'],
            'كم'      => ['كم داخلي','كم شيفون','كم قطن'],
            'أندر'    => ['أندر كاب','أندر سكارف','بونيه'],
            'طرح'     => ['طرحة قطن','طرحة فسكوز','طرحة كريب','طرحة سادة','طرحة مشجرة'],
        ];

        $fabricIds = FabricType::pluck('id')->all();
        $i = 0;
        foreach ($cats as $cat => $names) {
            foreach ($names as $nm) {
                $i++;
                $m = ProductModel::updateOrCreate(['code' => sprintf('MDL-%03d', $i)], [
                    'name' => $nm, 'category' => $cat,
                    'fabric_type_id' => $fabricIds[$i % count($fabricIds)],
                    'pcs_per_dozen' => 12, 'is_active' => $i % 17 !== 0,
                ]);
                $m->sizes()->sync($sizeIds->take(3 + ($i % 3)));
            }
        }

        $accs = [
            ['BAG-S1','كيس مقاس 1','bag',60000,5000,true],  ['BAG-S2','كيس مقاس 2','bag',50000,5000,true],
            ['BAG-S3','كيس مقاس 3','bag',40000,5000,true],  ['STK-001','استيكر الموديل','sticker',80000,10000,true],
            ['STK-002','استيكر المقاس','sticker',75000,10000,true], ['DOZ-BAG','كيس دستة','bag',8000,1000,true],
            ['TKT-001','تيكت البراند','label',90000,12000,true], ['TKT-002','تيكت السعر','label',85000,12000,true],
            ['BTN-001','زرار صدف','button',20000,3000,false], ['BTN-002','زرار بلاستيك','button',24000,3000,false],
            ['ZIP-001','سوستة 20 سم','zipper',6000,1000,false], ['ZIP-002','سوستة 40 سم','zipper',4500,1000,false],
            ['THR-001','خيط أبيض','thread',3000,500,true],   ['THR-002','خيط أسود','thread',3200,500,true],
            ['CRT-001','كرتونة تصدير','carton',1500,300,true], ['CRT-002','كرتونة محلي','carton',2200,300,true],
            ['LST-001','أستك 2 سم','other',12000,2000,false], ['LBL-CARE','تيكت العناية','label',70000,9000,true],
        ];
        foreach ($accs as [$c,$n,$t,$q,$r,$sh]) {
            Accessory::updateOrCreate(['code' => $c], [
                'name' => $n, 'type' => $t, 'unit' => 'قطعة',
                'stock_qty' => $q, 'reorder_point' => $r, 'is_shared' => $sh, 'is_active' => true,
            ]);
        }

        // BOM لكل موديل
        $bag  = Accessory::where('code','BAG-S2')->value('id');
        $stk  = Accessory::where('code','STK-001')->value('id');
        $doz  = Accessory::where('code','DOZ-BAG')->value('id');
        $tkt  = Accessory::where('code','TKT-001')->value('id');
        $care = Accessory::where('code','LBL-CARE')->value('id');
        $btn  = Accessory::where('code','BTN-001')->value('id');
        $zip  = Accessory::where('code','ZIP-001')->value('id');

        foreach (ProductModel::all() as $m) {
            foreach ([[$bag,1,'كيس القطعة'], [$stk,1,'استيكر الموديل'],
                      [$tkt,1,'تيكت البراند'], [$care,1,'تيكت العناية'],
                      [$doz, 1/12, 'كيس الدستة']] as [$aid,$q,$note]) {
                if (!$aid) continue;
                ModelBom::updateOrCreate(
                    ['product_model_id' => $m->id, 'accessory_id' => $aid, 'size_id' => null],
                    ['qty_per_piece' => $q, 'notes' => $note]
                );
            }
            if ($m->category === 'بنطلون' && $btn) {
                ModelBom::updateOrCreate(
                    ['product_model_id' => $m->id, 'accessory_id' => $btn, 'size_id' => null],
                    ['qty_per_piece' => 2, 'notes' => 'زرارين']
                );
            }
            if ($m->category === 'بنطلون' && $zip) {
                ModelBom::updateOrCreate(
                    ['product_model_id' => $m->id, 'accessory_id' => $zip, 'size_id' => null],
                    ['qty_per_piece' => 1, 'notes' => 'سوستة']
                );
            }
        }
    }

    // ── دورة الشراء ─────────────────────────────────────────────

    private function purchaseOrders(): void
    {
        $this->say('طلبات الشراء…');

        $planner = User::where('username','planner')->value('id');
        $purch   = User::where('username','moaz')->value('id');
        $fin     = User::where('username','finance')->value('id');

        $suppliers = Supplier::pluck('id')->all();
        $fabrics   = FabricType::pluck('id')->all();
        $colors    = Color::active()->pluck('id')->all();
        $whs       = Warehouse::where('type','fabric')->pluck('id')->all();

        // توزيع المراحل: كل دور يلاقي شغل مستني منه
        $plan = array_merge(
            // الحفظ بينزّل للمشتريات تلقائيًا — planning مرحلة عابرة
            array_fill(0, 8,  'purchasing'),
            array_fill(0, 5,  'finance'),
            array_fill(0, 10, 'approved'),
            array_fill(0, 14, 'receiving'),
            array_fill(0, 7,  'closed'),
        );

        foreach ($plan as $i => $stage) {
            $daysAgo = 120 - $i * 3;
            $po = PurchaseOrder::create([
                'po_no'         => sprintf('PO-%d-%05d', now()->year, 100 + $i),
                'po_date'       => now()->subDays(max(1, $daysAgo))->toDateString(),
                'employee_id'   => $planner,
                'tax_pct'       => 14,
                'discount_pct'  => 0,
                'stage'         => $stage,
                'status'        => in_array($stage, ['approved','receiving','closed'], true) ? 'approved' : 'draft',
                'planning_note' => ['تغطية فوركاست الربع','تعويض سحب عالي','طلب موسمي','تأمين مخزون أمان'][$i % 4],
                'created_by'    => $planner,
                'requested_by'  => $planner,
                'requested_at'  => now()->subDays(max(1, $daysAgo + 2)),
            ]);

            for ($l = 0; $l < 2 + ($i % 4); $l++) {
                $qty = 10 + (($i * 7 + $l * 5) % 40);
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'line_no'        => $l + 1,
                    'color_id'       => $colors[($i * 3 + $l) % count($colors)],
                    'fabric_type_id' => $fabrics[($i + $l) % count($fabrics)],
                    'qty'            => $qty,
                    'unit'           => 'طن',
                    'unit_price'     => 0,
                    'line_total'     => 0,
                    'tolerance_pct'  => 5,
                    'notes'          => $l === 1 ? 'وزن المقطع من 190 جرام الى 210 جرام' : null,
                ]);
            }

            // من مرحلة المشتريات وطالع: المورد والأسعار
            if ($stage !== 'planning') {
                $po->update([
                    'supplier_id'    => $suppliers[$i % count($suppliers)],
                    'warehouse_id'   => $whs[$i % count($whs)],
                    'delivery_date'  => now()->subDays(max(1, $daysAgo - 25))->toDateString(),
                    'delivery_place' => 'العبور',
                    'payment_method' => ['آجل 60 يوم','آجل 30 يوم','نقدي','دفعات'][$i % 4],
                    'sourced_by'     => $purch,
                    'sourced_at'     => now()->subDays(max(1, $daysAgo - 1)),
                ]);
                foreach ($po->lines as $line) {
                    $price = 140000 + (($line->id * 1300) % 22000);
                    $line->update(['unit_price' => $price, 'line_total' => $line->qty * $price]);
                }
            }

            // من مرحلة الحسابات وطالع: العلم
            if (!in_array($stage, ['planning','purchasing'], true)) {
                $po->update([
                    'finance_by'   => $fin,
                    'finance_at'   => now()->subDays(max(1, $daysAgo - 2)),
                    'finance_note' => $i % 3 === 0 ? 'هيتصرف على دفعتين — الأولى مع التوريد.' : null,
                ]);
            }

            $po->refresh()->recalcTotals();
        }
    }

    // ── دورة القماش ─────────────────────────────────────────────

    private function fabricCycle(): void
    {
        $this->say('أذون الإضافة والفحص والمعمل والاستلام…');

        $store = User::where('username','store')->value('id');
        $qc    = User::where('username','qc')->value('id');
        $lab   = User::where('username','lab')->value('id');

        $pos     = PurchaseOrder::whereIn('stage', ['approved','receiving','closed'])->get();
        $fabrics = FabricType::all()->keyBy('id');
        $whs     = Warehouse::where('type','fabric')->pluck('id')->all();

        // الحالة المستهدفة لكل حوض — كل مرحلة فيها شغل مستني
        $targets = array_merge(
            array_fill(0, 7,  'under_inspection'),
            array_fill(0, 5,  'inspected'),
            array_fill(0, 5,  'lab_done'),
            array_fill(0, 21, 'released'),
        );

        foreach ($targets as $i => $target) {
            $po   = $pos[$i % max(1, $pos->count())] ?? null;
            $line = $po?->lines->first();
            if (!$po || !$line) continue;

            $ft     = $fabrics[$line->fabric_type_id] ?? $fabrics->first();
            $rolls  = 5 + ($i % 12);
            $kg     = round($rolls * (140 + ($i % 60)), 2);
            $days   = 95 - $i * 2;
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','', $po->supplier?->code ?? 'CN'), 0, 4)) ?: 'CN';
            $date   = now()->subDays(max(1, $days));

            $sa = StockAddition::create([
                'doc_no'            => sprintf('SA-%d-%05d', now()->year, $i + 1),
                'paper_serial'      => (string) (41400 + $i),
                'doc_date'          => $date->toDateString(),
                'supplier_id'       => $po->supplier_id,
                'warehouse_id'      => $whs[$i % count($whs)],
                'purchase_order_id' => $po->id,
                'consignment_no'    => DocNumber::consignmentNo($prefix, $date, $po->po_no, $i),
                'status'            => 'approved',
                'created_by'        => $store,
            ]);

            StockAdditionLine::create([
                'stock_addition_id' => $sa->id,
                'item_code'      => (string) (17910000 + $i * 13),
                'item_name'      => $ft->name . ' — ' . ($line->color?->name ?? ''),
                'fabric_type_id' => $ft->id,
                'color_id'       => $line->color_id,
                'rolls_count'    => $rolls,
                'qty'            => $kg,
                'unit'           => 'كجم',
            ]);
            $sa->refresh()->recalcTotals();
            DocumentEffects::onApproved($sa->refresh());

            $c = $sa->refresh()->consignment;
            if (!$c) continue;

            // أطوال الأتواب
            $lengths = [];
            foreach ($c->rolls()->orderBy('roll_no')->get() as $ri => $roll) {
                $len = 780 + (($i * 11 + $ri * 7) % 180);
                $lengths[$roll->roll_no] = $len;
                $roll->update(['length_m' => $len, 'width_cm' => $ft->spec_width_cm]);
            }
            $c->forceFill(['total_length_m' => array_sum($lengths)])->save();

            if ($target === 'under_inspection') continue;

            // ── تقرير الفحص ──
            $counted  = $rolls + ($i % 9 === 0 ? -1 : 0);   // فرق جرد أحيانًا
            $sampleN  = max(3, (int) ceil($rolls * 0.3));
            $baseW    = (float) $ft->spec_width_cm;

            $insp = FabricInspection::create([
                'doc_no'         => sprintf('FI-%d-%05d', now()->year, $i + 1),
                'paper_serial'   => (string) (4600 + $i),
                'doc_date'       => $date->copy()->addDays(2)->toDateString(),
                'consignment_id' => $c->id,
                'fabric_type_id' => $ft->id,
                'color_id'       => $c->color_id,
                'supplier_id'    => $c->supplier_id,
                'inspector_id'   => $qc,
                'declared_rolls' => $rolls,
                'counted_rolls'  => $counted,
                'counted_kg'     => $kg,
                'total_rolls'    => $counted,
                'result'         => $i % 23 === 0 ? 'accepted_with_notes' : 'accepted',
                'status'         => 'approved',
                'created_by'     => $qc,
            ]);

            $rollRows = $c->rolls()->orderBy('roll_no')->limit($sampleN)->get();
            foreach ($rollRows as $ri => $roll) {
                $w   = $baseW - (($i + $ri) % 3);
                $def = ($i + $ri) % 4 === 0 ? 1 + (($i + $ri) % 3) : 0;
                InspectionRoll::create([
                    'fabric_inspection_id' => $insp->id,
                    'roll_no'       => $roll->roll_no,
                    'length_m'      => $roll->length_m,
                    'width_cm'      => $w,
                    'gsm'           => $ft->spec_gsm + (($i + $ri) % 11) - 5,
                    'defects_count' => $def,
                    'defect_pct'    => $roll->length_m > 0 ? round($def / $roll->length_m * 100, 3) : 0,
                    'defect_desc'   => $def ? ['بوجرسو','برادة خفيفة','عقدة','فرق صبغة موضعي'][($i+$ri) % 4] : null,
                ]);
                $roll->update(['is_inspected' => true]);
            }
            $insp->refresh()->recalc();
            DocumentEffects::onApproved($insp->refresh());

            if ($target === 'inspected') continue;

            // ── تقرير المعمل ──
            $labRep = LabReport::create([
                'doc_no'              => sprintf('LB-%d-%05d', now()->year, $i + 1),
                'paper_serial'        => (string) (2100 + $i),
                'doc_date'            => $date->copy()->addDays(3)->toDateString(),
                'consignment_id'      => $c->id,
                'supplier_id'         => $c->supplier_id,
                'fabric_type_id'      => $ft->id,
                'color_id'            => $c->color_id,
                'technician_id'       => $lab,
                's1_shrink_len_pct'   => 2.5 + ($i % 4) * 0.5,
                's1_shrink_width_pct' => 1.5 + ($i % 3) * 0.5,
                's2_shrink_len_pct'   => 3.0 + ($i % 3) * 0.5,
                's2_shrink_width_pct' => 2.0 + ($i % 2) * 0.5,
                'color_match_ok'      => $i % 19 !== 0,
                'status'              => 'approved',
                'created_by'          => $lab,
            ]);
            foreach (range(0, min(6, $rolls - 1)) as $ri) {
                LabGsmReading::create([
                    'lab_report_id' => $labRep->id,
                    'roll_no'       => str_pad((string) ($ri + 1), 3, '0', STR_PAD_LEFT),
                    'gsm'           => $ft->spec_gsm + (($i * 3 + $ri * 2) % 13) - 6,
                ]);
            }
            $labRep->refresh()->recalc();
            DocumentEffects::onApproved($labRep->refresh());

            if ($target === 'lab_done') continue;

            // ── إذن الاستلام = الإفراج ──
            $gr = GoodsReceipt::create([
                'doc_no'               => sprintf('GR-%d-%05d', now()->year, $i + 1),
                'paper_serial'         => (string) (1001500 + $i),
                'doc_date'             => $date->copy()->addDays(4)->toDateString(),
                'warehouse_id'         => $sa->warehouse_id,
                'supplier_id'          => $c->supplier_id,
                'purchase_order_id'    => $po->id,
                'consignment_id'       => $c->id,
                'stock_addition_id'    => $sa->id,
                'fabric_inspection_id' => $insp->id,
                'supplier_rep'         => 'مندوب ' . ($po->supplier?->name ?? ''),
                'status'               => 'approved',
                'created_by'           => $store,
            ]);
            GoodsReceiptLine::create([
                'goods_receipt_id' => $gr->id,
                'item_code'        => (string) (17910000 + $i * 13),
                'fabric_type_id'   => $ft->id,
                'color_id'         => $c->color_id,
                'unit'             => 'كجم',
                'width_cm'         => $insp->min_width_cm,
                'rolls_count'      => $counted,
                'qty'              => $kg,
                'consignment_no'   => $c->consignment_no,
            ]);
            $gr->refresh()->recalcTotals();
            DocumentEffects::onApproved($gr->refresh());
        }
    }

    // ── الماركرات ───────────────────────────────────────────────

    private function markers(): void
    {
        $this->say('الماركرات وطلباتها…');

        $planner = User::where('username','planner')->value('id');
        $pattern = User::where('username','pattern')->value('id');

        $factories = Factory::pluck('id')->all();
        $models    = ProductModel::where('is_active', true)->pluck('id')->all();
        $sizes     = Size::ordered()->pluck('id')->all();
        $ready     = Consignment::readyForProduction()->get();

        for ($i = 0; $i < 22; $i++) {
            $c     = $ready[$i % max(1, $ready->count())] ?? null;
            $width = $c?->min_width_cm ?: (165 + ($i % 20));

            $mr = MarkerRequest::create([
                'doc_no'           => sprintf('MR-%d-%05d', now()->year, $i + 1),
                'doc_date'         => now()->subDays(70 - $i * 2)->toDateString(),
                'consignment_id'   => $c?->id,
                'factory_id'       => $factories[$i % count($factories)],
                'fabric_width_cm'  => $width,
                'requested_models' => 'تعشيقة تجمع ' . (2 + $i % 3) . ' موديلات من نفس الخامة',
                'assigned_to'      => $pattern,
                'needed_by'        => now()->subDays(max(1, 66 - $i * 2))->toDateString(),
                'status'           => $i < 18 ? 'delivered' : 'open',
                'created_by'       => $planner,
            ]);

            if ($i >= 18) continue;   // آخر 4 طلبات مفتوحة عند الباترونست

            $spread = round(2.4 + ($i % 9) * 0.12, 3);
            $marker = Marker::create([
                'code'                  => sprintf('MK-%d-%05d', now()->year, $i + 1),
                'name'                  => 'تعشيقة عرض ' . $width,
                'marker_request_id'     => $mr->id,
                'factory_id'            => $mr->factory_id,
                'created_by_patternist' => $pattern,
                'fabric_width_cm'       => $width,
                'marker_width_cm'       => $width - 2,
                'spread_length_m'       => $spread,
                'pieces_per_spread'     => 1,   // recalcPieces() تحت بيحط الإجمالي الحقيقي
                'efficiency_pct'        => 82 + ($i % 12),
                'status'                => $i < 16 ? 'approved' : 'draft',
                'is_active'             => true,
            ]);

            $lines = 2 + ($i % 3);
            for ($l = 0; $l < $lines; $l++) {
                MarkerLine::create([
                    'marker_id'        => $marker->id,
                    'product_model_id' => $models[($i * 3 + $l) % count($models)],
                    'size_id'          => $sizes[($i + $l) % count($sizes)],
                    'qty_per_spread'   => 1 + (($i + $l) % 3),
                ]);
            }
            $marker->refresh()->recalcPieces();
            $mr->forceFill(['marker_id' => $marker->id])->save();
        }
    }

    // ── التشغيل ─────────────────────────────────────────────────

    private function production(): void
    {
        $this->say('أوامر الشغل والقص والاستلامات…');

        $planner = User::where('username','planner')->value('id');
        $follow  = User::where('username','follow')->value('id');
        $store   = User::where('username','store')->value('id');
        $whFin   = Warehouse::where('code','FIN')->value('id');

        $markers = Marker::where('status','approved')->with('lines')->get();
        if ($markers->isEmpty()) return;

        $statuses = array_merge(
            array_fill(0, 3, 'draft'),
            array_fill(0, 3, 'pending'),
            array_fill(0, 4, 'approved'),
            array_fill(0, 6, 'sent_to_factory'),
            array_fill(0, 8, 'in_production'),
            array_fill(0, 9, 'partially_received'),
            array_fill(0, 12,'closed'),
        );

        $n = 0;
        foreach ($statuses as $i => $status) {
            $c = Consignment::readyForProduction()->orderBy('id')->skip($i % 20)->first()
                 ?? Consignment::whereIn('status',['released','in_production'])->inRandomOrder()->first();
            if (!$c) break;

            $marker = $markers[$i % $markers->count()];
            if (!$marker->fitsWidth((float) $c->min_width_cm)) {
                $marker = $markers->first(fn ($m) => $m->fitsWidth((float) $c->min_width_cm)) ?? $marker;
            }

            $alloc = min((float) $c->remaining_kg ?: 300, 250 + ($i % 8) * 70);
            if ($alloc <= 0) continue;

            $calc = PlanningEngine::forWorkOrder($c, $marker, $alloc);
            if (!($calc['ok'] ?? false)) continue;

            $n++;
            $due = now()->subDays(55 - $i * 2);

            $wo = WorkOrder::create([
                'wo_no'         => sprintf('WO-%d-%05d', now()->year, $n),
                'wo_date'       => now()->subDays(60 - $i * 2)->toDateString(),
                'factory_id'    => $marker->factory_id,
                'due_date'      => $due->toDateString(),
                'receive_date'  => $due->copy()->addDays(3)->toDateString(),
                'product_title' => $marker->lines->first()?->productModel?->name ?? 'منتج',
                'qb_code'       => $marker->code,
                'marker_copies' => 2,
                'planner_id'    => $planner,
                'status'        => $status,
                'created_by'    => $planner,
            ]);

            // خامة واحدة — الديمو المولّد مش بيحاكي المنتجات متعددة الخامات
            \App\Models\WorkOrderFabric::create([
                'work_order_id'         => $wo->id,
                'line_no'               => 1,
                'consignment_id'        => $c->id,
                'fabric_type_id'        => $c->fabric_type_id,
                'color_id'              => $c->color_id,
                'marker_id'             => $marker->id,
                'role'                  => 'main',
                'calc_mode'             => 'weight',
                'unit'                  => 'كجم',
                'planned_qty'           => $alloc,
                'spread_length_m'       => $marker->spread_length_m,
                'fabric_width_m'        => round((float) $c->min_width_cm / 100, 3),
                'gsm_kg_m2'             => round((float) $c->avg_gsm / 1000, 4),
                'pieces_per_spread'     => $marker->pieces_per_spread,
                'ply_weight_kg'         => $calc['ply_weight_kg'],
                'consumption_per_piece' => $calc['kg_per_piece'],
                'calc_plies'            => $calc['expected_plies'],
                'calc_pieces'           => $calc['expected_pieces'],
                'plies'                 => $calc['expected_plies'],
                'expected_pieces'       => $calc['expected_pieces'],
                'is_governing'          => true,
            ]);

            $spreads = (int) floor($calc['expected_pieces'] / max(1, $marker->pieces_per_spread));
            foreach ($marker->lines as $ml) {
                WorkOrderLine::create([
                    'work_order_id'    => $wo->id,
                    'product_model_id' => $ml->product_model_id,
                    'size_id'          => $ml->size_id,
                    'qty_per_spread'   => $ml->qty_per_spread,
                    'planned_qty'      => $spreads * (int) $ml->qty_per_spread,
                ]);
            }
            $wo->refresh()->recalc();

            foreach (PlanningEngine::explodeAccessories($wo->refresh()) as $accId => $r) {
                \App\Models\AccessoryRequirement::updateOrCreate(
                    ['work_order_id' => $wo->id, 'accessory_id' => $accId],
                    ['required_qty' => $r['required'], 'shortage_qty' => $r['shortage']]
                );
            }
            $c->refresh()->recalcRemaining();

            if (!in_array($status, ['in_production','partially_received','closed'], true)) continue;

            // ── بيان القص ──
            $factor  = 0.94 + ($i % 7) * 0.012;
            $actual  = round((float) $marker->spread_length_m + (($i % 5) - 1) * 0.01, 3);

            $cd = CutDeclaration::create([
                'doc_no'                 => sprintf('CD-%d-%05d', now()->year, $n),
                'doc_date'               => $due->copy()->subDays(8)->toDateString(),
                'work_order_id'          => $wo->id,
                'factory_id'             => $wo->factory_id,
                'actual_spread_length_m' => $actual,
                'actual_plies'           => max(1, (int) round($spreads * 0.98)),
                'used_kg'                => round($alloc * 0.99, 3),
                'status'                 => 'approved',
                'created_by'             => $follow,
                'variance_reason'        => $factor < 0.95 ? 'جزء من القماش طلع أعرض واتعمل له تعشيقة تانية.' : null,
            ]);

            $total = 0;
            foreach ($wo->lines as $wl) {
                $q = (int) floor($wl->planned_qty * $factor);
                $total += $q;
                CutDeclarationLine::create([
                    'cut_declaration_id' => $cd->id,
                    'product_model_id'   => $wl->product_model_id,
                    'size_id'            => $wl->size_id,
                    'qty'                => $q,
                ]);
            }
            $v = PlanningEngine::variance((float) $wo->expected_pieces, (float) $total);
            $cd->forceFill([
                'total_pieces'        => $total,
                'actual_kg_per_piece' => $total > 0 ? round($alloc * 0.99 / $total, 5) : null,
                'variance_pct'        => $v['pct'],
                'variance_flag'       => $v['flag'],
            ])->saveQuietly();
            DocumentEffects::onApproved($cd->refresh());

            if ($status === 'in_production') { $wo->forceFill(['status' => 'in_production'])->save(); continue; }

            // ── استلامات الإنتاج ──
            $wo->refresh()->load('lines');
            $portions = $status === 'closed' ? [0.55, 1.0] : [0.45];

            foreach ($portions as $pi => $portion) {
                $pr = ProductionReceipt::create([
                    'doc_no'        => sprintf('PR-%d-%05d', now()->year, $n * 10 + $pi),
                    'doc_date'      => $due->copy()->subDays(4 - $pi * 2)->toDateString(),
                    'work_order_id' => $wo->id,
                    'factory_id'    => $wo->factory_id,
                    'warehouse_id'  => $whFin,
                    'status'        => 'approved',
                    'created_by'    => $store,
                ]);

                foreach ($wo->lines as $wl) {
                    $q = $portion >= 1.0
                        ? (int) $wl->remaining_qty
                        : (int) floor($wl->cut_qty * $portion);
                    if ($q <= 0) continue;
                    ProductionReceiptLine::create([
                        'production_receipt_id' => $pr->id,
                        'product_model_id'      => $wl->product_model_id,
                        'size_id'               => $wl->size_id,
                        'color_id'              => $c->color_id,
                        'qty'                   => $q,
                    ]);
                }
                $pr->refresh()->recalcTotals();
                if ($pr->total_pieces > 0) DocumentEffects::onApproved($pr->refresh());
                $wo->refresh()->load('lines');
            }
        }
    }

    // ── داتا التخطيط ────────────────────────────────────────────

    private function planningData(): void
    {
        $this->say('المبيعات والأرصدة والفوركاست…');

        $planner = User::where('username','planner')->value('id');
        $models  = ProductModel::where('is_active', true)->get();
        $whFin   = Warehouse::where('code','FIN')->value('id');

        // 18 شهر مبيعات
        $rows = [];
        foreach ($models as $mi => $m) {
            $base = 1200 + (($mi * 431) % 9000);
            for ($k = 17; $k >= 0; $k--) {
                $from = now()->subMonths($k)->startOfMonth();
                $to   = now()->subMonths($k)->endOfMonth();
                $season = 1 + sin(($from->month / 12) * 2 * M_PI) * 0.22;
                $qty = (int) round($base * $season * (0.9 + (($mi + $k) % 7) / 30));
                $rows[] = [
                    'pulled_at'   => $to->copy()->addDays(5)->toDateString(),
                    'period_from' => $from->toDateString(),
                    'period_to'   => $to->toDateString(),
                    'product_model_id' => $m->id,
                    'qty_pcs'     => $qty,
                    'raw_qty'     => $qty,
                    'raw_unit'    => 'قطعة',
                    'source'      => 'quickbooks_excel',
                    'revision'    => 1,
                    'is_locked'   => $k > 0,
                    'unit_warning'=> false,
                    'created_at'  => now(), 'updated_at' => now(),
                ];
            }
            // آخر 30 يوم — أساس متوسط البيع اليومي
            $rows[] = [
                'pulled_at'   => now()->toDateString(),
                'period_from' => now()->subDays(30)->toDateString(),
                'period_to'   => now()->toDateString(),
                'product_model_id' => $m->id,
                'qty_pcs'     => $base,
                'raw_qty'     => $base,
                'raw_unit'    => 'قطعة',
                'source'      => 'quickbooks_excel',
                'revision'    => 1,
                'is_locked'   => false,
                'unit_warning'=> false,
                'created_at'  => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) SalesSnapshot::insert($chunk);

        // الأرصدة — متعمّد يبقى فيه موديلات في الخطر
        $stock = [];
        foreach ($models as $mi => $m) {
            $base = 1200 + (($mi * 431) % 9000);
            $mult = [0.15, 0.4, 1.2, 2.6, 5.0][$mi % 5];
            $stock[] = [
                'pulled_at'        => now()->toDateString(),
                'warehouse_id'     => $whFin,
                'product_model_id' => $m->id,
                'qty_pcs'          => (int) round($base * $mult),
                'reliability'      => $mi % 4 === 0 ? 'counted' : 'book',
                'source'           => 'excel',
                'created_at'       => now(), 'updated_at' => now(),
            ];
            SafetyStock::updateOrCreate(
                ['product_model_id' => $m->id, 'color_id' => null],
                ['qty_pcs' => 400 + ($mi % 5) * 150, 'cover_days' => 15,
                 'notes' => 'مبدئي — يتراجع كل ربع سنة', 'updated_by' => $planner]
            );
        }
        StockSnapshot::insert($stock);

        // نسب الألوان + فوركاست لأكبر 8 موديلات
        $basics = Color::basic()->pluck('id')->all();
        $ratios = [];
        foreach ($basics as $bi => $cid) $ratios[$cid] = [45, 30, 25][$bi] ?? 0;

        foreach ($models->take(8) as $m) {
            foreach ($ratios as $cid => $pct) {
                \App\Models\ColorRatio::updateOrCreate(
                    ['product_model_id' => $m->id, 'color_id' => $cid, 'year' => now()->year, 'month' => null],
                    ['ratio_pct' => $pct, 'source' => 'issues', 'updated_by' => $planner,
                     'notes' => 'مستنتجة من صرف المخزن — محتاجة مراجعة']
                );
            }
            ForecastService::generate($m->id, now()->year + 1, 10, $ratios, $planner);
        }
    }

    // ── النقاشات على المستندات ──────────────────────────────────

    private function discussions(): void
    {
        $this->say('نقاشات المستندات…');

        $users = User::whereIn('username', ['planner','moaz','store','qc','lab','follow','finance','gm'])
                     ->pluck('id','username');

        $threads = [
            [PurchaseOrder::class, [
                ['planner','question','الطلب ده محتاج يوصل قبل نهاية الشهر — ينفع نضغط على المورد؟'],
                ['moaz','answer','كلمت المورد، قال أقصى حاجة 10 أيام. لو عايزين أسرع هنحتاج نقسّمه على موردين.'],
                ['finance','note','المبلغ ده كبير على دفعة واحدة — هنقسّمه على دفعتين مع التوريد.'],
            ]],
            [FabricInspection::class, [
                ['qc','note','فيه توبين فيهم برادة خفيفة على الجنب. مش مؤثرة على التعشيقة بس حبيت أسجّلها.'],
                ['planner','question','البرادة دي هتاثر على العرض المستخدم؟'],
                ['qc','answer','لا، هي على 1 سم من الجنب والماركر بتاعنا أضيق من كده بـ 2 سم.'],
            ]],
            [WorkOrder::class, [
                ['follow','question','المصنع بيقول القماش فيه جزء أعرض شوية — ينفع يعمل له تعشيقة تانية؟'],
                ['planner','decision','موافق، بس بشرط يبعتلنا بيان قص منفصل للجزء ده عشان الحسبة تفضل مظبوطة.'],
                ['follow','answer','تمام، بلغته وهيبعت البيانين.'],
            ]],
            [GoodsReceipt::class, [
                ['store','note','استلمنا الرسالة كاملة، الأتواب مطابقة للجرد.'],
                ['gm','decision','تمام — اعتمد وافرج عنها.'],
            ]],
            [LabReport::class, [
                ['lab','note','الانكماش في الحدود، بس اللون فيه فرق بسيط عن العينة المعتمدة.'],
                ['planner','question','الفرق ده هيبان في المنتج النهائي؟'],
                ['lab','answer','لو الحوض هيتشغّل لوحده مش هيبان. الممنوع إننا نخلطه مع حوض تاني.'],
            ]],
        ];

        foreach ($threads as [$class, $msgs]) {
            $docs = $class::latest('id')->limit(6)->get();
            foreach ($docs as $di => $doc) {
                if ($di % 2) continue;   // مش كل المستندات فيها نقاش
                foreach ($msgs as $mi => [$who, $kind, $body]) {
                    DocumentComment::create([
                        'commentable_type' => $class,
                        'commentable_id'   => $doc->id,
                        'user_id'          => $users[$who] ?? null,
                        'body'             => $body,
                        'kind'             => $kind,
                        'created_at'       => now()->subDays(6 - $mi)->subHours($di),
                        'updated_at'       => now()->subDays(6 - $mi)->subHours($di),
                    ]);
                }
            }
        }
    }
}
