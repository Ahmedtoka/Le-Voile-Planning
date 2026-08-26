<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Factory;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\DocNumber;
use App\Services\FlowEngine;
use App\Services\FlowMessage;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إذن صرف خام — الحلقة بين أمر الشغل والمصنع.
 *
 * الورقة الواحدة بتصرف خامات لأكتر من أمر شغل (زي 1303774 اللي فيه
 * KB106 و KB107)، وكل سطر بيقول: الخامة دي من أي رسالة وبأي لون
 * وكام توب وكام كيلو/متر، ولأي أمر شغل.
 *
 * اعتماده هو اللي بيخصم فعليًا من رصيد الحوض.
 */
class MaterialIssueController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = MaterialIssue::with(['factory', 'warehouse']);
        $this->applyFilters($q, $request, ['doc_no', 'paper_serial', 'issued_to'], 'doc_date',
            ['status' => 'status', 'factory_id' => 'factory_id', 'warehouse_id' => 'warehouse_id']);

        $base = MaterialIssue::query();

        // أوامر لسه عايزة خام — نفس تعريف الكاونتر والكارت بالظبط
        $waiting = WorkOrder::needsMaterial()->count();

        /* طابور الصرف: أوامر شغالة وفيها خامات ناقصة صرف */
        $awaitingWos = \App\Models\WorkOrder::with(['factory', 'fabrics'])
            ->needsMaterial()
            ->latest('id')->limit(10)->get();

        return view('issues.index', [
            'awaitingWos' => $awaitingWos,
            'title'   => 'أذون صرف الخام',
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','total_qty','status'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات',
                 'options' => ['draft' => 'مسودة', 'approved' => 'تم', 'rejected' => 'ملغي']],
                ['name' => 'factory_id', 'label' => 'كل المصانع',
                 'options' => Factory::orderBy('name')->pluck('name', 'id'), 'width' => 150],
                ['name' => 'warehouse_id', 'label' => 'كل المخازن',
                 'options' => Warehouse::orderBy('name')->pluck('name', 'id'), 'width' => 150],
            ],
            'summary' => [
                ['label' => 'أوامر مستنية صرف', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'معتمدة ومتبعتة للمصنع ولسه ما خدتش خامة.'],
                ['label' => 'إجمالي الأذون', 'value' => $base->count(), 'note' => 'كل أذون الصرف المسجلة.'],
                ['label' => 'مستنية اعتماد', 'value' => (clone $base)->where('status', 'draft')->count(),
                 'tone' => 'warn', 'note' => 'الاعتماد هو اللي بيخصم من الحوض.'],
                ['label' => 'أتواب منصرفة',
                 'value' => number_format((int) (clone $base)->where('status', 'approved')->sum('total_rolls')),
                 'tone' => 'brand', 'note' => 'خرجت من المخزن للمصانع.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $row = new MaterialIssue([
            'doc_date'     => now()->toDateString(),
            'status'       => 'draft',
            'warehouse_id' => Warehouse::where('type', 'fabric')->value('id'),
        ]);

        $preset = [];
        if ($woId = $request->get('work_order_id')) {
            $wo = WorkOrder::with('fabrics.consignment', 'fabrics.fabricType', 'fabrics.color')->find($woId);
            if ($wo) {
                $row->factory_id = $wo->factory_id;
                $row->issued_to  = $wo->factory?->name;
                foreach ($wo->fabrics as $f) {
                    $preset[] = [
                        'work_order_id'        => $wo->id,
                        'work_order_fabric_id' => $f->id,
                        'consignment_id'       => $f->consignment_id,
                        'fabric_type_id'       => $f->fabric_type_id,
                        'color_id'             => $f->color_id,
                        'unit'                 => $f->unit,
                        'qty'                  => max(0, (float) $f->planned_qty - (float) $f->issued_qty),
                        'consignment_no'       => $f->consignment?->consignment_no,
                    ];
                }
            }
        }

        return view('issues.form', $this->formData(['row' => $row, 'mode' => 'create', 'preset' => $preset]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $mi = DB::transaction(function () use ($data) {
            $mi = MaterialIssue::create($data['header'] + [
                'doc_no'     => DocNumber::next('material_issue', 'material_issues'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($mi, $data['lines']);
            $mi->refresh()->recalcTotals();
            return $mi;
        });

        return redirect()->route('material-issues.edit', $mi)->with('success', 'تم إنشاء إذن الصرف ' . $mi->doc_no);
    }

    public function edit(MaterialIssue $material_issue)
    {
        $material_issue->load(['lines.consignment', 'lines.fabricType', 'lines.color', 'lines.workOrder', ]);

        return view('issues.form', $this->formData([
            'row'    => $material_issue,
            'mode'   => 'edit',
            'preset' => $material_issue->lines->toArray(),
        ]));
    }

    public function update(Request $request, MaterialIssue $material_issue)
    {
        abort_unless($material_issue->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($material_issue, $data) {
            $material_issue->update($data['header']);
            $material_issue->lines()->delete();
            $this->syncLines($material_issue, $data['lines']);
            $material_issue->refresh()->recalcTotals();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(MaterialIssue $material_issue)
    {
        abort_unless($material_issue->isEditable(), 403);
        $material_issue->load('lines.consignment');

        if ($material_issue->lines->isEmpty()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل إذن صرف فاضي.']);
        }

        /* مينفعش تصرف أكتر من المتاح.
           المتاح = المفرج عنه − اللي اتصرف قبل كده على إذون معتمدة تانية. */
        $over = [];
        foreach ($material_issue->lines->groupBy('consignment_id') as $cid => $lines) {
            $c = $lines->first()->consignment;
            if (!$c) continue;

            $alreadyIssued = (float) MaterialIssueLine::where('consignment_id', $cid)
                ->where('material_issue_id', '!=', $material_issue->id)
                ->whereHas('materialIssue', fn ($q) => $q->where('status', 'approved'))
                ->sum('qty');

            $available = (float) $c->released_kg - $alreadyIssued;
            $qty = (float) $lines->sum('qty');

            if ($qty > $available + 0.001) {
                $over[] = $c->consignment_no . ': مطلوب ' . number_format($qty, 2)
                        . ' والمتاح ' . number_format(max(0, $available), 2)
                        . ' (المفرج عنه ' . number_format((float) $c->released_kg, 2)
                        . ' − منصرف قبل كده ' . number_format($alreadyIssued, 2) . ')';
            }
        }

        if ($over) {
            return back()->withErrors(['msg' => 'الكمية أكبر من المفرج عنه — ' . implode(' | ', $over)]);
        }

        FlowEngine::complete($material_issue, 'إذن الصرف ' . $material_issue->doc_no . ' اتسجّل وخلص');
        return back()->with(FlowMessage::flash('issue.done', $material_issue));
    }

    public function print(MaterialIssue $material_issue)
    {
        $material_issue->load(['lines.consignment', 'lines.fabricType', 'lines.color', 'lines.workOrder',
                               'factory', 'warehouse']);
        return view('print.material_issue', ['mi' => $material_issue]);
    }

    public function destroy(MaterialIssue $material_issue)
    {
        abort_unless($material_issue->isDraft(), 403);
        $material_issue->delete();
        return redirect()->route('material-issues.index')->with('success', 'تم الحذف.');
    }

    // ── داخلي ──

    private function formData(array $extra): array
    {
        return array_merge([
            'title'      => 'إذن صرف خام',
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'factories'  => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'workOrders' => WorkOrder::with(['factory', 'fabrics.consignment', 'fabrics.fabricType', 'fabrics.color'])
                                ->whereIn('status', ['approved', 'sent_to_factory', 'cutting', 'in_production'])
                                ->latest('id')->limit(150)->get(),
            'consignments' => Consignment::with(['color', 'fabricType'])->readyForProduction()->latest('id')->get(),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'      => ['required', 'date'],
            'paper_serial'  => ['nullable', 'string', 'max:40'],
            'warehouse_id'  => ['required', 'exists:warehouses,id'],
            'factory_id'    => ['nullable', 'exists:factories,id'],
            'issued_to'     => ['required', 'string', 'max:191'],
            'receiver_name' => ['nullable', 'string', 'max:191'],
            'notes'         => ['nullable', 'string'],

            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.work_order_id'        => ['nullable', 'exists:work_orders,id'],
            'lines.*.work_order_fabric_id' => ['nullable', 'exists:work_order_fabrics,id'],
            'lines.*.consignment_id'       => ['required', 'exists:consignments,id'],
            'lines.*.item_code'            => ['nullable', 'string', 'max:40'],
            'lines.*.unit'                 => ['required', 'string', 'max:20'],
            'lines.*.width_cm'             => ['nullable', 'numeric', 'min:0'],
            'lines.*.rolls_count'          => ['nullable', 'integer', 'min:0'],
            'lines.*.qty'                  => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes'                => ['nullable', 'string'],
        ], [], [
            'warehouse_id'           => 'المخزن',
            'issued_to'              => 'منصرف إلى',
            'lines.*.consignment_id' => 'رقم الرسالة',
            'lines.*.qty'            => 'الكمية',
        ]);

        $lines = $v['lines'];
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(MaterialIssue $mi, array $lines): void
    {
        foreach ($lines as $l) {
            $c = Consignment::find($l['consignment_id']);

            MaterialIssueLine::create([
                'material_issue_id'    => $mi->id,
                'work_order_id'        => $l['work_order_id'] ?? null,
                'work_order_fabric_id' => $l['work_order_fabric_id'] ?? null,
                'consignment_id'       => $c?->id,
                'fabric_type_id'       => $c?->fabric_type_id,
                'color_id'             => $c?->color_id,
                'item_code'            => $l['item_code'] ?? null,
                'unit'                 => $l['unit'],
                'width_cm'             => $l['width_cm'] ?? null,
                'rolls_count'          => $l['rolls_count'] ?? 0,
                'qty'                  => $l['qty'],
                'consignment_no'       => $c?->consignment_no,
                'notes'                => $l['notes'] ?? null,
            ]);
        }
    }
}
