<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\ActivityLog;
use App\Models\AppNotification;
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
use App\Models\GoodsReceiptRejection;
use App\Models\InspectionRoll;
use App\Models\LabGsmReading;
use App\Models\LabReport;
use App\Models\Marker;
use App\Models\MarkerLine;
use App\Models\MarkerRequest;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\ModelBom;
use App\Models\PreparationRequest;
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
use App\Models\WorkOrderFabric;
use App\Models\WorkOrderLine;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مولّد الداتا الديمو.
 *
 * الغرض منه إنك تفتح السيستم وتلاقيه «شغّال» — كل شاشة فيها بيانات،
 * وكل دور يلاقي شغل مستني منه، والفلو ماشي من طلب الشراء لحد قفل
 * أمر الشغل. ده للتجربة والفهم — مش للاستخدام الفعلي.
 *
 * ★ كل مستند بيتقفل من نفس المسار اللي الشاشات بتستخدمه (FlowEngine::complete
 *   ⇒ DocumentEffects)، فالأرصدة والمستلم على الطلبات وحركات المخزون بتطلع
 *   بنفس أرقام الشغل الحقيقي: مجموع الرسايل = المستلم على الطلب،
 *   والمتاح = المفرج عنه − المخصص − المحجوز.
 *
 * generateFull() = resetTransactions() + البيانات الأساسية + 90 يوم ترانزاكشنات.
 * reset() بيمسح كل حركة الشغل ويسيب المستخدمين والأدوار.
 */
class DemoDataService
{
    /** الترانزاكشنات بس — البيانات الأساسية (موردين/خامات/ألوان/موديلات/مصانع) بتفضل */
    public const TRANSACTION_TABLES = [
        'production_receipt_lines', 'production_receipts',
        'cut_declaration_lines', 'cut_declarations',
        'material_issue_lines', 'material_issues',
        'goods_receipt_rejections', 'work_order_fabrics',
        'accessory_requirements', 'work_order_lines', 'work_orders',
        'preparation_request_models', 'preparation_requests',
        'marker_lines', 'markers', 'marker_request_models', 'marker_requests',
        'lab_gsm_readings', 'lab_reports',
        'inspection_rolls', 'fabric_inspections',
        'goods_receipt_lines', 'goods_receipts',
        'stock_addition_lines', 'stock_additions',
        'stock_movements', 'fabric_rolls', 'consignments',
        'purchase_order_models', 'purchase_order_lines', 'purchase_orders',
        'forecasts', 'sales_snapshots', 'stock_snapshots', 'factory_loads',
        'document_comments', 'activity_logs', 'app_notifications',
    ];

    /** كل بيانات الشغل — المستخدمين والأدوار والصلاحيات بيفضلوا */
    public const BUSINESS_TABLES = [
        'document_comments',
        'production_receipt_lines', 'production_receipts',
        'cut_declaration_lines', 'cut_declarations',
        'material_issue_lines', 'material_issues',
        'goods_receipt_rejections', 'work_order_fabrics',
        'accessory_requirements', 'work_order_lines', 'work_orders',
        'preparation_request_models', 'preparation_requests',
        'marker_lines', 'markers', 'marker_request_models', 'marker_requests',
        'lab_gsm_readings', 'lab_reports',
        'inspection_rolls', 'fabric_inspections',
        'goods_receipt_lines', 'goods_receipts',
        'stock_addition_lines', 'stock_additions',
        'stock_movements', 'fabric_rolls', 'consignments',
        'purchase_order_models', 'purchase_order_lines', 'purchase_orders',
        'forecasts', 'color_ratios', 'safety_stocks',
        'sales_snapshots', 'stock_snapshots', 'factory_loads',
        'model_boms', 'model_sizes', 'product_models', 'accessories',
        'color_merges', 'colors', 'fabric_types', 'sizes',
        'warehouses', 'factories', 'suppliers',
        'activity_logs', 'app_notifications',
    ];

    private $log;

    /** المستخدمين بالدور: planner / purchasing / storekeeper / inspector / lab_tech / patternist / factory_follow / finance / gm */
    private array $u = [];
    /** الخامات بالكود */
    private $ft;
    /** الرسايل المولّدة بمفتاح السيناريو (A1, B1 …) */
    private array $cons = [];
    /** الماركرات المعتمدة بمفتاح */
    private array $mk = [];
    /** طلبات التحضير بمفتاح */
    private array $pq = [];
    /** عدّاد الرسايل في اليوم الواحد — لرقم الرسالة */
    private array $dayCounter = [];
    private int $lastNotif = 0;
    private int $lastLog = 0;

    public function __construct(?callable $log = null)
    {
        $this->log = $log ?? fn (string $m) => null;
    }

    private function say(string $m): void { ($this->log)($m); }

    // ════════════════════════════════════════════════════════════
    //  المسح
    // ════════════════════════════════════════════════════════════

    /** مسح الترانزاكشنات بس — عشان تمشي على السيستم من الأول والبيانات الأساسية زي ما هي */
    public function resetTransactions(): void
    {
        $this->say('بمسح الترانزاكشنات…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TRANSACTION_TABLES as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                DB::table($t)->truncate();
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // أرصدة الإكسسوارات بتيجي من الأذون — ترجع صفر
        if (DB::getSchemaBuilder()->hasTable('accessories')) {
            DB::table('accessories')->update(['stock_qty' => 0]);
        }

        $this->say('اتمسحت الترانزاكشنات. البيانات الأساسية والمستخدمين زي ما هم.');
    }

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

        $this->say('اتمسح كل شيء ما عدا المستخدمين والأدوار.');
    }

    // ════════════════════════════════════════════════════════════
    //  التوليد
    // ════════════════════════════════════════════════════════════

    /** ① الديمو الكامل — نفس generateFull() (الزرار والأمر lv:demo بيناديا) */
    public function generate(): array
    {
        return $this->generateFull();
    }

