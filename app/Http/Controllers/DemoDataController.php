<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DemoDataService;
use App\Services\PaperSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * أدوات الداتا — للأدمن بس.
 *
 * زرار بيمسح كل بيانات الشغل (ويسيب المستخدمين والأدوار ودورات الاعتماد)،
 * وزرار بيولّد داتا ديمو كاملة تخلّي السيستم يبان شغّال من أول لحظة.
 */
class DemoDataController extends Controller
{
    public function index()
    {
        return view('settings.data', [
            'title'  => 'أدوات الداتا',
            'counts' => $this->counts(),
        ]);
    }

    /** ① ديمو كامل — السيستم وهو شغّال بكل مستنداته */
    public function generate(Request $request)
    {
        $request->validate(['confirm' => ['required', 'in:توليد']], [], ['confirm' => 'التأكيد']);

        @set_time_limit(900);

        // generate() بيمسح الأول لوحده — أرقام المستندات متسلسلة
        $stats = (new DemoDataService())->generate();
        ActivityLogger::log('demo_generated', null, 'توليد ديمو كامل');

        return back()->with('info',
            '<b>تم توليد الديمو الكامل.</b> السيستم دلوقتي فيه مستندات في كل مرحلة — '
            . 'ادخل بأي دور وشوف الرقم الأحمر جنب شاشاته.<br><span class="hint">'
            . e(collect($stats)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ')) . '</span>');
    }

    /** ⓪ الورق الحقيقي — المستندات الفعلية بأرقامها */
    public function generatePaper(Request $request)
    {
        $request->validate(['confirm' => ['required', 'in:الورق']], [], ['confirm' => 'التأكيد']);

        @set_time_limit(600);
        $stats = (new PaperSeeder())->run();
        ActivityLogger::log('paper_generated', null, 'إدخال المستندات الورقية');

        return back()->with('info',
            '<b>تم إدخال الورق الحقيقي.</b> قارن كل شاشة بالورقة اللي في إيدك: '
            . '<a href="' . route('purchase-orders.index') . '">طلب شراء 107</a> · '
            . '<a href="' . route('goods-receipts.index') . '">استلام 1000885</a> · '
            . '<a href="' . route('work-orders.index') . '">KB106</a> · '
            . '<a href="' . route('material-issues.index') . '">صرف 1303774</a>'
            . '<br><span class="hint">' . e(collect($stats)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ')) . '</span>');
    }

    /** ② بيانات أساسية بس — نقطة بداية للشغل الحقيقي */
    public function generateMaster(Request $request)
    {
        $request->validate(['confirm' => ['required', 'in:أساسية']], [], ['confirm' => 'التأكيد']);

        @set_time_limit(600);

        $stats = (new DemoDataService())->generateMasterOnly();
        ActivityLogger::log('master_generated', null, 'توليد بيانات أساسية');

        return back()->with('info',
            '<b>تم تجهيز البيانات الأساسية.</b> مفيش أي طلبات ولا أحواض ولا أوامر — '
            . 'ابدأ من <a href="' . route('purchase-orders.index') . '">طلبات الشراء</a> وامشِ الدورة بنفسك.'
            . '<br><span class="hint">' . e(collect($stats)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ')) . '</span>');
    }

    public function reset(Request $request)
    {
        $request->validate(['confirm' => ['required', 'in:مسح']], [], ['confirm' => 'التأكيد']);

        (new DemoDataService())->reset();
        ActivityLogger::log('data_reset', null, 'مسح بيانات الشغل');

        return back()->with('success', 'تم مسح كل بيانات الشغل. المستخدمون والأدوار ودورات الاعتماد زي ما هم.');
    }

    private function counts(): array
    {
        $out = [];
        foreach ([
            'suppliers' => 'موردين', 'factories' => 'مصانع', 'warehouses' => 'مخازن',
            'material_issues' => 'أذون صرف', 'goods_receipt_rejections' => 'رفض وتعليق',
            'work_order_fabrics' => 'خامات أوامر',
            'fabric_types' => 'خامات', 'colors' => 'ألوان', 'product_models' => 'موديلات',
            'accessories' => 'إكسسوارات', 'purchase_orders' => 'طلبات شراء',
            'stock_additions' => 'أذون إضافة', 'consignments' => 'أحواض',
            'fabric_inspections' => 'تقارير فحص', 'lab_reports' => 'تقارير معمل',
            'goods_receipts' => 'أذون استلام', 'markers' => 'ماركرات',
            'work_orders' => 'أوامر شغل', 'cut_declarations' => 'بيانات قص',
            'production_receipts' => 'استلامات إنتاج', 'document_comments' => 'تعليقات',
            'sales_snapshots' => 'لقطات مبيعات', 'forecasts' => 'سطور فوركاست',
            'users' => 'مستخدمين',
        ] as $table => $label) {
            $out[$label] = DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : 0;
        }
        return $out;
    }
}
