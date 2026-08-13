<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\DemoDataService;
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

    public function generate(Request $request)
    {
        $request->validate(['confirm' => ['required', 'in:توليد']], [], ['confirm' => 'التأكيد']);

        @set_time_limit(600);

        // generate() بيمسح الأول لوحده — أرقام المستندات متسلسلة
        $stats = (new DemoDataService())->generate();
        ActivityLogger::log('demo_generated', null, 'توليد داتا ديمو');

        $summary = collect($stats)->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ');

        return back()->with('info', 'تم توليد الداتا الديمو.<br><span class="hint">' . e($summary) . '</span>');
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
