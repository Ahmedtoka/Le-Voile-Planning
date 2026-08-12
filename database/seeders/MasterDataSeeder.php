<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\FabricType;
use App\Models\Factory;
use App\Models\ModelBom;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/** بيانات أساسية ديمو — مبنية على المستندات الورقية الفعلية. */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── الموردين ──
        foreach ([
            ['196', 'مالك الدملشي', 'أ/ مالك', '01000000196', 'العبور', 'آجل 60 يوم'],
            ['043', 'الصنع تكس',    'أ/ سيد',  '01000000043', 'العاشر من رمضان', 'آجل 30 يوم'],
            ['MRC', 'مارشيلو',      'أ/ مارك', '01000000777', 'المحلة', 'نقدي'],
        ] as [$code, $name, $person, $phone, $addr, $terms]) {
            Supplier::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person, 'phone' => $phone,
                'address' => $addr, 'payment_terms' => $terms, 'is_active' => true,
            ]);
        }

        // ── المصانع ──
        foreach ([
            ['SIN', 'سيني',  'أ/ سيني', '01100000001', 4000, 7],
            ['KHD', 'خالد',  'أ/ خالد', '01100000002', 2500, 5],
            ['NSR', 'النصر', 'أ/ نصر',  '01100000003', 6000, 10],
        ] as [$code, $name, $person, $phone, $cap, $cycle]) {
            Factory::updateOrCreate(['code' => $code], [
                'name' => $name, 'contact_person' => $person, 'phone' => $phone,
                'daily_capacity_pcs' => $cap, 'avg_cycle_days' => $cycle, 'is_active' => true,
            ]);
        }

        // ── المخازن ──
        foreach ([
            ['OBR', 'مخزن العبور',        'fabric'],
            ['043', 'مخزن الخامات 043',   'fabric'],
            ['ACC', 'مخزن الإكسسوارات',   'accessories'],
            ['FIN', 'مخزن المنتج التام',  'finished'],
        ] as [$code, $name, $type]) {
            Warehouse::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'is_active' => true,
                'last_stock_count_at' => now()->subDays(20)->toDateString(),
            ]);
        }

        // ── الخامات ──
        FabricType::updateOrCreate(['code' => 'VSC-FL'], [
            'name' => 'فسكوز فل ليكرا',
            'composition' => '95% Viscose / 5% Lycra',
            'spec_width_cm' => 185, 'spec_width_min_cm' => 170,
            'spec_gsm' => 200, 'spec_gsm_min' => 190, 'spec_gsm_max' => 210,
            'max_shrink_len_pct' => 5, 'max_shrink_width_pct' => 4, 'max_defect_pct' => 3,
            'is_active' => true,
        ]);
        FabricType::updateOrCreate(['code' => 'CTN-LY'], [
            'name' => 'قطن ليكرا',
            'composition' => '92% Cotton / 8% Lycra',
            'spec_width_cm' => 175, 'spec_width_min_cm' => 165,
            'spec_gsm' => 180, 'spec_gsm_min' => 170, 'spec_gsm_max' => 195,
            'max_shrink_len_pct' => 6, 'max_shrink_width_pct' => 4, 'max_defect_pct' => 3,
            'is_active' => true,
        ]);

        /* ── الألوان ──
         | الأساسيات (ليها داتا تاريخية) + مثال حي لمشكلة تكرار الأكواد:
         | "بني كود 5" رجع من الصباغة بفرق بسيط فاتعمله كود 600، وبعدين
         | 601 و 602 — وده اللي وصّلهم لآلاف الأكواد. الدمج هنا بيوضح الحل.
        */
        $basics = [
            ['BLK-001', 'أسود',      'أسود',      '#111111', true],
            ['WHT-001', 'أبيض',      'أبيض',      '#ffffff', true],
            ['OFW-001', 'أوف وايت',  'أبيض',      '#f2ece2', true],
        ];
        foreach ($basics as [$code, $name, $family, $hex, $basic]) {
            Color::updateOrCreate(['code' => $code], [
                'name' => $name, 'family' => $family, 'hex' => $hex,
                'is_basic' => $basic, 'status' => 'active',
            ]);
        }

        $others = [
            ['BRN-005', 'بني كود 5',   'بني',   '#6b4f2a'],
            ['BRN-600', 'بني كود 600', 'بني',   '#6d5130'],
            ['BRN-601', 'بني كود 601', 'بني',   '#705333'],
            ['BEG-010', 'بيج',         'بيج',   '#d8c7a9'],
            ['NVY-020', 'كحلي',        'أزرق',  '#22314f'],
            ['GRY-030', 'رمادي',       'رمادي', '#8d8d8d'],
        ];
        foreach ($others as [$code, $name, $family, $hex]) {
            Color::updateOrCreate(['code' => $code], [
                'name' => $name, 'family' => $family, 'hex' => $hex,
                'is_basic' => false, 'status' => 'active',
            ]);
        }

        // مثال دمج: 600 و 601 اتدمجوا في 005 (نفس اللون بفروق صباغة)
        foreach (['BRN-600', 'BRN-601'] as $dupCode) {
            $from = Color::where('code', $dupCode)->first();
            $to   = Color::where('code', 'BRN-005')->first();
            if ($from && $to && $from->status === 'active') {
                Color::merge($from, $to, null, 'نفس اللون — فرق صباغة بسيط. دمج ضمن تنظيف الأكواد.');
            }
        }

        // ── المقاسات ──
        foreach ([['S','مقاس 1',1], ['M','مقاس 2',2], ['L','مقاس 3',3], ['XL','مقاس 4',4]] as [$c,$n,$o]) {
            Size::updateOrCreate(['code' => $c], ['name' => $n, 'sort_order' => $o, 'is_active' => true]);
        }

        // ── الموديلات ──
        $vsc = FabricType::where('code', 'VSC-FL')->value('id');
        $models = [
            ['BODY-001', 'بادي كات',        'مكملات', 12],
            ['SAB-002',  'بادي سابرينا',    'مكملات', 12],
            ['PNT-003',  'بنطلون',          'مكملات', 12],
            ['TLB-004',  'تلبيسة',          'مكملات', 12],
        ];
        foreach ($models as [$code, $name, $cat, $doz]) {
            $m = ProductModel::updateOrCreate(['code' => $code], [
                'name' => $name, 'category' => $cat, 'fabric_type_id' => $vsc,
                'pcs_per_dozen' => $doz, 'is_active' => true,
            ]);
            $m->sizes()->sync(Size::pluck('id'));
        }

        // ── الإكسسوارات ──
        $accs = [
            ['BAG-S2',  'كيس مقاس 2',     'bag',     'قطعة', 50000, 5000, true],
            ['BAG-S3',  'كيس مقاس 3',     'bag',     'قطعة', 40000, 5000, true],
            ['STK-001', 'استيكر الموديل', 'sticker', 'قطعة', 80000, 10000, true],
            ['DOZ-BAG', 'كيس دستة',       'bag',     'قطعة', 8000,  1000, true],
            ['BTN-001', 'زرار',           'button',  'قطعة', 20000, 3000, false],
            ['ZIP-001', 'سوستة',          'zipper',  'قطعة', 6000,  1000, false],
        ];
        foreach ($accs as [$code, $name, $type, $unit, $qty, $rop, $shared]) {
            Accessory::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'unit' => $unit,
                'stock_qty' => $qty, 'reorder_point' => $rop,
                'is_shared' => $shared, 'is_active' => true,
            ]);
        }

        // ── BOM: كل قطعة ليها كيس واستيكر، والدستة ليها كيس دستة ──
        $bagS2  = Accessory::where('code','BAG-S2')->value('id');
        $sticker= Accessory::where('code','STK-001')->value('id');
        $dozBag = Accessory::where('code','DOZ-BAG')->value('id');
        $button = Accessory::where('code','BTN-001')->value('id');

        foreach (ProductModel::all() as $m) {
            ModelBom::updateOrCreate(
                ['product_model_id' => $m->id, 'accessory_id' => $bagS2, 'size_id' => null],
                ['qty_per_piece' => 1, 'notes' => 'كيس القطعة']
            );
            ModelBom::updateOrCreate(
                ['product_model_id' => $m->id, 'accessory_id' => $sticker, 'size_id' => null],
                ['qty_per_piece' => 1, 'notes' => 'استيكر الموديل']
            );
            ModelBom::updateOrCreate(
                ['product_model_id' => $m->id, 'accessory_id' => $dozBag, 'size_id' => null],
                ['qty_per_piece' => 1 / max(1, $m->pcs_per_dozen), 'notes' => 'كيس الدستة — قطعة لكل دستة']
            );
        }

        // البنطلون بس فيه زراير
        $pnt = ProductModel::where('code','PNT-003')->first();
        if ($pnt) {
            ModelBom::updateOrCreate(
                ['product_model_id' => $pnt->id, 'accessory_id' => $button, 'size_id' => null],
                ['qty_per_piece' => 2, 'notes' => 'زرارين للبنطلون']
            );
        }
    }
}
