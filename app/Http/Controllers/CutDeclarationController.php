<?php

namespace App\Http\Controllers;

use App\Models\CutDeclaration;
use App\Models\CutDeclarationLine;
use App\Models\WorkOrder;
use App\Services\DocNumber;
use App\Services\FlowEngine;
use App\Services\FlowMessage;
use App\Services\PlanningEngine;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * بيان القص.
 *
 * أول رقم فعلي بيوصلنا من المصنع. أهم حقل: طول الفرشة الفعلي —
 * لو المصنع فرش على 3.05 بدل 3.00، بتخسر رِقّة كاملة من كل توب.
 */
class CutDeclarationController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = CutDeclaration::with(['workOrder', 'factory']);
        $this->applyFilters($q, $request,
            ['doc_no', 'workOrder.wo_no'],
            'doc_date',
            ['status' => 'status', 'factory_id' => 'factory_id', 'variance_flag' => 'variance_flag']
        );

        $base    = CutDeclaration::query();
        $waiting = WorkOrder::whereIn('status', ['sent_to_factory','cutting'])
            ->whereDoesntHave('cutDeclarations', fn ($x) => $x->where('status', 'approved'))->count();

        /* طابور القص: الأوامر اللي عند المصنع */
        $awaitingWos = \App\Models\WorkOrder::with('factory')
            ->whereIn('status', ['sent_to_factory', 'cutting'])
            ->latest('id')->limit(10)->get();

        return view('cutting.index', [
            'awaitingWos' => $awaitingWos,
            'title'   => 'بيانات القص',
            'rows'    => $this->applySort($q, $request, ['doc_no','doc_date','total_pieces','variance_pct','status'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','approved'=>'تم','rejected'=>'ملغي']],
                ['name' => 'factory_id', 'label' => 'كل المصانع', 'options' => \App\Models\Factory::orderBy('name')->pluck('name','id'), 'width' => 150],
                ['name' => 'variance_flag', 'label' => 'كل الانحرافات', 'options' => WorkOrder::VARIANCE_FLAGS, 'width' => 150],
            ],
            'summary' => [
                ['label' => 'أوامر مستنية بيان', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'اتبعتت للمصنع ولسه ما جاش منها بيان قص.'],
                ['label' => 'إجمالي البيانات', 'value' => $base->count(), 'note' => 'كل بيانات القص المسجلة.'],
                ['label' => 'انحراف خارج الحدود', 'value' => (clone $base)->where('variance_flag','danger')->count(),
                 'tone' => 'danger', 'note' => 'الفرق تعدى ' . config('lvplanning.variance.warn_pct') . '% — لازم سبب مكتوب.'],
                ['label' => 'إجمالي المقصوص', 'value' => number_format((int) (clone $base)->where('status','approved')->sum('total_pieces')),
                 'tone' => 'brand', 'note' => 'قطع اتقصت فعليًا في المصانع.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $wo = WorkOrder::with(['lines.productModel', 'lines.size', 'marker'])
                ->findOrFail($request->get('work_order_id'));

        return view('cutting.form', [
            'title' => 'بيان قص لأمر الشغل ' . $wo->wo_no,
            'row'   => new CutDeclaration([
                'doc_date'                => now()->toDateString(),
                'work_order_id'           => $wo->id,
                'factory_id'              => $wo->factory_id,
                'actual_spread_length_m'  => $wo->governingFabric()?->effective_spread,
                'status'                  => 'draft',
            ]),
            'wo'   => $wo,
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $cd = DB::transaction(function () use ($data) {
            $cd = CutDeclaration::create($data['header'] + [
                'doc_no'     => DocNumber::next('cut_declaration', 'cut_declarations'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($cd, $data['lines']);
            $this->recompute($cd);

            return $cd;
        });

        return redirect()->route('cut-declarations.edit', $cd)->with('success', 'تم إنشاء البيان ' . $cd->doc_no);
    }

    public function edit(CutDeclaration $cut_declaration)
    {
        $cut_declaration->load(['lines', 'workOrder.lines.productModel', 'workOrder.lines.size', ]);

        return view('cutting.form', [
            'title' => 'بيان قص ' . $cut_declaration->doc_no,
            'row'   => $cut_declaration,
            'wo'    => $cut_declaration->workOrder,
            'mode'  => 'edit',
        ]);
    }

    public function update(Request $request, CutDeclaration $cut_declaration)
    {
        abort_unless($cut_declaration->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($cut_declaration, $data) {
            $cut_declaration->update($data['header']);
            $this->syncLines($cut_declaration, $data['lines'], true);
            $this->recompute($cut_declaration);
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(CutDeclaration $cut_declaration)
    {
        abort_unless($cut_declaration->isEditable(), 403);

        $cut_declaration->refresh();

        // الانحراف خارج الحدود لازم يتكتب له سبب قبل الإرسال
        if ($cut_declaration->variance_flag === 'danger' && !trim((string) $cut_declaration->variance_reason)) {
            return back()->withErrors(['msg' =>
                'الانحراف ' . $cut_declaration->variance_pct . '% خارج الحدود المقبولة ('
                . config('lvplanning.variance.warn_pct') . '%). اكتب سبب الفرق قبل الإرسال.']);
        }

        FlowEngine::complete($cut_declaration, 'بيان القص ' . $cut_declaration->doc_no . ' اتسجّل وخلص');
        return back()->with(FlowMessage::flash('cut.done', $cut_declaration));
    }

    public function destroy(CutDeclaration $cut_declaration)
    {
        abort_unless($cut_declaration->isDraft(), 403);
        $cut_declaration->delete();
        return redirect()->route('cut-declarations.index')->with('success', 'تم الحذف.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'               => ['required', 'date'],
            'work_order_id'          => ['required', 'exists:work_orders,id'],
            'factory_id'             => ['nullable', 'exists:factories,id'],
            'actual_spread_length_m' => ['required', 'numeric', 'min:0.01'],
            'actual_plies'           => ['nullable', 'integer', 'min:0'],
            'used_kg'                => ['nullable', 'numeric', 'min:0'],
            'variance_reason'        => ['nullable', 'string'],
            'notes'                  => ['nullable', 'string'],

            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.product_model_id' => ['required', 'exists:product_models,id'],
            'lines.*.size_id'          => ['nullable', 'exists:sizes,id'],
            'lines.*.qty'              => ['required', 'integer', 'min:0'],
            'lines.*.notes'            => ['nullable', 'string'],
        ], [], [
            'actual_spread_length_m' => 'طول الفرشة الفعلي',
            'lines.*.qty'            => 'الكمية المقصوصة',
        ]);

        $lines = collect($v['lines'])->filter(fn ($l) => (int) $l['qty'] > 0)->values()->all();
        unset($v['lines']);

        $v['used_kg']      = $v['used_kg'] ?? 0;
        $v['actual_plies'] = $v['actual_plies'] ?? 0;

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(CutDeclaration $cd, array $lines, bool $replace = false): void
    {
        if ($replace) $cd->lines()->delete();

        foreach ($lines as $l) {
            CutDeclarationLine::create(['cut_declaration_id' => $cd->id] + $l);
        }
    }

    /** حساب الإجماليات والانحراف مقارنةً بالمتوقع */
    private function recompute(CutDeclaration $cd): void
    {
        $cd->load('lines', 'workOrder');
        $total = (int) $cd->lines->sum('qty');
        $wo    = $cd->workOrder;

        $v = $wo ? PlanningEngine::variance((float) $wo->target_qty, (float) $total) : ['pct' => null, 'flag' => null];

        $cd->forceFill([
            'total_pieces'          => $total,
            'actual_kg_per_piece'   => $total > 0 && $cd->used_kg > 0 ? round((float) $cd->used_kg / $total, 5) : null,
            'variance_pct'          => $v['pct'],
            'variance_flag'         => $v['flag'],
        ])->saveQuietly();
    }
}
