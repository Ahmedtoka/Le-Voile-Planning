<?php

namespace App\Http\Controllers;

use App\Exports\TableExport;
use App\Imports\ColorsImport;
use App\Imports\SalesImport;
use App\Imports\StockImport;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\ProductModel;
use App\Models\WorkOrder;
use App\Services\CoverageService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
    public function index()
    {
        return view('settings.import', ['title' => 'استيراد وتصدير']);
    }

    // ── استيراد ──

    public function importColors(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt']], [], ['file' => 'الملف']);

        $import = new ColorsImport;
        Excel::import($import, $request->file('file'));

        $msg = "تم: {$import->created} جديد، {$import->updated} محدّث، {$import->merged} مدموج.";
        if ($import->errors) {
            return back()->with('success', $msg)->withErrors(['msg' => implode(' | ', array_slice($import->errors, 0, 10))]);
        }

        return back()->with('success', $msg);
    }

    public function importSales(Request $request)
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
        ], [], ['file' => 'الملف', 'period_from' => 'من تاريخ', 'period_to' => 'إلى تاريخ']);

        $import = new SalesImport($data['period_from'], $data['period_to'], auth()->id());
        Excel::import($import, $request->file('file'));

        $msg = "تم استيراد {$import->imported} سطر.";
        if ($import->warnings) {
            $msg .= " ⚠ فيه {$import->warnings} سطر رقمه شاذ — يحتمل خطأ دستة/قطعة، راجعهم.";
        }

        return back()->with('success', $msg);
    }

    public function importStock(Request $request)
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'pulled_at'   => ['required', 'date'],
            'reliability' => ['required', 'in:counted,book,estimated'],
        ], [], ['file' => 'الملف', 'pulled_at' => 'تاريخ السحب']);

        $import = new StockImport($data['pulled_at'], $data['reliability'], auth()->id());
        Excel::import($import, $request->file('file'));

        return back()->with('success', "تم استيراد {$import->imported} سطر رصيد.");
    }

    // ── تصدير ──

    public function exportColors()
    {
        $rows = Color::with('mergedInto')->orderBy('code')->get()->map(fn ($c) => [
            $c->code, $c->name, $c->family, $c->status_name,
            $c->mergedInto?->code, $c->is_basic ? 'نعم' : 'لا', $c->legacy_code,
        ])->all();

        return Excel::download(
            new TableExport(['كود اللون','الاسم','العائلة','الحالة','مدموج في','أساسي','الكود القديم'], $rows, 'الألوان'),
            'colors-' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function exportConsignments()
    {
        $rows = Consignment::with(['supplier','fabricType','color'])->latest('id')->get()->map(fn ($c) => [
            $c->consignment_no, $c->arrival_date?->format('Y-m-d'), $c->supplier?->name,
            $c->fabricType?->name, $c->color?->code, $c->total_kg, $c->rolls_count,
            $c->min_width_cm, $c->avg_gsm, $c->defect_pct, $c->remaining_kg, $c->status_name,
        ])->all();

        return Excel::download(
            new TableExport(
                ['رقم الرسالة','التاريخ','المورد','الخامة','اللون','الوزن (كجم)','الأتواب',
                 'أقل عرض','متوسط البنشر','نسبة العيوب %','المتبقي (كجم)','الحالة'],
                $rows, 'الأحواض'
            ),
            'consignments-' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function exportWorkOrders()
    {
        $rows = WorkOrder::with(['fabrics.consignment', 'fabrics.color', 'factory'])->latest('id')->get()
            ->map(fn ($w) => [
                $w->wo_no, $w->wo_date?->format('Y-m-d'), $w->product_title,
                $w->fabrics->pluck('consignment.consignment_no')->filter()->implode('، '),
                $w->fabrics->pluck('color.code')->filter()->implode('، '),
                $w->factory?->name,
                round((float) $w->fabrics->sum('planned_qty'), 2),
                $w->computed_governing_qty, $w->target_qty, $w->cut_pieces,
                $w->received_pieces, $w->outstanding_pieces, $w->variance_pct, $w->status_name,
            ])->all();

        return Excel::download(
            new TableExport(
                ['أمر الشغل','التاريخ','المنتج','الرسائل','الألوان','المصنع','إجمالي الخامة',
                 'الحاكمة','المستهدف','المقصوص','المستلم','المتبقي','الانحراف %','الحالة'],
                $rows, 'أوامر الشغل'
            ),
            'work-orders-' . now()->format('Ymd') . '.xlsx'
        );
    }

    public function exportCoverage()
    {
        $rows = collect(CoverageService::overview())->map(fn ($r) => [
            $r['model']->code, $r['model']->name, $r['sold_window'], $r['avg_daily'],
            $r['stock'], $r['safety'], $r['usable'], $r['cover_days'], $r['flag_label'],
        ])->all();

        return Excel::download(
            new TableExport(
                ['الكود','الموديل','مبيعات 30 يوم','متوسط يومي','الرصيد','مخزون الأمان',
                 'المتاح','أيام التغطية','الحالة'],
                $rows, 'التغطية'
            ),
            'coverage-' . now()->format('Ymd') . '.xlsx'
        );
    }

    /** قوالب الاستيراد الفاضية */
    public function template(string $type)
    {
        [$headings, $sample, $name] = match ($type) {
            'colors' => [
                ['code','name','family','hex','is_basic','legacy_code','merged_into'],
                [['BLK-001','أسود','أسود','#000000','1','5',''], ['BRN-600','بني','بني','#6b4f2a','0','600','BRN-005']],
                'template-colors',
            ],
            'sales' => [
                ['model_code','qty','unit'],
                [['BODY-001','5000','قطعة'], ['SAB-002','420','دستة']],
                'template-sales',
            ],
            'stock' => [
                ['model_code','color_code','warehouse_code','qty'],
                [['BODY-001','BLK-001','043','1200']],
                'template-stock',
            ],
            default => [['code','name'], [], 'template'],
        };

        return Excel::download(new TableExport($headings, $sample, 'قالب'), $name . '.xlsx');
    }
}
