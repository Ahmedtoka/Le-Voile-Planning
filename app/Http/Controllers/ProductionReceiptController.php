<?php

namespace App\Http\Controllers;

use App\Models\ProductionReceipt;
use App\Models\ProductionReceiptLine;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * استلام المنتج التام من المصنع.
 *
 * جزئي ومتكرر: "سلّمتني من ده 50، ومن ده 20، ومن ده 5" — كل مرة
 * بتتخصم من المقصوص لحد ما الرصيد يصفر وأمر الشغل يتقفل تلقائيًا.
 */
class ProductionReceiptController extends Controller
{
    public function index(Request $request)
    {
        $q = ProductionReceipt::with(['workOrder', 'factory', 'warehouse'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);

        return view('production.index', ['title' => 'استلامات الإنتاج', 'rows' => $q->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $wo = WorkOrder::with(['lines.productModel', 'lines.size', 'consignment'])
                ->findOrFail($request->get('work_order_id'));

        return view('production.form', [
            'title'      => 'استلام إنتاج من أمر الشغل ' . $wo->wo_no,
            'row'        => new ProductionReceipt([
                'doc_date'      => now()->toDateString(),
                'work_order_id' => $wo->id,
                'factory_id'    => $wo->factory_id,
                'status'        => 'draft',
            ]),
            'wo'         => $wo,
            'warehouses' => Warehouse::whereIn('type', ['finished','other'])->orWhere('is_active', true)
                                ->orderBy('name')->get()->pluck('label', 'id'),
            'mode'       => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $pr = DB::transaction(function () use ($data) {
            $pr = ProductionReceipt::create($data['header'] + [
                'doc_no'     => DocNumber::next('production_receipt', 'production_receipts'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($pr, $data['lines']);
            $pr->refresh()->recalcTotals();
            return $pr;
        });

        return redirect()->route('production-receipts.edit', $pr)->with('success', 'تم إنشاء الاستلام ' . $pr->doc_no);
    }

    public function edit(ProductionReceipt $production_receipt)
    {
        $production_receipt->load(['lines', 'workOrder.lines.productModel', 'workOrder.lines.size', 'approval.steps']);

        return view('production.form', [
            'title'      => 'استلام إنتاج ' . $production_receipt->doc_no,
            'row'        => $production_receipt,
            'wo'         => $production_receipt->workOrder,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'mode'       => 'edit',
        ]);
    }

    public function update(Request $request, ProductionReceipt $production_receipt)
    {
        abort_unless($production_receipt->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($production_receipt, $data) {
            $production_receipt->update($data['header']);
            $this->syncLines($production_receipt, $data['lines'], true);
            $production_receipt->refresh()->recalcTotals();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(ProductionReceipt $production_receipt)
    {
        abort_unless($production_receipt->isEditable(), 403);

        $production_receipt->load('lines', 'workOrder.lines');
        $wo = $production_receipt->workOrder;

        // منع استلام أكتر من المقصوص
        if ($wo) {
            foreach ($production_receipt->lines as $line) {
                $woLine = $wo->lines->first(fn ($l) =>
                    $l->product_model_id == $line->product_model_id && $l->size_id == $line->size_id);

                if ($woLine && (int) $line->qty > (int) $woLine->remaining_qty) {
                    return back()->withErrors(['msg' =>
                        'مينفعش تستلم ' . $line->qty . ' من ' . ($woLine->productModel->name ?? '')
                        . ' — المتبقي على المصنع ' . $woLine->remaining_qty . ' بس.']);
                }
            }
        }

        ApprovalEngine::submit($production_receipt);
        return back()->with('success', 'تم الإرسال للاعتماد.');
    }

    public function destroy(ProductionReceipt $production_receipt)
    {
        abort_unless($production_receipt->isDraft(), 403);
        $production_receipt->delete();
        return redirect()->route('production-receipts.index')->with('success', 'تم الحذف.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'      => ['required', 'date'],
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'factory_id'    => ['nullable', 'exists:factories,id'],
            'warehouse_id'  => ['required', 'exists:warehouses,id'],
            'notes'         => ['nullable', 'string'],

            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.product_model_id' => ['required', 'exists:product_models,id'],
            'lines.*.size_id'          => ['nullable', 'exists:sizes,id'],
            'lines.*.color_id'         => ['nullable', 'exists:colors,id'],
            'lines.*.qty'              => ['required', 'integer', 'min:0'],
            'lines.*.rejected_qty'     => ['nullable', 'integer', 'min:0'],
            'lines.*.notes'            => ['nullable', 'string'],
        ], [], ['warehouse_id' => 'المخزن', 'lines.*.qty' => 'الكمية']);

        $lines = collect($v['lines'])->map(function ($l) {
            $l['rejected_qty'] = $l['rejected_qty'] ?? 0;
            return $l;
        })->filter(fn ($l) => (int) $l['qty'] > 0)->values()->all();
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(ProductionReceipt $pr, array $lines, bool $replace = false): void
    {
        if ($replace) $pr->lines()->delete();

        foreach ($lines as $l) {
            ProductionReceiptLine::create(['production_receipt_id' => $pr->id] + $l);
        }
    }
}
