<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\ColorRatio;
use App\Models\Consignment;
use App\Models\Forecast;
use App\Models\Marker;
use App\Models\ProductModel;
use App\Models\SafetyStock;
use App\Services\CoverageService;
use App\Services\ForecastService;
use App\Services\PlanningEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanningController extends Controller
{
    /**
     * الحاسبة المستقلة — بتديك الأرقام قبل ما تعمل أمر شغل أصلًا.
     * مفيدة للباترونست والمخطط وهم بيجربوا سيناريوهات.
     */
    public function calculator(Request $request)
    {
        $result = null;
        $impact = null;

        if ($request->filled('width_cm')) {
            $result = PlanningEngine::compute(
                widthCm:         (float) $request->get('width_cm'),
                gsm:             (float) $request->get('gsm'),
                spreadLengthM:   (float) $request->get('spread_length_m'),
                piecesPerSpread: (int) $request->get('pieces_per_spread'),
                availableKg:     (float) $request->get('available_kg', 0),
                rollLengthM:     $request->filled('roll_length_m') ? (float) $request->get('roll_length_m') : null,
            );

            if ($request->filled('actual_spread_length_m') && $request->filled('roll_length_m')) {
                $impact = PlanningEngine::spreadImpact(
                    (float) $request->get('roll_length_m'),
                    (float) $request->get('spread_length_m'),
                    (float) $request->get('actual_spread_length_m'),
                    (int) $request->get('pieces_per_spread'),
                );
            }
        }

        return view('forecast.calculator', [
            'title'  => 'حاسبة التخطيط',
            'result' => $result,
            'impact' => $impact,
            'input'  => $request->all(),
        ]);
    }

    /** أيام التغطية — الشاشة اللي بتتفتح كل صبح */
    public function coverage()
    {
        return view('forecast.coverage', [
            'title' => 'أيام التغطية',
            'rows'  => CoverageService::overview(),
        ]);
    }

    /** نسب الألوان — يدوية وقابلة للتعديل */
    public function colorRatios(Request $request)
    {
        $modelId = $request->get('product_model_id');
        $year    = (int) $request->get('year', now()->year);

        $rows = collect();
        if ($modelId) {
            $rows = ColorRatio::with('color')
                ->where('product_model_id', $modelId)
                ->where('year', $year)
                ->whereNull('month')
                ->orderByDesc('ratio_pct')->get();
        }

        return view('forecast.color_ratios', [
            'title'   => 'نسب الألوان',
            'models'  => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'  => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'rows'    => $rows,
            'modelId' => $modelId,
            'year'    => $year,
            'derived' => $modelId ? ForecastService::deriveColorRatios((int) $modelId, $year) : [],
            'total'   => (float) $rows->sum('ratio_pct'),
        ]);
    }

    public function saveColorRatios(Request $request)
    {
        $data = $request->validate([
            'product_model_id' => ['required', 'exists:product_models,id'],
            'year'             => ['required', 'integer', 'min:2000', 'max:2100'],
            'ratios'           => ['required', 'array'],
            'ratios.*.color_id'  => ['required', 'exists:colors,id'],
            'ratios.*.ratio_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], ['ratios' => 'النسب']);

        $sum = array_sum(array_column($data['ratios'], 'ratio_pct'));
        if (abs($sum - 100) > 0.5) {
            return back()->withErrors(['msg' => 'مجموع النسب لازم يساوي 100% — دلوقتي هو ' . round($sum, 2) . '%.']);
        }

        DB::transaction(function () use ($data) {
            foreach ($data['ratios'] as $r) {
                ColorRatio::updateOrCreate(
                    [
                        'product_model_id' => $data['product_model_id'],
                        'color_id'         => $r['color_id'],
                        'year'             => $data['year'],
                        'month'            => null,
                    ],
                    ['ratio_pct' => $r['ratio_pct'], 'source' => 'manual', 'updated_by' => auth()->id()]
                );
            }
        });

        return back()->with('success', 'تم حفظ النسب.');
    }

    /** الفوركاست */
    public function forecast(Request $request)
    {
        $year    = (int) $request->get('year', now()->year);
        $modelId = $request->get('product_model_id');

        $q = Forecast::with(['productModel', 'color'])->where('year', $year);
        if ($modelId) $q->where('product_model_id', $modelId);

        // الإجماليات لازم تتحسب قبل paginate — لأن paginate بيضيف limit/offset على نفس الـ query
        $totals = [
            'forecast' => (float) (clone $q)->sum('forecast_qty'),
            'actual'   => (float) (clone $q)->sum('actual_qty'),
        ];

        $rows = $q->orderBy('product_model_id')->orderBy('month')->paginate(100)->withQueryString();

        return view('forecast.index', [
            'title'   => 'الفوركاست ' . $year,
            'rows'    => $rows,
            'year'    => $year,
            'modelId' => $modelId,
            'models'  => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'totals'  => $totals,
        ]);
    }

    public function generateForecast(Request $request)
    {
        $data = $request->validate([
            'product_model_id' => ['required', 'exists:product_models,id'],
            'year'             => ['required', 'integer', 'min:2000', 'max:2100'],
            'growth_pct'       => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ], [], ['growth_pct' => 'نسبة النمو']);

        $count = ForecastService::generate(
            (int) $data['product_model_id'],
            (int) $data['year'],
            (float) ($data['growth_pct'] ?? 0),
            [],
            auth()->id()
        );

        return back()->with('success', "تم توليد {$count} سطر فوركاست. راجعهم قبل الاعتماد — المصدر داتا غير مكتملة.");
    }

    public function syncActuals(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $n = ForecastService::syncActuals($year);
        return back()->with('success', "تم تحديث الفعلي على {$n} سطر.");
    }

    /** مخزون الأمان */
    public function safetyStock(Request $request)
    {
        return view('forecast.safety_stock', [
            'title'  => 'مخزون الأمان',
            'rows'   => SafetyStock::with(['productModel', 'color'])->orderBy('product_model_id')->paginate(50),
            'models' => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors' => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
        ]);
    }

    public function saveSafetyStock(Request $request)
    {
        $data = $request->validate([
            'product_model_id' => ['required', 'exists:product_models,id'],
            'color_id'         => ['nullable', 'exists:colors,id'],
            'qty_pcs'          => ['required', 'numeric', 'min:0'],
            'cover_days'       => ['nullable', 'integer', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ], [], ['qty_pcs' => 'كمية الأمان']);

        SafetyStock::updateOrCreate(
            ['product_model_id' => $data['product_model_id'], 'color_id' => $data['color_id'] ?? null],
            $data + ['updated_by' => auth()->id()]
        );

        return back()->with('success', 'تم الحفظ.');
    }
}