    /**
     * الديمو الكامل — 90 يوم شغل متسق يغطي الفلو كله:
     * طلبات شراء في كل مرحلة (أكتر من موديل، استلام مباشر، دفعات جزئية)،
     * رسايل في كل حالة بفحص ومعمل مدمج ومرفوضات بتصنيفاتها، طلبات تحضير،
     * ماركرات على عروض مختلفة، أوامر شغل في كل الحالات بصرف وقص واستلام،
     * ومبيعات وأرصدة وفوركاست.
     *
     * بيمسح الترانزاكشنات الأول (البيانات الأساسية بتتجدد بـ updateOrCreate).
     */
    public function generateFull(): array
    {
        $t0 = microtime(true);
        mt_srand(36);   // نفس الداتا كل مرة — الأرقام ثابتة عشان الشرح يتكرر

        $this->resetTransactions();

        $this->masterData();
        UserSeeder::linkFactories();
        $this->users();

        $this->purchaseScenario();
        $this->markersAndRequests();
        $this->preparationRequests();
        $this->workOrders();
        $this->closeConsumedConsignments();
        $this->planningData();
        $this->discussions();
        $this->notificationsTouch();

        $stats = [
            'موردين'          => Supplier::count(),
            'مصانع'           => Factory::count(),
            'ألوان'           => Color::count(),
            'موديلات'         => ProductModel::count(),
            'طلبات شراء'      => PurchaseOrder::count(),
            'أذون إضافة'      => StockAddition::count(),
            'رسايل'           => Consignment::count(),
            'تقارير فحص'      => FabricInspection::count(),
            'تقارير معمل'     => LabReport::count(),
            'أذون استلام'     => GoodsReceipt::count(),
            'مرفوضات'         => GoodsReceiptRejection::count(),
            'طلبات تحضير'     => PreparationRequest::count(),
            'طلبات ماركر'     => MarkerRequest::count(),
            'ماركرات'         => Marker::count(),
            'أوامر شغل'       => WorkOrder::count(),
            'أذون صرف'        => MaterialIssue::count(),
            'بيانات قص'       => CutDeclaration::count(),
            'استلامات إنتاج'  => ProductionReceipt::count(),
            'تعليقات'         => DocumentComment::count(),
            'إشعارات'         => AppNotification::count(),
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
        UserSeeder::linkFactories();

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

    // ── أدوات صغيرة ─────────────────────────────────────────────

    private function users(): void
    {
        foreach (['planner', 'purchasing', 'purchasing_mgr', 'storekeeper', 'stock_controller', 'inspector',
                  'lab_tech', 'patternist', 'factory_follow', 'finance', 'gm', 'admin'] as $r) {
            $this->u[$r] = UserSeeder::idByRole($r) ?? User::orderBy('id')->value('id');
        }
        $this->ft = FabricType::all()->keyBy('code');
    }

    /** يوم من الماضي (أو المستقبل لو سالب) — كل التواريخ بتتحسب من النهارده */
    private function day(int $daysAgo): Carbon
    {
        return now()->startOfDay()->subDays($daysAgo);
    }

    private function r(float $min, float $max, int $dec = 0): float|int
    {
        $v = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
        return $dec === 0 ? (int) round($v) : round($v, $dec);
    }

    private function pick(array $arr): mixed
    {
        return $arr[mt_rand(0, count($arr) - 1)];
    }

    private function colorId(string $needle): int
    {
        return (int) (Color::where('status', 'active')->where('name', 'like', $needle . '%')->orderBy('id')->value('id')
            ?? Color::where('status', 'active')->orderBy('id')->value('id'));
    }

    private function supplierId(string $code): ?int { return Supplier::where('code', $code)->value('id'); }
    private function factoryId(string $code): ?int  { return Factory::where('code', $code)->value('id'); }
    private function warehouseId(string $code): ?int { return Warehouse::where('code', $code)->value('id'); }

    private function modelIds(string $category, int $n, int $offset = 0): array
    {
        return ProductModel::where('is_active', true)->where('category', $category)
            ->orderBy('id')->skip($offset)->limit($n)->pluck('id')->all();
    }

    /** يقفل المستند من نفس مسار الشاشات، وبيرجّع تاريخ الإشعارات والسجل لتاريخ المستند */
    private function complete($doc, string $msg, Carbon $date): void
    {
        FlowEngine::complete($doc, $msg);
        $this->stamp($date);
    }

    /** الإشعارات وسجل الحركة اللي اتولّدوا دلوقتي بياخدوا تاريخ المستند مش تاريخ التوليد */
    private function stamp(Carbon $date): void
    {
        $at = $date->copy()->setTime(mt_rand(8, 16), mt_rand(0, 59));
        AppNotification::where('id', '>', $this->lastNotif)->update(['created_at' => $at, 'updated_at' => $at]);
        ActivityLog::where('id', '>', $this->lastLog)->update(['created_at' => $at, 'updated_at' => $at]);
        $this->lastNotif = (int) AppNotification::max('id');
        $this->lastLog   = (int) ActivityLog::max('id');
    }

    // ── البيانات الأساسية ───────────────────────────────────────

    private function masterData(): void
    {
        $this->say('البيانات الأساسية…');

        $suppliers = [
            ['196','مالك الدملشي','أ/ مالك','العبور','آجل 60 يوم','local'],
            ['043','الصنع تكس','أ/ سيد','العاشر من رمضان','آجل 30 يوم','local'],
            ['MRC','مارشيلو','أ/ مارك','المحلة','نقدي','local'],
            ['NSG','النصر للغزل','أ/ رأفت','المحلة الكبرى','آجل 45 يوم','local'],
            ['DLT','دلتا تكس','أ/ سامي','طنطا','آجل 30 يوم','local'],
            ['ALX','الإسكندرية للنسيج','أ/ هشام','برج العرب','آجل 60 يوم','imported'],
            ['GLD','جولدن تكستايل','أ/ وليد','العبور','نقدي','local'],
            ['SHR','الشرقية للنسيج','أ/ ماجد','الزقازيق','آجل 30 يوم','local'],
            ['MSR','مصر للغزل','أ/ طارق','كفر الدوار','آجل 90 يوم','local'],
            ['UNI','يونيتكس','أ/ كريم','السادس من أكتوبر','آجل 45 يوم','both'],
            ['ACC','النور للإكسسوارات','أ/ رمضان','شبرا','نقدي','local'],
            ['PKG','بكرة للتغليف','أ/ عصام','العبور','آجل 30 يوم','local'],
        ];
        foreach ($suppliers as $i => [$code,$name,$person,$addr,$terms,$type]) {
            Supplier::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person,
                'phone' => '010' . str_pad((string) (10000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'address' => $addr, 'payment_terms' => $terms, 'supplier_type' => $type, 'is_active' => true,
            ]);
        }

        $factories = [
            ['SIN','سيني','أ/ محمود رجب',4000,7],   ['KHD','خالد','أ/ خالد فوزي',2500,5],   ['NSR','النصر','أ/ سمير عبد الله',6000,10],
            ['AMN','الأمين','أ/ أمين حسن',3200,6], ['HRM','الهرم','أ/ رضا الهرم',5000,9],  ['SFA','الصفا','أ/ صفاء محمد',1800,4],
            ['MDN','المدينة','أ/ عماد',4500,8],    ['RWD','الرواد','أ/ وائل',2200,5],
        ];
        foreach ($factories as $i => [$code,$name,$person,$cap,$cycle]) {
            Factory::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person,
                'phone' => '011' . str_pad((string) (20000000 + $i * 211), 8, '0', STR_PAD_LEFT),
                'address' => ['العاشر من رمضان', 'العبور', 'المحلة', 'شبرا الخيمة', 'بدر', 'السادات', 'العبور', 'قليوب'][$i],
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

        // الخامة: كود، اسم، تركيب، عرض معياري، أقل عرض، بنشر، حد أدنى، حد أعلى
        $fabrics = [
            ['VSC-FL','فسكوز فل ليكرا','95% Viscose / 5% Lycra',185,178,200,190,210],
            ['CTN-LY','قطن ليكرا','92% Cotton / 8% Lycra',175,168,180,170,195],
            ['SNG-JR','سنجل جيرسيه','100% Cotton',180,172,190,180,200],
            ['MLT-SP','ملتون سبور','80% Cotton / 20% Poly',170,163,230,220,240],
            ['RIB-22','ريب 2×2','95% Cotton / 5% Lycra',90,84,240,225,255],
            ['LYC-SP','ليكرا سبور','88% Poly / 12% Lycra',150,144,210,200,225],
            ['VIS-JR','فسكوز جيرسيه','100% Viscose',165,158,185,178,195],
            ['INT-LK','إنترلوك','100% Cotton',180,172,220,208,235],
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
        $this->modelExtras();
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

        foreach ($families as $family => [$prefix, $hex, $names]) {
            foreach ($names as $ni => $nm) {
                // كل لون بياخد من 3 لـ 5 أكواد — زي اللي حصل فعلًا مع الصباغة
                $variants = 3 + ($ni % 3);
                $parent = null;
                for ($v = 0; $v < $variants; $v++) {
                    $code = sprintf('%s-%03d', $prefix, $ni * 10 + $v);
                    $c = Color::firstOrCreate(['code' => $code], [
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
            }
        }

        // شوية أكواد موقوفة — مرة واحدة بس (التوليد المتكرر ما يوقفش أكتر)
        if (!Color::where('status', 'retired')->exists()) {
            Color::where('status', 'active')->where('is_basic', false)->where('code', 'like', '%-__3')
                ->limit(12)->update(['status' => 'retired']);
        }
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

        // الخامة الافتراضية لكل فئة — زي الواقع: البادي فسكوز/قطن ليكرا، الطرح فسكوز جيرسيه…
        $catFabric = [
            'بادي' => ['VSC-FL', 'CTN-LY'], 'بنطلون' => ['CTN-LY', 'MLT-SP'], 'تلبيسة' => ['SNG-JR', 'CTN-LY'],
            'إسدال' => ['VSC-FL', 'VIS-JR'], 'كم' => ['LYC-SP', 'SNG-JR'], 'أندر' => ['LYC-SP', 'INT-LK'], 'طرح' => ['VIS-JR', 'VSC-FL'],
        ];
        $fabricIds = FabricType::pluck('id', 'code');
        $i = 0;
        foreach ($cats as $cat => $names) {
            foreach ($names as $ni => $nm) {
                $i++;
                $m = ProductModel::updateOrCreate(['code' => sprintf('MDL-%03d', $i)], [
                    'name' => $nm, 'category' => $cat,
                    'fabric_type_id' => $fabricIds[$catFabric[$cat][$ni % 2]] ?? null,
                    'pcs_per_dozen' => 12, 'is_active' => $i !== 11,   // «ليجن» موقوف — موديل واحد موقوف عشان الفلتر يبان
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

        foreach (ProductModel::where('code', 'like', 'MDL-%')->get() as $m) {
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

        // الكتالوج الحقيقي من شيت الجوب أوردر: 49 موديل مكملات + BOM + إكسسوارات بأكوادها
        JobOrderMaster::seed(fn ($m) => $this->say('  ' . $m));
    }

    /**
     * متوسط استهلاك وصورة لكل موديل.
     * المتوسط هو اللي بيوزّع الاستهلاك الفعلي على الموديلات المشتركة في نفس الفرشة،
     * والصورة بتطلع في ورقة أمر الشغل. الموديلات اللي ملهاش صورة حقيقية بتاخد
     * صورة توضيحية بسيطة (SVG) في storage/app/public/models.
     */
    private function modelExtras(): void
    {
        // كجم/قطعة تقريبي بالفئة — البادي أتقل من الكم والأندر
        $avg = ['بادي' => [0.17, 0.24], 'بنطلون' => [0.26, 0.34], 'تلبيسة' => [0.19, 0.27], 'إسدال' => [0.42, 0.55],
                'كم' => [0.05, 0.08], 'أندر' => [0.04, 0.07], 'طرح' => [0.11, 0.16], 'مكملات' => [0.04, 0.09]];
        $hue = ['بادي' => '#9D197E', 'بنطلون' => '#3B4C7A', 'تلبيسة' => '#1F5B35', 'إسدال' => '#560D45',
                'كم' => '#8B5E34', 'أندر' => '#4A3F48', 'طرح' => '#B5342B', 'مكملات' => '#6B606A'];

        $dir = storage_path('app/public/models');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        foreach (ProductModel::all() as $m) {
            $cat = $m->category ?: 'مكملات';
            $upd = [];
            if (!$m->std_consumption_kg) {
                [$lo, $hi] = $avg[$cat] ?? $avg['مكملات'];
                $upd['std_consumption_kg'] = $this->r($lo, $hi, 3);
            }
            if (!$m->photo_url && is_dir($dir)) {
                $file = 'demo-' . preg_replace('/[^A-Za-z0-9_-]/', '', $m->code) . '.svg';
                if (!is_file($dir . '/' . $file)) {
                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="360" viewBox="0 0 300 360">'
                        . '<rect width="300" height="360" rx="18" fill="#F8F4F1"/>'
                        . '<rect x="20" y="20" width="260" height="250" rx="14" fill="' . ($hue[$cat] ?? '#6B606A') . '" opacity=".12"/>'
                        . '<path d="M95 70 l40 -22 q15 10 30 0 l40 22 l-18 40 l-12 -6 v120 h-50 h-50 v-120 l-12 6 z" fill="' . ($hue[$cat] ?? '#6B606A') . '" opacity=".55"/>'
                        . '<text x="150" y="305" text-anchor="middle" font-family="Tahoma, Arial" font-size="20" font-weight="700" fill="#2B2028">' . htmlspecialchars($m->name) . '</text>'
                        . '<text x="150" y="335" text-anchor="middle" font-family="Tahoma, Arial" font-size="14" fill="#6B606A">' . htmlspecialchars($m->code . ' · ' . $cat) . '</text>'
                        . '</svg>';
                    @file_put_contents($dir . '/' . $file, $svg);
                }
                if (is_file($dir . '/' . $file)) $upd['photo_url'] = asset('storage/models/' . $file);
            }
            if ($upd) $m->forceFill($upd)->saveQuietly();
        }
    }

    // ════════════════════════════════════════════════════════════
    //  دورة الشراء والقماش — 90 يوم
    // ════════════════════════════════════════════════════════════

    /**
     * السيناريو كله في جدول واحد: كل طلب شراء بسطوره ودفعات وصوله، وكل دفعة
     * بتقول كل سطر فيها بيوصل كام (نسبة من المطلوب أو 'rest' = الباقي) وإيه
     * الحالة اللي الرسالة الناتجة تقف عندها.
     */
    private function purchaseScenario(): void
    {
        $this->say('طلبات الشراء وأذون الإضافة والفحص والمعمل والإفراج…');

        $black = $this->colorId('أسود'); $offw = $this->colorId('أوف وايت'); $white = $this->colorId('أبيض');
        $navy  = $this->colorId('كحلي'); $grey = $this->colorId('رمادي'); $beige = $this->colorId('بيج');
        $olive = $this->colorId('زيتي'); $burg = $this->colorId('بورجندي'); $rose = $this->colorId('روز');
        $lav   = $this->colorId('لافندر'); $mocha = $this->colorId('موكا'); $petrol = $this->colorId('بترولي');
        $mint  = $this->colorId('منت'); $camel = $this->colorId('جملي'); $powder = $this->colorId('بودرة');

        $badi = $this->modelIds('بادي', 6); $talb = $this->modelIds('تلبيسة', 4); $tarh = $this->modelIds('طرح', 5);
        $kom  = $this->modelIds('كم', 3);   $andr = $this->modelIds('أندر', 3);   $bant = $this->modelIds('بنطلون', 4);
        $isd  = $this->modelIds('إسدال', 3);

        $pos = [
            // ── مقفولة (قديمة) ──
            ['key' => 'A', 'stage' => 'closed', 'days' => 85, 'supplier' => 'NSG', 'wh' => 'OBR', 'pay' => 'آجل 45 يوم',
             'note' => 'تغطية فوركاست الربع — البادي الأساسي أسود وأوف وايت', 'models' => [$badi[0], $badi[1], $badi[2]],
             'lines' => [['VSC-FL', $black, 3.0, 'طن'], ['VSC-FL', $offw, 2.0, 'طن']],
             'batches' => [
                 ['days' => 70, 'sdn' => 'ن.غ 7718', 'lines' => [0 => 1.0, 1 => 1.0],
                  'targets' => [0 => ['key' => 'A1', 'state' => 'released'], 1 => ['key' => 'A2', 'state' => 'released']]],
             ]],
            ['key' => 'B', 'stage' => 'closed', 'days' => 80, 'supplier' => 'DLT', 'wh' => 'OBR', 'pay' => 'آجل 30 يوم',
             'note' => 'بنطلون الكحلي — سحب عالي في الشتوي', 'models' => [$bant[0], $bant[1]],
             'lines' => [['CTN-LY', $navy, 2.5, 'طن']],
             // وصل زيادة 4% — جوه نسبة الزيادة (5%) فالسطر اتقفل عادي
             'batches' => [
                 ['days' => 62, 'sdn' => 'د.ت 2210', 'lines' => [0 => 1.04],
                  'targets' => [0 => ['key' => 'B1', 'state' => 'released']]],
             ]],
            ['key' => 'C', 'stage' => 'closed', 'days' => 75, 'supplier' => 'ALX', 'wh' => 'SLM', 'pay' => 'تحويل بنكي', 'direct' => true,
             'note' => 'حاويات مستوردة — استلام مباشر من غير دورة فحص', 'models' => [$talb[0], $talb[1], $talb[2]],
             'lines' => [['SNG-JR', $white, 4.0, 'طن'], ['SNG-JR', $beige, 2.0, 'طن']],
             'batches' => [
                 ['days' => 55, 'sdn' => 'CNT-88213', 'lines' => [0 => 1.0, 1 => 1.0],
                  'targets' => [0 => ['key' => 'C1', 'state' => 'released', 'indicative' => true], 1 => ['key' => 'C2', 'state' => 'released', 'indicative' => true]]],
             ]],
            ['key' => 'E', 'stage' => 'closed', 'days' => 55, 'supplier' => 'SHR', 'wh' => '043', 'pay' => 'آجل 30 يوم',
             'note' => 'ملتون الشتوي — زيتي وبورجندي', 'models' => [$bant[2], $isd[0]],
             'lines' => [['MLT-SP', $olive, 1.5, 'طن'], ['MLT-SP', $burg, 1.5, 'طن']],
             'batches' => [
                 ['days' => 40, 'sdn' => 'ش.ن 1190', 'lines' => [0 => 1.0, 1 => 1.0],
                  'targets' => [
                      0 => ['key' => 'E1', 'state' => 'released',
                            'hold' => ['rolls' => 2, 'category' => 'gsm', 'resolution' => 'accepted_with_discount', 'discount' => 7,
                                       'reason' => 'توبين بنشر 246–248 فوق المواصفة (240) — معلّق لحين رد التخطيط والمشتريات',
                                       'note' => 'اتقبل بخصم 7% من المورد على التوبين — البنشر الزيادة مش مؤثر على الموديل']],
                      1 => ['key' => 'E2', 'state' => 'rejected', 'category' => 'color',
                            'reason' => 'اللون مختلف تمامًا عن العينة المعتمدة — البورجندي طالع طوبي، وفيه فرق لون بين الأتواب'],
                  ]],
             ]],
            ['key' => 'H', 'stage' => 'closed', 'days' => 38, 'supplier' => '043', 'wh' => '043', 'pay' => 'نقدي',
             'note' => 'قطن ليكرا أسود بالكيلو — طلب عاجل للتلبيسة', 'models' => [$talb[3], $kom[0]],
             'lines' => [['CTN-LY', $black, 1000, 'كجم']],
             'batches' => [
                 ['days' => 25, 'sdn' => 'ص.ت 5102', 'lines' => [0 => 1.0],
                  'targets' => [0 => ['key' => 'H1', 'state' => 'released',
                      'reject' => ['rolls' => 3, 'category' => 'shrinkage', 'resolution' => 'returned',
                                   'note' => 'انكماش الطول 7.5% على التلات أتواب — رجعوا للمورد بمحضر']]]],
             ]],

            // ── جاري الاستلام (دفعات) ──
            ['key' => 'D', 'stage' => 'receiving', 'days' => 60, 'supplier' => 'MRC', 'wh' => 'OBR', 'pay' => 'آجل 60 يوم',
             'note' => 'ليكرا سبور للكم والأندر — على 3 دفعات حسب التوريد', 'models' => [$kom[0], $kom[1], $andr[0], $andr[1]],
             'lines' => [['LYC-SP', $black, 3.0, 'طن'], ['LYC-SP', $grey, 2.0, 'طن']],
             'batches' => [
                 ['days' => 45, 'sdn' => 'م.ش 3311', 'lines' => [0 => 0.5, 1 => 0.6], 'eta' => 30,
                  'targets' => [
                      0 => ['key' => 'D1', 'state' => 'released', 'variance' => -1,
                            'reject' => ['rolls' => 2, 'category' => 'width', 'resolution' => 'open',
                                         'detail' => 'عرض 141–142 سم أقل من أقل عرض مسموح (144)']],
                      1 => ['key' => 'D2', 'state' => 'released'],
                  ]],
                 ['days' => 30, 'sdn' => 'م.ش 3367', 'lines' => [0 => 0.3], 'eta' => 12,
                  'targets' => [0 => ['key' => 'D3', 'state' => 'released', 'color_break' => '014، 019']]],
                 ['days' => 12, 'sdn' => 'م.ش 3402', 'lines' => [1 => 'rest'], 'eta' => -10,
                  'targets' => [1 => ['key' => 'D4', 'state' => 'inspected']]],
             ]],
            ['key' => 'F', 'stage' => 'receiving', 'days' => 48, 'supplier' => 'GLD', 'wh' => 'OBR', 'pay' => 'نقدي',
             'note' => 'إنترلوك أسود للأندر — المورد بيورّد على دفعات', 'models' => [$andr[0], $andr[2]],
             'lines' => [['INT-LK', $black, 2.0, 'طن']],
             'batches' => [
                 ['days' => 35, 'sdn' => 'ج.ت 910', 'lines' => [0 => 0.55], 'eta' => 20,
                  'targets' => [0 => ['key' => 'F1', 'state' => 'released', 'gsm_high' => true,
                      'hold' => ['rolls' => 3, 'category' => 'gsm', 'resolution' => 'accepted_with_discount', 'discount' => 8,
                                 'reason' => 'متوسط البنشر 241 فوق الحد الأعلى (235) — معلّق لحين الرد',
                                 'note' => 'المشتريات اتفقت على خصم 8% — البنشر الزيادة بيقلل عدد القطع']]]],
                 ['days' => 20, 'sdn' => 'ج.ت 957', 'lines' => [0 => 0.25], 'eta' => -7,
                  'targets' => [0 => ['key' => 'F2', 'state' => 'lab_done']]],
             ]],
            ['key' => 'G', 'stage' => 'receiving', 'days' => 42, 'supplier' => 'UNI', 'wh' => 'SLM', 'pay' => 'آجل 45 يوم',
             'note' => 'فسكوز جيرسيه للطرح — روز ولافندر', 'models' => [$tarh[0], $tarh[1], $tarh[2]],
             'lines' => [['VIS-JR', $rose, 2.0, 'طن'], ['VIS-JR', $lav, 1.0, 'طن']],
             'batches' => [
                 ['days' => 28, 'sdn' => 'ي.ت 4480', 'lines' => [0 => 0.5, 1 => 1.0], 'eta' => 14,
                  'targets' => [0 => ['key' => 'G1', 'state' => 'released'], 1 => ['key' => 'G2', 'state' => 'released', 'color_break' => '007']]],
                 ['days' => 9, 'sdn' => 'ي.ت 4533', 'lines' => [0 => 0.3], 'eta' => -5,
                  'targets' => [0 => ['key' => 'G3', 'state' => 'under_inspection']]],
             ]],
            ['key' => 'I', 'stage' => 'receiving', 'days' => 30, 'supplier' => 'ALX', 'wh' => 'SLM', 'pay' => 'تحويل بنكي', 'direct' => true,
             'note' => 'حاوية سنجل أسود — استلام مباشر، النص التاني في الطريق', 'models' => [$talb[0], $talb[2]],
             'lines' => [['SNG-JR', $black, 3.0, 'طن']],
             'batches' => [
                 ['days' => 14, 'sdn' => 'CNT-88402', 'lines' => [0 => 0.5], 'eta' => -12,
                  'targets' => [0 => ['key' => 'I1', 'state' => 'released', 'indicative' => true]]],
             ]],
            ['key' => 'J', 'stage' => 'receiving', 'days' => 26, 'supplier' => 'NSG', 'wh' => 'OBR', 'pay' => 'آجل 45 يوم',
             'note' => 'فسكوز فل ليكرا كحلي وأسود — البادي الشتوي', 'models' => [$badi[3], $badi[4], $isd[1]],
             'lines' => [['VSC-FL', $navy, 2.0, 'طن'], ['VSC-FL', $black, 1.5, 'طن']],
             'batches' => [
                 ['days' => 16, 'sdn' => 'ن.غ 7790', 'lines' => [1 => 1.0], 'eta' => 10,
                  'targets' => [1 => ['key' => 'J1', 'state' => 'lab_done']]],
                 ['days' => 2, 'sdn' => 'ن.غ 7811', 'lines' => [0 => 0.5], 'eta' => -14,
                  'targets' => [0 => ['key' => 'J2', 'state' => 'under_inspection']]],
             ]],

            // ── اتسعّرت ومستنية الوصول ──
            ['key' => 'K', 'stage' => 'approved', 'days' => 25, 'supplier' => 'DLT', 'wh' => 'OBR', 'pay' => 'آجل 30 يوم', 'delivery' => 4,
             'note' => 'قطن ليكرا موكا وبترولي — المورد اتأخر عن موعده', 'models' => [$bant[1], $bant[3]],
             'lines' => [['CTN-LY', $mocha, 1.5, 'طن'], ['CTN-LY', $petrol, 1.0, 'طن']]],
            ['key' => 'L', 'stage' => 'approved', 'days' => 12, 'supplier' => 'MSR', 'wh' => '043', 'pay' => 'آجل 90 يوم', 'delivery' => -10,
             'note' => 'سنجل جيرسيه منت للتلبيسة الصيفي', 'models' => [$talb[1], $talb[2], $kom[2]],
             'lines' => [['SNG-JR', $mint, 2.0, 'طن']]],
            ['key' => 'M', 'stage' => 'approved', 'days' => 8, 'supplier' => 'SHR', 'wh' => '043', 'pay' => 'دفعات', 'delivery' => -15, 'finance' => false,
             'note' => 'ملتون جملي للإسدال الشتوي', 'models' => [$isd[0], $isd[2]],
             'lines' => [['MLT-SP', $camel, 1.2, 'طن'], ['MLT-SP', $black, 0.8, 'طن']]],

            // ── عند المشتريات (لسه ما اتسعّرتش) ──
            ['key' => 'N', 'stage' => 'purchasing', 'days' => 6,
             'note' => 'تأمين مخزون أمان — الطرح الأساسية أوف وايت وبودرة', 'models' => [$tarh[2], $tarh[3], $tarh[4]],
             'lines' => [['VIS-JR', $offw, 1.5, 'طن'], ['VIS-JR', $powder, 1.0, 'طن']]],
            ['key' => 'O', 'stage' => 'purchasing', 'days' => 2,
             'note' => 'ريب للأندر كاب — طلب موسمي', 'models' => [$andr[0], $andr[1]],
             'lines' => [['RIB-22', $black, 600, 'كجم']]],
        ];

        // الطلبات بترتيب تاريخها — عشان أرقامها تطلع متسلسلة مع الزمن
        usort($pos, fn ($a, $b) => $b['days'] <=> $a['days']);
        $created = [];
        foreach ($pos as $s) $created[$s['key']] = ['po' => $this->createPo($s), 'spec' => $s];

        // الدفعات بترتيب وصولها (كل الطلبات مع بعض)
        $arrivals = [];
        foreach ($created as $key => ['po' => $po, 'spec' => $s]) {
            foreach ($s['batches'] ?? [] as $b) $arrivals[] = ['po' => $po, 'spec' => $s, 'batch' => $b];
        }
        usort($arrivals, fn ($a, $b) => $b['batch']['days'] <=> $a['batch']['days']);

        $received = [];   // po key => line idx => kg
        foreach ($arrivals as $a) {
            $this->arrive($a['po'], $a['spec'], $a['batch'], $received);
        }

        // الفحص والمعمل والإفراج — بترتيب وصول الرسايل
        $queue = collect($this->cons)->sortBy(fn ($row) => $row['c']->arrival_date->format('Y-m-d') . $row['c']->id);
        foreach ($queue as $key => $row) {
            $this->qualityCycle($row['c'], $row['target'], $row['direct']);
        }
    }

    private function createPo(array $s): PurchaseOrder
    {
        $date = $this->day($s['days']);
        $pl   = $this->u['planner'];
        $priced = $s['stage'] !== 'purchasing';

        $po = PurchaseOrder::create([
            'po_no'             => DocNumber::next('purchase_order', 'purchase_orders', 'po_no'),
            'po_date'           => $date->toDateString(),
            'employee_id'       => $pl,
            'tax_pct'           => 14,
            'discount_pct'      => 0,
            'stage'             => $priced ? 'approved' : 'purchasing',
            'status'            => $priced ? 'approved' : 'draft',
            'planning_note'     => $s['note'],
            'inspection_exempt' => (bool) ($s['direct'] ?? false),
            'created_by'        => $pl,
            'requested_by'      => $pl,
            'requested_at'      => $date->copy()->setTime(10, 15),
        ]);
        $po->models()->sync(array_values(array_filter($s['models'])));

        foreach ($s['lines'] as $i => [$fcode, $colorId, $qty, $unit]) {
            $ft = $this->ft[$fcode];
            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_no'        => $i + 1,
                'color_id'       => $colorId,
                'fabric_type_id' => $ft->id,
                'qty'            => $qty,
                'unit'           => $unit,
                'unit_price'     => 0,
                'line_total'     => 0,
                'tolerance_pct'  => 5,
                'notes'          => $i === 0 ? 'وزن المقطع من ' . $ft->spec_gsm_min . ' إلى ' . $ft->spec_gsm_max . ' جرام · عرض ' . $ft->spec_width_cm . ' سم' : null,
            ]);
        }

        if ($priced) {
            // موعد التوريد: المحدد، وإلا آخر دفعة/موعد الباقي في السيناريو، وإلا 10 أيام بعد الطلب
            $lead = $s['delivery'] ?? (isset($s['batches'])
                ? min(array_map(fn ($b) => $b['eta'] ?? $b['days'], $s['batches']))
                : $s['days'] - 10);
            $po->update([
                'supplier_id'    => $this->supplierId($s['supplier']),
                'warehouse_id'   => $this->warehouseId($s['wh']),
                'delivery_date'  => $this->day($lead)->toDateString(),
                'delivery_place' => Warehouse::find($this->warehouseId($s['wh']))?->name ?? 'العبور',
                'payment_method' => $s['pay'],
                'sourced_by'     => $this->u['purchasing'],
                'sourced_at'     => $date->copy()->addDay()->setTime(12, 30),
            ]);
            // سعر الطن حسب الخامة — الفسكوز أغلى من القطن
            $perTon = ['VSC-FL' => 186000, 'CTN-LY' => 158000, 'SNG-JR' => 149000, 'MLT-SP' => 172000,
                       'RIB-22' => 161000, 'LYC-SP' => 178000, 'VIS-JR' => 181000, 'INT-LK' => 166000];
            foreach ($po->lines as $line) {
                $base  = $perTon[$this->ft->firstWhere('id', $line->fabric_type_id)?->code] ?? 160000;
                $base += $this->r(-4000, 4000);
                $price = $line->unit === 'طن' ? $base : round($base / 1000, 2);
                $line->update(['unit_price' => $price, 'line_total' => round((float) $line->qty * $price, 2)]);
            }

            if (($s['finance'] ?? true) !== false) {
                $po->update([
                    'finance_by'   => $this->u['finance'],
                    'finance_at'   => $date->copy()->addDays(2)->setTime(11, 0),
                    'finance_note' => $this->pick(['هيتصرف على دفعتين — الأولى مع التوريد.', 'آجل حسب الاتفاق — متابعة الاستحقاق مع الاستلام.', null, 'مطلوب فاتورة ضريبية قبل الصرف.']),
                ]);
            }
            $this->stamp($date->copy()->addDay());
        }

        $po->refresh()->recalcTotals();
        return $po;
    }

    /**
     * دفعة وصول = إذن إضافة واحد بسطر لكل لون — بيتقفل من FlowEngine فيولّد الرسايل
     * ويحدّث المستلم على الطلب ويقفل البند اللي اكتمل ويبعت إشعار الفحص.
     */
    private function arrive(PurchaseOrder $po, array $s, array $b, array &$received): void
    {
        $date  = $this->day($b['days']);
        $store = $this->u['storekeeper'];
        $po->load('lines');
        $direct = (bool) ($s['direct'] ?? false);

        $sa = StockAddition::create([
            'doc_no'            => DocNumber::next('stock_addition', 'stock_additions'),
            'paper_serial'      => (string) (41450 + StockAddition::count()),
            'supplier_doc_no'   => $b['sdn'],
            'doc_date'          => $date->toDateString(),
            'supplier_id'       => $po->supplier_id,
            'warehouse_id'      => $po->warehouse_id,
            'purchase_order_id' => $po->id,
            'receipt_type'      => $direct ? 'container' : 'normal',
            'remainder_eta'     => isset($b['eta']) ? $this->day($b['eta'])->toDateString() : null,
            'status'            => 'draft',
            'notes'             => $direct ? 'حاويات — استلام مباشر، الإضافة دي هي النهائية' : null,
            'created_by'        => $store,
        ]);

        $rollsByLine = [];
        foreach ($b['lines'] as $li => $fraction) {
            $poLine = $po->lines[$li];
            $ft     = $this->ft->firstWhere('id', $poLine->fabric_type_id);
            $orderedKg = DocumentEffects::toUnit($poLine->qty, $poLine->unit, 'كجم');
            $soFar     = $received[$s['key']][$li] ?? 0.0;

            $targetKg = $fraction === 'rest'
                ? max(0, $orderedKg - $soFar)
                : $orderedKg * (float) $fraction;

            $rolls = $this->rollsFor($ft, $targetKg);
            $kg    = round(array_sum(array_column($rolls, 'kg')), 2);
            $received[$s['key']][$li] = $soFar + $kg;
            $rollsByLine[$li] = $rolls;

            StockAdditionLine::create([
                'stock_addition_id' => $sa->id,
                'item_code'      => (string) (17910000 + $ft->id * 100 + ($poLine->color_id % 100)),
                'item_name'      => $ft->name . ' — ' . ($poLine->color?->name ?? ''),
                'fabric_type_id' => $ft->id,
                'color_id'       => $poLine->color_id,
                'po_line_id'     => $poLine->id,
                'rolls_count'    => count($rolls),
                'qty'            => $kg,
                'unit'           => 'كجم',
                'remainder_eta'  => isset($b['eta']) && $fraction !== 'rest' && $fraction < 1 ? $this->day($b['eta'])->toDateString() : null,
            ]);
        }
        $sa->refresh()->recalcTotals();

        $this->complete($sa, 'إذن الإضافة ' . $sa->doc_no . ' اتسجّل وخلص', $date);
        $sa->refresh()->load('lines');

        // الرسايل اللي اتولّدت: رقم بنمط الشركة + بيانات الأتواب الحقيقية
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $po->supplier?->code ?? 'CN'), 0, 4)) ?: 'CN';
        foreach ($sa->lines as $li => $line) {
            $c = $line->consignment;
            if (!$c) continue;

            $dk = $date->format('dmy');
            $seq = $this->dayCounter[$dk] = ($this->dayCounter[$dk] ?? -1) + 1;
            $no  = DocNumber::consignmentNo($prefix, $date, (string) ((int) substr($po->po_no, -5)), $seq);
            $c->forceFill(['consignment_no' => $no])->saveQuietly();
            if ($sa->consignment_id === $c->id) $sa->forceFill(['consignment_no' => $no])->saveQuietly();

            $rolls = $rollsByLine[array_keys($b['lines'])[$li]] ?? [];
            $totalLen = 0;
            foreach ($c->rolls()->orderBy('roll_no')->get() as $ri => $roll) {
                $r = $rolls[$ri] ?? null;
                if (!$r) continue;
                $roll->update(['length_m' => $r['len'], 'width_cm' => $r['width'], 'gsm' => $r['gsm'], 'net_kg' => $r['kg']]);
                $totalLen += $r['len'];
            }
            $c->forceFill(['total_length_m' => round($totalLen, 2)])->saveQuietly();
            if ($direct) {
                $c->forceFill(['released_at' => $date->toDateString(), 'notes' => 'استلام مباشر (حاويات) — دخلت مُفرَج عنها'])->saveQuietly();
            }

            $target = $b['targets'][array_keys($b['lines'])[$li]] ?? ['key' => 'X' . $c->id, 'state' => 'released'];
            $this->cons[$target['key']] = ['c' => $c->refresh(), 'target' => $target, 'direct' => $direct, 'rolls' => $rolls];
        }
    }

    /**
     * أتواب دفعة: طول 48–60 م، عرض = المعياري − (0..4) سم، بنشر حوالين المواصفة،
     * والوزن منهم (طول × عرض × بنشر). الأطوال بتتظبط عشان المجموع يطلع الوزن المطلوب بالظبط.
     */
    private function rollsFor(FabricType $ft, float $targetKg): array
    {
        $w0  = (float) $ft->spec_width_cm;
        $g0  = (float) $ft->spec_gsm;
        $avg = 54 * ($w0 / 100) * ($g0 / 1000);
        $n   = max(3, (int) round($targetKg / $avg));

        $rolls = [];
        for ($i = 0; $i < $n; $i++) {
            $rolls[] = ['len' => $this->r(48, 60, 1), 'width' => $w0 - $this->r(0, 4), 'gsm' => $g0 + $this->r(-6, 6)];
        }
        $sum = 0;
        foreach ($rolls as $r) $sum += $r['len'] * ($r['width'] / 100) * ($r['gsm'] / 1000);
        $scale = $sum > 0 ? $targetKg / $sum : 1;

        $acc = 0;
        foreach ($rolls as $i => &$r) {
            $r['len'] = round($r['len'] * $scale, 1);
            $r['kg']  = $i === $n - 1
                ? round($targetKg - $acc, 2)
                : round($r['len'] * ($r['width'] / 100) * ($r['gsm'] / 1000), 2);
            $acc += $r['kg'];
        }
        unset($r);
        return $rolls;
    }

    /** فحص ⇒ معمل مدمج ⇒ إذن استلام (إفراج) — لحد الحالة المستهدفة */
    private function qualityCycle(Consignment $c, array $t, bool $direct): void
    {
        $state = $t['state'];
        $arrival = $c->arrival_date->copy();

        // الاستلام المباشر: مُفرَج عنه من الأول — الفحص لو حصل استدلالي
        if ($direct) {
            if (!empty($t['indicative'])) {
                $insp = $this->inspect($c, $t, $arrival->copy()->addDays(3), true);
                $this->lab($c, $t, $arrival->copy()->addDays(3));
            }
            $c->refresh()->recalcRemaining();
            return;
        }

        if ($state === 'under_inspection') return;

        $inspDate = $arrival->copy()->addDays($this->r(1, 3));
        $insp = $this->inspect($c, $t, $inspDate, false);

        if ($state === 'rejected' || $state === 'inspected') return;

        $this->lab($c, $t, $inspDate);                       // المعمل مدمج مع نفس ورقة الفحص
        if ($state === 'lab_done') return;

        $this->release($c, $insp, $t, $inspDate->copy()->addDays($this->r(1, 3)));
    }

    private function inspect(Consignment $c, array $t, Carbon $date, bool $indicative): FabricInspection
    {
        $qc = $this->u['inspector'];
        $c->refresh();
        $ft = $this->ft->firstWhere('id', $c->fabric_type_id);
        $rolls = $c->rolls()->orderBy('roll_no')->get();
        $n = $rolls->count();

        $sampleN = $indicative ? min($n, 5) : min($n, max(3, (int) ceil($n * $this->r(0.15, 0.25, 2))));
        $sample  = $rolls->sortBy(fn () => mt_rand())->take($sampleN)->sortBy('roll_no')->values();

        $variance = (int) ($t['variance'] ?? 0);
        $counted  = $n + $variance;
        $rejectN  = (int) ($t['reject']['rolls'] ?? 0);
        $rejected = $t['state'] === 'rejected';

        $insp = FabricInspection::create([
            'doc_no'         => DocNumber::next('fabric_inspection', 'fabric_inspections'),
            'paper_serial'   => (string) (4620 + FabricInspection::count()),
            'doc_date'       => $date->toDateString(),
            'consignment_id' => $c->id,
            'fabric_type_id' => $c->fabric_type_id,
            'color_id'       => $c->color_id,
            'supplier_id'    => $c->supplier_id,
            'inspector_id'   => $qc,
            'declared_rolls' => $n,
            'counted_rolls'  => $counted,
            'counted_kg'     => $c->total_kg,
            'result'         => $rejected ? 'rejected' : ($rejectN > 0 || !empty($t['color_break']) ? 'accepted_with_notes' : 'accepted'),
            'notes'          => $rejected
                ? ($t['reason'] ?? 'اترفضت في الفحص')
                : ($indicative ? 'فحص استدلالي على رسالة مستلمة مباشرة — للعرض والبنشر بس' : ($t['reject']['detail'] ?? null)),
            'status'         => 'draft',
            'created_by'     => $qc,
        ]);

        $defectTypes = InspectionRoll::DEFECT_TYPES;
        $details = ['بوجرسو' => 'خطين طوليين على بعد 3 سم من الجنب', 'برادة' => 'برادة خفيفة على الحرف',
                    'تناسخ' => 'تناسخ لون على 40 سم', 'ثقوب' => 'ثقب 2 مم في منتصف التوب', 'بقع' => 'بقعة زيت صغيرة',
                    'شد / تجعيد' => 'شد في النسيج عند البداية', 'اختلاف لون' => 'فرق درجة بين أول وآخر التوب', 'أخرى' => 'علامة قلم على الجنب'];

        foreach ($sample as $i => $roll) {
            $isRej = !$rejected && $i < $rejectN;
            $w = (float) $roll->width_cm;
            if ($isRej && ($t['reject']['category'] ?? '') === 'width') $w = (float) $ft->spec_width_min_cm - $this->r(2, 3);

            $def = $isRej ? $this->r(2, 4) : ($this->r(0, 100) < 30 ? $this->r(1, 2) : 0);
            $type = $isRej
                ? match ($t['reject']['category'] ?? 'other') { 'width' => 'أخرى', 'shrinkage' => 'شد / تجعيد', 'color' => 'اختلاف لون', default => $this->pick($defectTypes) }
                : $this->pick(array_slice($defectTypes, 0, 6));

            InspectionRoll::create([
                'fabric_inspection_id' => $insp->id,
                'fabric_roll_id' => $roll->id,
                'roll_no'        => $roll->roll_no,
                'length_m'       => $roll->length_m,
                'width_cm'       => $w,
                'gsm'            => $roll->gsm,
                'net_kg'         => $roll->net_kg,
                'defects_count'  => $def,
                'defect_pct'     => $roll->length_m > 0 ? round($def / $roll->length_m * 100, 3) : 0,
                'defect_desc'    => $def ? $type . ': ' . ($isRej ? ($t['reject']['detail'] ?? $details[$type]) : $details[$type]) : null,
                'status'         => $isRej ? 'rejected' : 'ok',
                'notes'          => $isRej ? 'مرفوض — ' . ($t['reject']['category'] === 'width' ? 'عرض ' . $w . ' سم' : 'اتوزن لوحده') : null,
            ]);
            $roll->update(['is_inspected' => true]);
        }

        $insp->refresh()->recalc();
        $this->complete($insp, 'تقرير الفحص ' . $insp->doc_no . ' اتحفظ وخلص', $date);

        // تصنيف سبب الرفض وقراره (الميتينج: عرض/بنشر/انكماش قابلة للتفاوض — اللون لا)
        $this->classifyRejections($insp, $t, $date);

        return $insp->refresh();
    }

    private function classifyRejections(FabricInspection $insp, array $t, Carbon $date): void
    {
        $rej = GoodsReceiptRejection::where('fabric_inspection_id', $insp->id)->get();
        foreach ($rej as $r) {
            $spec = $t['state'] === 'rejected' ? ($t + ['resolution' => 'open']) : ($t['reject'] ?? []);
            $upd = ['reason_category' => $spec['category'] ?? 'other'];
            if (($spec['resolution'] ?? 'open') !== 'open') {
                $upd += [
                    'resolution'      => $spec['resolution'],
                    'discount_pct'    => $spec['discount'] ?? null,
                    'resolution_note' => $spec['note'] ?? null,
                    'resolved_by'     => $this->u['purchasing'],
                    'resolved_at'     => $date->copy()->addDays(2)->setTime(13, 0),
                ];
            }
            $r->update($upd);
        }

        /* أتواب اترفضت بوزنها ⇒ المستلم على بند الطلب نقص والبند اتفتح تاني (DocumentEffects).
           لو الطلب كان اتقفل بالوصول، يرجع «جاري الاستلام» بموعد للباقي — المرفوض لسه مطلوب من المورد. */
        $po = $insp->consignment?->purchaseOrder;
        if ($po && $rej->isNotEmpty()) {
            $po->refresh()->load('lines');
            $openLine = $po->lines->contains(fn ($l) => !$l->is_closed && (float) $l->received_qty < (float) $l->min_allowed_qty);
            if ($openLine && $po->stage === 'closed') {
                $po->forceFill([
                    'stage'         => 'receiving',
                    'status'        => 'partially_received',
                    'remainder_eta' => $date->copy()->addDays(10)->toDateString(),
                ])->save();
            }
        }
    }

    /** المعمل المدمج: بنشر لكل توب مفحوص، 2–4 عينات انكماش، مطابقة اللون، وعلامة الفواصل */
    private function lab(Consignment $c, array $t, Carbon $date): LabReport
    {
        $tech = $this->u['lab_tech'];
        $ft = $this->ft->firstWhere('id', $c->fabric_type_id);
        $insp = $c->inspections()->latest('id')->first();

        $samples = $this->r(2, 4);
        $shr = [];
        $hiShrink = ($t['reject']['category'] ?? '') === 'shrinkage';
        for ($s = 1; $s <= 4; $s++) {
            $shr["s{$s}_shrink_len_pct"]   = $s <= $samples ? ($hiShrink ? $this->r(5.5, 7.5, 1) : $this->r(1.5, 4.5, 1)) : null;
            $shr["s{$s}_shrink_width_pct"] = $s <= $samples ? $this->r(1.0, 3.5, 1) : null;
        }

        $lab = LabReport::create([
            'doc_no'              => DocNumber::next('lab_report', 'lab_reports'),
            'paper_serial'        => (string) (2190 + LabReport::count()),
            'doc_date'            => $date->toDateString(),
            'consignment_id'      => $c->id,
            'supplier_id'         => $c->supplier_id,
            'fabric_type_id'      => $c->fabric_type_id,
            'color_id'            => $c->color_id,
            'technician_id'       => $tech,
            'color_match_ok'      => empty($t['color_break']) && ($t['state'] !== 'rejected'),
            'has_color_break'     => !empty($t['color_break']),
            'color_break_roll_no' => $t['color_break'] ?? null,
            'color_break_note'    => !empty($t['color_break']) ? 'فرق درجة واضح بين الأتواب دي وباقي الرسالة — ما تتخلطش في قطعة واحدة' : null,
            'notes'               => !empty($t['gsm_high']) ? 'البنشر أعلى من الحد الأعلى للمواصفة على كل القراءات' : null,
            'status'              => 'draft',
            'created_by'          => $tech,
        ] + $shr);

        $rows = $insp ? $insp->rolls()->where('status', '!=', 'rejected')->get() : collect();
        foreach ($rows as $r) {
            $g = (float) $r->gsm;
            if (!empty($t['gsm_high'])) $g = (float) $ft->spec_gsm_max + $this->r(3, 9);
            LabGsmReading::create(['lab_report_id' => $lab->id, 'roll_no' => $r->roll_no, 'gsm' => $g]);
        }
        $lab->refresh()->recalc();
        $this->complete($lab, 'تقرير المعمل ' . $lab->doc_no . ' اتحفظ مع الفحص ' . ($insp?->doc_no ?? ''), $date);

        return $lab;
    }

    /** إذن استلام الخام = الإفراج. التعليق (لون/بنشر) بيتسجّل على نفس الورقة قبل التخليص */
    private function release(Consignment $c, FabricInspection $insp, array $t, Carbon $date): GoodsReceipt
    {
        $store = $this->u['storekeeper'];
        $c->refresh();

        $gr = GoodsReceipt::create([
            'doc_no'               => DocNumber::next('goods_receipt', 'goods_receipts'),
            'paper_serial'         => (string) (1001520 + GoodsReceipt::count()),
            'doc_date'             => $date->toDateString(),
            'warehouse_id'         => $c->warehouse_id,
            'supplier_id'          => $c->supplier_id,
            'purchase_order_id'    => $c->purchase_order_id,
            'consignment_id'       => $c->id,
            'stock_addition_id'    => $c->sourceAddition()?->id,
            'fabric_inspection_id' => $insp->id,
            'supplier_rep'         => 'مندوب ' . ($c->supplier?->name ?? ''),
            'status'               => 'draft',
            'created_by'           => $store,
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $gr->id,
            'item_code'        => (string) (17910000 + $c->fabric_type_id * 100 + ($c->color_id % 100)),
            'fabric_type_id'   => $c->fabric_type_id,
            'color_id'         => $c->color_id,
            'unit'             => 'كجم',
            'width_cm'         => $c->min_width_cm,
            'rolls_count'      => $c->rolls_count,
            'qty'              => $c->total_kg,
            'consignment_no'   => $c->consignment_no,
        ]);
        $gr->refresh()->recalcTotals();

        // تعليق على نفس الورقة — بيستنى رد التخطيط والمشتريات
        $hold = null;
        if (!empty($t['hold'])) {
            $h = $t['hold'];
            $avgKg = $c->rolls_count > 0 ? (float) $c->total_kg / $c->rolls_count : 20;
            $hold = GoodsReceiptRejection::create([
                'goods_receipt_id'     => $gr->id,
                'fabric_inspection_id' => $insp->id,
                'consignment_id'       => $c->id,
                'color_id'             => $c->color_id,
                'color_code'           => $c->color?->code,
                'lot_label'            => $c->consignment_no,
                'kind'                 => 'on_hold',
                'rolls_count'          => $h['rolls'],
                'qty'                  => round($avgKg * $h['rolls'], 3),
                'unit'                 => 'كجم',
                'party'                => 'planning',
                'reason_category'      => $h['category'],
                'reason'               => $h['reason'],
                'resolution'           => 'open',
                'created_by'           => $store,
            ]);
        }

        $this->complete($gr, 'استلام الخام ' . $gr->doc_no . ' اتسجّل وخلص', $date);

        // قرار التعليق (زي RejectionController::resolve)
        if ($hold && ($t['hold']['resolution'] ?? 'open') !== 'open') {
            $res = $t['hold']['resolution'];
            $hold->update([
                'resolution'      => $res,
                'discount_pct'    => $t['hold']['discount'] ?? null,
                'resolution_note' => $t['hold']['note'] ?? null,
                'resolved_by'     => $this->u['purchasing'],
                'resolved_at'     => $date->copy()->addDays(3)->setTime(14, 0),
            ]);
            if (in_array($res, ['accepted', 'accepted_with_discount'], true)) {
                $c->refresh();
                $c->forceFill(['released_kg' => (float) $c->released_kg + (float) $hold->qty])->save();
                $c->recalcRemaining();
            }
            Notifier::broadcastToRole('finance', 'discount_agreed', 'خصم متفق عليه مع المورد',
                $hold->label . ' — ' . ($t['hold']['discount'] ?? 0) . '% على فاتورة ' . ($c->supplier?->name ?? ''),
                route('rejections.index'), 'info');
            $this->stamp($date->copy()->addDays(3));
        }

        return $gr;
    }

    // ════════════════════════════════════════════════════════════
    //  الماركرات وطلباتها
    // ════════════════════════════════════════════════════════════

    /**
     * ماركرات معتمدة على عروض مختلفة بموديلات متعددة — مبنية على أقل عرض للرسايل
     * الجاهزة عشان أوامر الشغل تلاقي ماركر مناسب، وكمان «ماركرات قديمة» على عروض
     * قريبة بنفس الموديلات عشان اقتراح الماركر القديم يشتغل.
     */
    private function markersAndRequests(): void
    {
        $this->say('الماركرات وطلباتها…');

        $sizes = Size::ordered()->pluck('id')->all();
        $pat   = $this->u['patternist'];
        $pl    = $this->u['planner'];

        $badi = $this->modelIds('بادي', 6); $talb = $this->modelIds('تلبيسة', 4); $tarh = $this->modelIds('طرح', 5);
        $kom  = $this->modelIds('كم', 3);   $andr = $this->modelIds('أندر', 3);   $bant = $this->modelIds('بنطلون', 4);
        $isd  = $this->modelIds('إسدال', 3);

        // [مفتاح, مفتاح الرسالة (العرض منها), الموديلات [id => قطع/فرشة], طول الفرشة, كفاءة, مصنع, أيام, الحالة]
        $defs = [
            ['A1', 'A1', [$badi[0] => 3, $badi[1] => 3],            3.10, 86, 'SIN', 66, 'approved'],
            ['A2', 'A2', [$badi[1] => 2, $badi[2] => 2, $kom[0] => 4], 2.85, 84, 'NSR', 64, 'approved'],
            ['B1', 'B1', [$bant[0] => 2, $bant[1] => 2],            3.40, 82, 'KHD', 57, 'approved'],
            ['B1b','B1', [$bant[1] => 3],                           2.60, 88, 'HRM', 45, 'approved'],
            ['C1', 'C1', [$talb[0] => 4, $talb[1] => 2],            2.95, 85, 'SIN', 50, 'approved'],
            ['C2', 'C2', [$talb[2] => 3, $kom[1] => 3],             2.70, 83, 'AMN', 48, 'approved'],
            ['D1', 'D1', [$kom[0] => 6, $andr[0] => 4],             2.40, 90, 'NSR', 40, 'approved'],
            ['D2', 'D2', [$kom[1] => 5, $andr[1] => 5],             2.50, 89, 'RWD', 38, 'approved'],
            ['E1', 'E1', [$bant[2] => 2, $isd[0] => 1],             3.60, 80, 'AMN', 34, 'approved'],
            ['H1', 'H1', [$talb[3] => 3, $kom[0] => 3],             2.80, 86, 'MDN', 22, 'approved'],
            ['I1', 'I1', [$talb[0] => 4, $talb[2] => 2],            3.00, 84, 'SIN', 12, 'approved'],
            ['G2', 'G2', [$tarh[0] => 2, $tarh[1] => 2],            3.20, 81, 'NSR', 24, 'approved'],
            ['G1', 'G1', [$tarh[0] => 2, $tarh[2] => 2],            3.15, 83, 'HRM', 8,  'approved'],
            // ماركرات قديمة على عروض قريبة — للاقتراح
            ['old1', null, [$kom[0] => 6, $andr[0] => 4],           2.45, 88, 'NSR', 75, 'approved', 'width' => 148],
            ['old2', null, [$tarh[0] => 2, $tarh[1] => 2, $tarh[2] => 1], 3.30, 80, 'HRM', 72, 'approved', 'width' => 163],
            ['old3', null, [$badi[3] => 3, $badi[4] => 3],           3.05, 85, 'KHD', 80, 'approved', 'width' => 183],
            // مسودات عند الباترونست
            ['drf1', 'F1',  [$andr[0] => 5, $andr[2] => 5],          2.30, null, 'SFA', 5, 'draft'],
            ['drf2', null,  [$isd[1] => 1, $isd[2] => 1],            3.80, null, 'MDN', 3, 'draft', 'width' => 183],
        ];

        foreach ($defs as $d) {
            [$key, $ck, $models, $spread, $eff, $fcode, $days, $status] = $d;
            $c = $ck ? ($this->cons[$ck]['c'] ?? null)?->refresh() : null;
            $width = (float) ($d['width'] ?? $c?->min_width_cm ?? 170);
            $date  = $this->day($days);

            $mr = MarkerRequest::create([
                'doc_no'           => DocNumber::next('marker_request', 'marker_requests'),
                'doc_date'         => $date->copy()->subDays(2)->toDateString(),
                'consignment_id'   => $c?->id,
                'factory_id'       => $this->factoryId($fcode),
                'fabric_width_cm'  => $width,
                'avg_gsm'          => $c?->avg_gsm,
                'requested_models' => ProductModel::whereIn('id', array_keys($models))->get()->pluck('label')->implode(' + '),
                'copies'           => 2,
                'assigned_to'      => $pat,
                'needed_by'        => $date->toDateString(),
                'status'           => $status === 'approved' ? 'delivered' : 'in_progress',
                'created_by'       => $pl,
            ]);
            $mr->models()->sync(array_keys($models));

            $marker = Marker::create([
                'code'                  => DocNumber::next('marker', 'markers', 'code'),
                'name'                  => 'تعشيقة ' . ProductModel::find(array_key_first($models))?->name . ' — عرض ' . rtrim(rtrim(number_format($width, 2), '0'), '.'),
                'marker_request_id'     => $mr->id,
                'factory_id'            => $mr->factory_id,
                'created_by_patternist' => $pat,
                'fabric_width_cm'       => $width,
                'marker_width_cm'       => $width - 2,
                'spread_length_m'       => $spread,
                'pieces_per_spread'     => 1,
                'efficiency_pct'        => $eff,
                'status'                => $status,
                'is_active'             => true,
                'notes'                 => $c?->has_color_break ? 'خد بالك: الرسالة فيها فواصل لون — ما تخلطش الأتواب المعلّمة' : null,
                'created_at'            => $date, 'updated_at' => $date,
            ]);
            $li = 0;
            foreach ($models as $mid => $qps) {
                MarkerLine::create([
                    'marker_id'        => $marker->id,
                    'product_model_id' => $mid,
                    'size_id'          => $sizes[$li++ % count($sizes)],
                    'qty_per_spread'   => $qps,
                ]);
            }
            $marker->refresh()->recalcPieces();
            if ($status === 'approved') $mr->forceFill(['marker_id' => $marker->id])->save();
            $this->mk[$key] = $marker->refresh();
        }

        // طلبات ماركر مفتوحة عند الباترونست
        $open = [
            ['J1', [$badi[3] => 3, $badi[4] => 3], 4, $pat,  'old3', 'البادي الشتوي — عايزين نفس تعشيقة الموسم اللي فات لو تنفع'],
            ['F1', [$andr[0] => 5, $andr[2] => 5], 6, null,  null,   'أندر كاب وسكارف — أكبر عدد قطع في الفرشة'],
            ['D4', [$kom[1] => 5, $andr[1] => 5],  2, $pat,  null,   'كم شيفون + أندر سكارف على الدفعة الأخيرة'],
        ];
        foreach ($open as [$ck, $models, $days, $assigned, $suggest, $note]) {
            $c = ($this->cons[$ck]['c'] ?? null)?->refresh();
            if (!$c || !$c->min_width_cm) continue;
            $mr = MarkerRequest::create([
                'doc_no'              => DocNumber::next('marker_request', 'marker_requests'),
                'doc_date'            => $this->day($days)->toDateString(),
                'consignment_id'      => $c->id,
                'fabric_width_cm'     => $c->min_width_cm,
                'avg_gsm'             => $c->avg_gsm,
                'requested_models'    => ProductModel::whereIn('id', array_keys($models))->get()->pluck('label')->implode(' + '),
                'sizes_note'          => 'مقاس 2 و3 بس',
                'copies'              => 3,
                'assigned_to'         => $assigned,
                'needed_by'           => $this->day($days - 5)->toDateString(),
                'suggested_marker_id' => $suggest ? $this->mk[$suggest]->id : null,
                'notes'               => $note,
                'status'              => 'open',
                'created_by'          => $pl,
            ]);
            $mr->models()->sync(array_keys($models));
            Notifier::broadcastToRole('patternist', 'marker_request', 'طلب ماركر جديد',
                $mr->doc_no . ' — الرسالة ' . $c->consignment_no . ' · عرض ' . $c->min_width_cm . ' سم', route('markers.requests'), 'info');
            $this->stamp($this->day($days));
        }
    }

    // ════════════════════════════════════════════════════════════
    //  طلبات التحضير (تقسيم الرسالة)
    // ════════════════════════════════════════════════════════════

    private function preparationRequests(): void
    {
        $this->say('طلبات التحضير…');

        $pl = $this->u['planner']; $st = $this->u['storekeeper'];
        $tarh = $this->modelIds('طرح', 5); $kom = $this->modelIds('كم', 3); $andr = $this->modelIds('أندر', 3);
        $talb = $this->modelIds('تلبيسة', 4);

        // [مفتاح, رسالة, كمية تقريبية, الحالة, فعلي, مصنع, أيام, موديلات, باقي؟, طلب ماركر (مفتوح/ماركر معتمد)]
        $defs = [
            ['PQ1', 'D3', 150, 'open',      null,  'NSR', 4, [$kom[0], $andr[0]], false, 'open'],
            ['PQ2', 'G1', 200, 'weighed',   206.4, 'HRM', 6, [$tarh[0], $tarh[2]], false, 'G1'],
            ['PQ3', 'G2', 900, 'weighed',   891.5, 'NSR', 9, [$tarh[0], $tarh[1]], false, 'G2'],
            ['PQ4', 'D3', 0,   'open',      null,  'RWD', 2, [$kom[0]],            true,  null],
            ['PQ5', 'F1', 300, 'cancelled', null,  'SFA', 11, [$andr[0]],          false, null],
            ['PQ6', 'C2', 120, 'weighed',   116.5, 'SFA', 3, [$talb[2]],           false, null],
        ];

        foreach ($defs as [$key, $ck, $qty, $status, $actual, $fcode, $days, $models, $remainder, $mk]) {
            $c = ($this->cons[$ck]['c'] ?? null)?->refresh();
            if (!$c) continue;
            $c->recalcRemaining(); $c->refresh();
            $date = $this->day($days);
            $req  = $remainder ? (float) $c->remaining_kg : (float) $qty;

            $pr = PreparationRequest::create([
                'doc_no'         => DocNumber::next('preparation_request', 'preparation_requests'),
                'doc_date'       => $date->toDateString(),
                'consignment_id' => $c->id,
                'factory_id'     => $this->factoryId($fcode),
                'requested_qty'  => round($req, 3),
                'is_remainder'   => $remainder,
                'actual_qty'     => $actual,
                'actual_rolls'   => $actual ? (int) round($actual / max(1, (float) $c->total_kg / max(1, $c->rolls_count))) : null,
                'unit'           => 'كجم',
                'status'         => $status,
                'notes'          => $remainder ? 'آخر تقسيمة — الباقي كله من غير وزن' : ($status === 'cancelled' ? 'اتلغى — المصنع اعتذر عن الموعد' : 'يتوزن ويتبعت مع أول عربية'),
                'store_note'     => $actual ? 'اتوزن على ميزان العبور — ' . ($actual > $req ? 'زيادة' : 'نقص') . ' ' . abs(round($actual - $req, 1)) . ' كجم عن التقريبي' : null,
                'requested_by'   => $pl,
                'weighed_by'     => $actual ? $st : null,
                'weighed_at'     => $actual ? $date->copy()->addDay()->setTime(11, 20) : null,
                'created_by'     => $pl,
                'created_at'     => $date, 'updated_at' => $date,
            ]);
            $pr->models()->sync($models);

            if ($mk === 'open') {
                $mr = MarkerRequest::create([
                    'doc_no' => DocNumber::next('marker_request', 'marker_requests'), 'doc_date' => $date->toDateString(),
                    'consignment_id' => $c->id, 'preparation_request_id' => $pr->id, 'fabric_width_cm' => $c->min_width_cm,
                    'avg_gsm' => $c->avg_gsm, 'requested_models' => ProductModel::whereIn('id', $models)->get()->pluck('label')->implode(' + '),
                    'copies' => 2, 'notes' => 'من طلب التحضير ' . $pr->doc_no, 'status' => 'open', 'created_by' => $pl,
                    'suggested_marker_id' => $this->mk['old1']->id ?? null,
                ]);
                $mr->models()->sync($models);
                $pr->update(['marker_request_id' => $mr->id]);
            } elseif ($mk && isset($this->mk[$mk])) {
                $marker = $this->mk[$mk];
                $pr->update(['marker_request_id' => $marker->marker_request_id]);
                MarkerRequest::where('id', $marker->marker_request_id)->update(['preparation_request_id' => $pr->id]);
            }

            if ($status === 'open') {
                Notifier::broadcastToRole('storekeeper', 'prep_request', 'طلب تحضير من التخطيط — اوزن ورد',
                    $pr->doc_no . ' — الرسالة ' . $c->consignment_no . ' · ' . ($remainder ? 'الباقي كله' : $qty . ' كجم تقريبًا'),
                    route('preparation-requests.index'), 'warning');
            } elseif ($status === 'weighed') {
                Notifier::send($pl, 'prep_weighed', 'المخزن وزن طلب التحضير ' . $pr->doc_no,
                    'الرسالة ' . $c->consignment_no . ' — الفعلي ' . $actual . ' كجم (المطلوب كان ' . $qty . ')',
                    route('work-orders.create', ['preparation_request_id' => $pr->id]), 'info');
            }
            $this->stamp($date);

            $c->recalcRemaining();
            $this->pq[$key] = $pr->refresh();
        }
    }

    // ════════════════════════════════════════════════════════════
    //  أوامر الشغل — صرف، قص، استلام
    // ════════════════════════════════════════════════════════════

    private function workOrders(): void
    {
        $this->say('أوامر الشغل والصرف والقص والاستلامات…');

        // to: draft | approved | sent | cutting | in_production | partial | closed
        $defs = [
            ['fab' => [['A1', 'A1', 'all']],                 'f' => 'SIN', 'days' => 60, 'due' => 48, 'to' => 'closed', 'cut' => 0.985],
            ['fab' => [['A2', 'A2', 1200]],                  'f' => 'NSR', 'days' => 58, 'due' => 45, 'to' => 'closed', 'cut' => 1.03],
            ['fab' => [['B1', 'B1', 900], ['A2', 'A2', 500, 'secondary']], 'f' => 'KHD', 'days' => 50, 'due' => 38, 'to' => 'closed', 'cut' => 0.99],
            ['fab' => [['B1', 'B1b', 1000]],                 'f' => 'HRM', 'days' => 40, 'due' => 26, 'to' => 'partial', 'cut' => 1.055,
             'reason' => 'المصنع فرش على 3.05 بدل 3.00 — جزء من القماش طلع أعرض واتعمل له تعشيقة تانية فزاد القص.'],
            ['fab' => [['C1', 'C1', 1500]],                  'f' => 'SIN', 'days' => 28, 'due' => 12, 'to' => 'partial', 'cut' => 0.97],
            ['fab' => [['D1', 'D1', 1200]],                  'f' => 'NSR', 'days' => 35, 'due' => 6,  'to' => 'in_production', 'cut' => 1.0],
            ['fab' => [['E1', 'E1', 800], ['C2', 'C2', 600, 'secondary']], 'f' => 'AMN', 'days' => 30, 'due' => -5, 'to' => 'in_production', 'cut' => 1.02],
            ['fab' => [['H1', 'H1', 'all']],                 'f' => 'MDN', 'days' => 18, 'due' => -7, 'to' => 'cutting'],
            ['fab' => [['C1', 'C1', 1000]],                  'f' => 'SFA', 'days' => 15, 'due' => -9, 'to' => 'cutting', 'issue' => 0.6],
            ['fab' => [['D2', 'D2', 1100]],                  'f' => 'RWD', 'days' => 12, 'due' => -10, 'to' => 'sent'],
            ['fab' => [['I1', 'I1', 1400]],                  'f' => 'SIN', 'days' => 10, 'due' => 2,  'to' => 'sent'],
            ['fab' => [['G2', 'G2', 'pq', 'main', 'PQ3']],   'f' => 'NSR', 'days' => 6,  'due' => -12, 'to' => 'approved'],
            ['fab' => [['B1', 'B1b', 600]],                  'f' => 'KHD', 'days' => 5,  'due' => -14, 'to' => 'approved'],
            ['fab' => [['G1', 'G1', 'pq', 'main', 'PQ2']],   'f' => 'HRM', 'days' => 3,  'due' => -14, 'to' => 'draft'],
            ['fab' => [['C2', null, 'pq', 'main', 'PQ6', 'C2']], 'f' => 'SFA', 'days' => 1, 'due' => -16, 'to' => 'draft'],
        ];

        foreach ($defs as $d) $this->workOrder($d);
    }

    private function workOrder(array $d): ?WorkOrder
    {
        $pl = $this->u['planner']; $follow = $this->u['factory_follow']; $store = $this->u['storekeeper'];
        $date = $this->day($d['days']);
        $factory = Factory::where('code', $d['f'])->first();

        // ── الخامات ──
        $fabrics = [];
        foreach ($d['fab'] as $f) {
            [$ck, $mkKey, $kg] = $f;
            $role = $f[3] ?? 'main';
            $pq   = isset($f[4]) ? $this->pq[$f[4]] : null;
            $c    = ($this->cons[$ck]['c'] ?? null)?->refresh();
            if (!$c) return null;
            $c->recalcRemaining(); $c->refresh();

            $marker = $mkKey ? $this->mk[$mkKey] : null;
            $calcMk = $marker ?: $this->mk[$f[5] ?? $ck];   // الفرشة والقطع من الماركر حتى لو لسه ما اتربطش
            $planned = match (true) {
                $kg === 'all' => (float) $c->remaining_kg,
                $kg === 'pq'  => (float) $pq->qty_for_work_order,
                default       => min((float) $kg, (float) $c->remaining_kg + (float) ($pq?->reserved_qty ?? 0)),
            };
            if ($planned <= 0) return null;

            $fabrics[] = compact('c', 'marker', 'calcMk', 'planned', 'role', 'pq');
        }

        $main = $fabrics[0];
        $title = ProductModel::find($main['calcMk']->lines->first()?->product_model_id)?->name ?? 'منتج';
        $wo = WorkOrder::create([
            'wo_no'         => DocNumber::next('work_order', 'work_orders', 'wo_no'),
            'wo_date'       => $date->toDateString(),
            'factory_id'    => $factory?->id,
            'due_date'      => $this->day($d['due'])->toDateString(),
            'receive_date'  => $this->day($d['due'] - 3)->toDateString(),
            'product_title' => $title . (count($fabrics) > 1 ? ' + ' . (ProductModel::find($fabrics[1]['calcMk']->lines->first()?->product_model_id)?->name ?? '') : ''),
            'qb_code'       => 'KB' . (120 + WorkOrder::count()),
            'marker_copies' => 2,
            'planner_id'    => $pl,
            'status'        => 'draft',
            'cutting_notes' => $main['c']->has_color_break ? 'الرسالة فيها فواصل لون — الأتواب المعلّمة تتقص لوحدها' : null,
            'created_by'    => $pl,
            'created_at'    => $date, 'updated_at' => $date,
        ]);

        foreach ($fabrics as $i => $f) {
            $c = $f['c']; $mk = $f['calcMk'];
            $safe = round((float) $mk->spread_length_m + (float) config('lvplanning.safety_margin_cm', 5) / 100, 3);
            $calc = PlanningEngine::computeFabric([
                'calc_mode' => 'weight', 'spread_length_m' => $mk->spread_length_m, 'spread_length_safe_m' => $safe,
                'fabric_width_m' => round((float) $c->min_width_cm / 100, 3), 'gsm_kg_m2' => round((float) $c->avg_gsm / 1000, 4),
                'pieces_per_spread' => $mk->pieces_per_spread, 'available' => $f['planned'],
            ]);
            if (!($calc['ok'] ?? false) || $calc['expected_pieces'] <= 0) { $wo->delete(); return null; }

            if ($f['pq']) {
                $f['pq']->update(['work_order_id' => $wo->id, 'status' => 'used']);
            }

            WorkOrderFabric::create([
                'work_order_id'          => $wo->id,
                'line_no'                => $i + 1,
                'consignment_id'         => $c->id,
                'preparation_request_id' => $f['pq']?->id,
                'fabric_type_id'         => $c->fabric_type_id,
                'color_id'               => $c->color_id,
                'marker_id'              => $f['marker']?->id,
                'role'                   => $f['role'],
                'calc_mode'              => 'weight',
                'unit'                   => 'كجم',
                'planned_qty'            => round($f['planned'], 3),
                'spread_length_m'        => $mk->spread_length_m,
                'spread_length_safe_m'   => $safe,
                'fabric_width_m'         => round((float) $c->min_width_cm / 100, 3),
                'gsm_kg_m2'              => round((float) $c->avg_gsm / 1000, 4),
                'pieces_per_spread'      => $mk->pieces_per_spread,
                'ply_weight_kg'          => $calc['ply_weight_kg'],
                'consumption_per_piece'  => $calc['consumption_per_piece'],
                'calc_plies'             => $calc['plies'],
                'calc_pieces'            => $calc['expected_pieces'],
                'plies'                  => $calc['plies'],
                'expected_pieces'        => $calc['expected_pieces'],
            ]);
        }

        // ── الموديلات من ماركر الخامة الرئيسية ──
        $wo->refresh()->load('fabrics');
        $mainF = $wo->fabrics->first();
        $plies = (int) $mainF->plies;
        $per   = (float) $mainF->consumption_per_piece;
        foreach ($main['calcMk']->lines as $ml) {
            $qty = $plies * (int) $ml->qty_per_spread;
            WorkOrderLine::create([
                'work_order_id'         => $wo->id,
                'product_model_id'      => $ml->product_model_id,
                'size_id'               => $ml->size_id,
                'qty_per_spread'        => $ml->qty_per_spread,
                'planned_qty'           => $qty,
                'avg_consumption_kg'    => ProductModel::find($ml->product_model_id)?->std_consumption_kg,
                'consumption_per_piece' => $per,
                'planned_kg'            => round($per * $qty, 3),
            ]);
        }
        $wo->refresh()->recalc();
        foreach ($fabrics as $f) $f['c']->recalcRemaining();

        if ($d['to'] === 'draft') { $this->stamp($date); return $wo; }

        // ── اعتماد (حجز على الرسايل) ──
        $this->complete($wo, 'أمر الشغل ' . $wo->wo_no . ' اتسجّل وخلص', $date);
        if ($d['to'] === 'approved') return $wo;

        $wo->forceFill(['status' => 'sent_to_factory'])->save();
        ActivityLogger::log('sent', $wo, 'إرسال أمر شغل للمصنع ' . $wo->wo_no);
        $this->stamp($date->copy()->addDay());
        if ($d['to'] === 'sent') return $wo;

        // ── صرف الخام ──
        $issueDate = $date->copy()->addDays(2);
        $this->materialIssue($wo->refresh()->load('fabrics.consignment'), (float) ($d['issue'] ?? 1.0), $issueDate);
        if ($d['to'] === 'cutting') return $wo;

        // ── بيان القص ──
        $cutDate = $issueDate->copy()->addDays($this->r(3, 6));
        $this->cutDeclaration($wo->refresh(), (float) ($d['cut'] ?? 1.0), $d['reason'] ?? null, $cutDate);
        if ($d['to'] === 'in_production') return $wo;

        // ── استلامات الإنتاج ──
        $wo->refresh()->load('lines');
        $portions = $d['to'] === 'closed' ? [0.55, 1.0] : [$this->r(0.45, 0.6, 2)];
        foreach ($portions as $pi => $portion) {
            $this->productionReceipt($wo->refresh()->load('lines'), $portion, $cutDate->copy()->addDays(4 + $pi * 5), $main['c']);
        }
        return $wo->refresh();
    }

    private function materialIssue(WorkOrder $wo, float $fraction, Carbon $date): void
    {
        $store = $this->u['storekeeper'];
        $mi = MaterialIssue::create([
            'doc_no'        => DocNumber::next('material_issue', 'material_issues'),
            'paper_serial'  => (string) (1303780 + MaterialIssue::count()),
            'doc_date'      => $date->toDateString(),
            'warehouse_id'  => $wo->fabrics->first()?->consignment?->warehouse_id,
            'factory_id'    => $wo->factory_id,
            'issued_to'     => $wo->factory?->name,
            'receiver_name' => $wo->factory?->contact_person,
            'status'        => 'draft',
            'notes'         => $fraction < 1 ? 'صرف جزئي — الباقي مع العربية الجاية' : null,
            'created_by'    => $store,
        ]);
        foreach ($wo->fabrics as $f) {
            $c = $f->consignment;
            $qty = round((float) $f->planned_qty * $fraction, 3);
            $avgRoll = $c && $c->rolls_count > 0 ? (float) $c->total_kg / $c->rolls_count : 20;
            MaterialIssueLine::create([
                'material_issue_id'    => $mi->id,
                'work_order_id'        => $wo->id,
                'work_order_fabric_id' => $f->id,
                'consignment_id'       => $f->consignment_id,
                'fabric_type_id'       => $f->fabric_type_id,
                'color_id'             => $f->color_id,
                'item_code'            => (string) (14810000 + $f->fabric_type_id * 10),
                'unit'                 => 'كجم',
                'width_cm'             => $c?->min_width_cm,
                'rolls_count'          => (int) round($qty / $avgRoll),
                'qty'                  => $qty,
                'consignment_no'       => $c?->consignment_no,
            ]);
        }
        $mi->refresh()->recalcTotals();
        $this->complete($mi, 'إذن الصرف ' . $mi->doc_no . ' اتسجّل وخلص', $date);
    }

    private function cutDeclaration(WorkOrder $wo, float $factor, ?string $reason, Carbon $date): void
    {
        $wo->load('lines', 'fabrics');
        $follow = $this->u['factory_follow'];
        $gov    = $wo->governingFabric();
        $target = (int) $wo->target_qty;
        $spread = (float) ($gov?->effective_spread ?? 3.0);
        $actualSpread = round($spread + ($factor > 1.04 ? 0.05 : $this->r(-0.01, 0.02, 2)), 3);

        $cd = CutDeclaration::create([
            'doc_no'                 => DocNumber::next('cut_declaration', 'cut_declarations'),
            'doc_date'               => $date->toDateString(),
            'work_order_id'          => $wo->id,
            'factory_id'             => $wo->factory_id,
            'actual_spread_length_m' => $actualSpread,
            'actual_plies'           => max(1, (int) round((int) ($gov?->plies ?: 1) * $factor)),
            'used_kg'                => round((float) $wo->fabrics->sum('planned_qty') * min(1, $factor + 0.005), 3),
            'variance_reason'        => $reason,
            'status'                 => 'draft',
            'created_by'             => $follow,
        ]);

        $plannedSum = max(1, (int) $wo->lines->sum('planned_qty'));
        $total = 0;
        foreach ($wo->lines as $wl) {
            $q = (int) floor($target * $factor * ((int) $wl->planned_qty / $plannedSum));
            $total += $q;
            CutDeclarationLine::create([
                'cut_declaration_id' => $cd->id,
                'product_model_id'   => $wl->product_model_id,
                'size_id'            => $wl->size_id,
                'qty'                => $q,
            ]);
        }
        $v = PlanningEngine::cumulativeVariance((float) $target, (float) $total);
        $cd->forceFill([
            'total_pieces'        => $total,
            'actual_kg_per_piece' => $total > 0 ? round((float) $cd->used_kg / $total, 5) : null,
            'variance_pct'        => $v['pct'],
            'variance_flag'       => $v['flag'],
        ])->saveQuietly();

        $this->complete($cd, 'بيان القص ' . $cd->doc_no . ' اتسجّل وخلص', $date);
    }

    private function productionReceipt(WorkOrder $wo, float $portion, Carbon $date, Consignment $mainC): void
    {
        $store = $this->u['storekeeper'];
        $pr = ProductionReceipt::create([
            'doc_no'        => DocNumber::next('production_receipt', 'production_receipts'),
            'doc_date'      => $date->toDateString(),
            'work_order_id' => $wo->id,
            'factory_id'    => $wo->factory_id,
            'warehouse_id'  => $this->warehouseId('FIN'),
            'status'        => 'draft',
            'created_by'    => $store,
        ]);
        foreach ($wo->lines as $li => $wl) {
            $q = $portion >= 1.0 ? (int) $wl->remaining_qty : (int) floor((int) $wl->cut_qty * $portion);
            if ($q <= 0) continue;
            $rej = ($portion >= 1.0 && $li === 0) ? $this->r(0, 6) : 0;   // كام قطعة معيبة راجعة من المصنع
            ProductionReceiptLine::create([
                'production_receipt_id' => $pr->id,
                'product_model_id'      => $wl->product_model_id,
                'size_id'               => $wl->size_id,
                'color_id'              => $mainC->color_id,
                'qty'                   => max(0, $q - $rej),
                'rejected_qty'          => $rej,
            ]);
        }
        $pr->refresh()->recalcTotals();
        if ($pr->total_pieces > 0) $this->complete($pr, 'استلام الإنتاج ' . $pr->doc_no . ' اتسجّل وخلص', $date);
    }

    /** رسالة اتصرفت كلها على أوامر مقفولة ⇒ تتقفل */
    private function closeConsumedConsignments(): void
    {
        foreach (Consignment::whereIn('status', ['released', 'in_production'])->get() as $c) {
            $c->recalcRemaining(); $c->refresh();
            $open = $c->workOrderFabrics()->whereHas('workOrder', fn ($q) => $q->whereNotIn('status', ['closed', 'cancelled', 'superseded']))->exists();
            $hasClosed = $c->workOrderFabrics()->whereHas('workOrder', fn ($q) => $q->where('status', 'closed'))->exists();
            if (!$open && $hasClosed && (float) $c->remaining_kg < 1.0) {
                $c->forceFill(['status' => 'closed', 'notes' => 'اتصرفت بالكامل على أوامر شغل مقفولة'])->save();
                $c->recalcRemaining();
            }
        }
    }

    // ── داتا التخطيط ────────────────────────────────────────────

    private function planningData(): void
    {
        $this->say('المبيعات والأرصدة والفوركاست…');

        $planner = $this->u['planner'];
        $models  = ProductModel::where('is_active', true)->orderBy('id')->get();
        $whFin   = $this->warehouseId('FIN');

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
                    'imported_by' => $planner,
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
                'imported_by' => $planner,
                'created_at'  => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) SalesSnapshot::insert($chunk);

        // الأرصدة — متعمّد يبقى فيه موديلات في الخطر والمراقبة والتمام والمخزون العالي
        $stock = [];
        foreach ($models as $mi => $m) {
            $base = 1200 + (($mi * 431) % 9000);
            $mult = [0.45, 0.8, 1.4, 2.6, 0.25, 1.0, 4.5][$mi % 7];   // أيام التغطية ≈ (mult×30) − أمان
            $stock[] = [
                'pulled_at'        => now()->toDateString(),
                'warehouse_id'     => $whFin,
                'product_model_id' => $m->id,
                'qty_pcs'          => (int) round($base * $mult),
                'reliability'      => $mi % 4 === 0 ? 'counted' : 'book',
                'source'           => 'excel',
                'imported_by'      => $planner,
                'created_at'       => now(), 'updated_at' => now(),
            ];
            SafetyStock::updateOrCreate(
                ['product_model_id' => $m->id, 'color_id' => null],
                ['qty_pcs' => (int) round($base * 0.12), 'cover_days' => 15,
                 'notes' => 'مبدئي — يتراجع كل ربع سنة', 'updated_by' => $planner]
            );
        }
        StockSnapshot::insert($stock);

        // نسب الألوان + فوركاست لأكبر 10 موديلات
        $basics = Color::basic()->pluck('id')->all();
        $extra  = [$this->colorId('كحلي'), $this->colorId('بيج')];
        foreach ($models->take(10) as $k => $m) {
            $ratios = $k % 2 === 0
                ? array_combine($basics, [45, 30, 25])
                : array_combine(array_merge($basics, $extra), [35, 20, 15, 18, 12]);
            foreach ($ratios as $cid => $pct) {
                \App\Models\ColorRatio::updateOrCreate(
                    ['product_model_id' => $m->id, 'color_id' => $cid, 'year' => now()->year, 'month' => null],
                    ['ratio_pct' => $pct, 'source' => $k % 3 === 0 ? 'manual' : 'issues', 'updated_by' => $planner,
                     'notes' => $k % 3 === 0 ? 'اتعدلت يدوي بعد مراجعة المبيعات' : 'مستنتجة من صرف المخزن — محتاجة مراجعة']
                );
            }
            ForecastService::generate($m->id, now()->year + 1, 8 + ($k % 3) * 2, $ratios, $planner);
        }
    }

    // ── النقاشات على المستندات ──────────────────────────────────

    private function discussions(): void
    {
        $this->say('نقاشات المستندات…');

        $u = $this->u;
        $threads = [
            [PurchaseOrder::class, [
                ['planner','question','الطلب ده محتاج يوصل قبل نهاية الشهر — ينفع نضغط على المورد؟'],
                ['purchasing','answer','كلمت المورد، قال أقصى حاجة 10 أيام. لو عايزين أسرع هنحتاج نقسّمه على موردين.'],
                ['finance','note','المبلغ ده كبير على دفعة واحدة — هنقسّمه على دفعتين مع التوريد.'],
            ]],
            [FabricInspection::class, [
                ['inspector','note','فيه توبين فيهم برادة خفيفة على الجنب. مش مؤثرة على التعشيقة بس حبيت أسجّلها.'],
                ['planner','question','البرادة دي هتاثر على العرض المستخدم؟'],
                ['inspector','answer','لا، هي على 1 سم من الجنب والماركر بتاعنا أضيق من كده بـ 2 سم.'],
            ]],
            [WorkOrder::class, [
                ['factory_follow','question','المصنع بيقول القماش فيه جزء أعرض شوية — ينفع يعمل له تعشيقة تانية؟'],
                ['planner','decision','موافق، بس بشرط يبعتلنا بيان قص منفصل للجزء ده عشان الحسبة تفضل مظبوطة.'],
                ['factory_follow','answer','تمام، بلغته وهيبعت البيانين.'],
            ]],
            [GoodsReceipt::class, [
                ['storekeeper','note','استلمنا الرسالة كاملة، الأتواب مطابقة للجرد.'],
                ['gm','decision','تمام — اعتمد وافرج عنها.'],
            ]],
            [LabReport::class, [
                ['lab_tech','note','الانكماش في الحدود، بس اللون فيه فرق بسيط عن العينة المعتمدة.'],
                ['planner','question','الفرق ده هيبان في المنتج النهائي؟'],
                ['lab_tech','answer','لو الرسالة هتتشغّل لوحدها مش هيبان. الممنوع إننا نخلطها مع رسالة تانية.'],
            ]],
            [PreparationRequest::class, [
                ['planner','note','الكمية تقريبية — اللي يطلع على الميزان هو اللي هيدخل أمر الشغل.'],
                ['storekeeper','answer','اتوزنت على ميزان العبور، الفرق 6 كيلو زيادة عن التقريبي.'],
            ]],
            [Consignment::class, [
                ['stock_controller','question','الرسالة دي اتجردت فعلًا ولا الرقم من المورد؟'],
                ['inspector','answer','اتجردت توب توب — الفرق كان توب واحد ناقص واتسجل في التقرير.'],
            ]],
            [Marker::class, [
                ['patternist','note','الكفاءة وصلت 86% بعد ما لفّيت المقاس 3 — أحسن من التعشيقة القديمة بـ 4%.'],
                ['planner','decision','تمام، اعتمده واستخدمه على كل رسايل نفس العرض.'],
            ]],
        ];

        foreach ($threads as [$class, $msgs]) {
            $docs = $class::latest('id')->limit(6)->get();
            foreach ($docs as $di => $doc) {
                if ($di % 2) continue;   // مش كل المستندات فيها نقاش
                $base = ($doc->doc_date ?? $doc->po_date ?? $doc->wo_date ?? $doc->arrival_date ?? $doc->created_at ?? now());
                $base = $base instanceof Carbon ? $base->copy() : Carbon::parse($base);
                foreach ($msgs as $mi => [$who, $kind, $body]) {
                    $at = $base->copy()->addHours(9 + $mi * 5 + $di);
                    DocumentComment::create([
                        'commentable_type' => $class,
                        'commentable_id'   => $doc->id,
                        'user_id'          => $u[$who] ?? null,
                        'body'             => $body,
                        'kind'             => $kind,
                        'created_at'       => $at,
                        'updated_at'       => $at,
                    ]);
                }
            }
        }
    }

    /** إشعارات إضافية + الإشعارات القديمة بتتعلّم مقروءة عشان الجرس يبان بشكل واقعي */
    private function notificationsTouch(): void
    {
        $u = $this->u;
        $late = PurchaseOrder::where('stage', 'approved')->whereDate('delivery_date', '<', now()->toDateString())->first();
        if ($late) {
            foreach (['purchasing', 'planner'] as $r) {
                Notifier::send($u[$r], 'po_late', 'طلب شراء اتأخر عن موعد التوريد',
                    $late->po_no . ' — ' . ($late->supplier?->name ?? '') . ' كان المفروض يوصل ' . $late->delivery_date?->format('Y-m-d'),
                    route('purchase-orders.index'), 'warning');
            }
        }
        $ack = PurchaseOrder::whereNotNull('sourced_at')->whereNull('finance_at')->first();
        if ($ack) {
            Notifier::send($u['finance'], 'po_finance', 'طلب شراء اتسعّر ومستني علمك',
                $ack->po_no . ' — ' . number_format((float) $ack->total) . ' ' . config('lvplanning.currency') . ' لـ ' . ($ack->supplier?->name ?? ''),
                route('finance.payables'), 'info');
        }
        foreach (WorkOrder::late()->get() as $wo) {
            Notifier::send($u['factory_follow'], 'wo_late', 'أمر شغل اتأخر عن موعده',
                $wo->wo_no . ' — ' . ($wo->factory?->name ?? '') . ' · كان مطلوب ' . $wo->due_date?->format('Y-m-d'),
                route('work-orders.index'), 'danger');
        }
        $this->stamp(now()->subDay());

        AppNotification::whereDate('created_at', '<', now()->subDays(7)->toDateString())
            ->update(['read_at' => now()->subDays(5)]);
    }
}
