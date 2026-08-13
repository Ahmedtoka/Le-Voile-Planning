<?php

namespace App\Http\Controllers;

use App\Models\Accessory;
use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\StockAdditionLine;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إذن الإضافة — أول مستند في دورة القماش.
 *
 * لما القماش يوصل، بيتسجّل هنا وبيتولّد منه الحوض (الرسالة) بحالة
 * «تحت الفحص». الكمية بتدخل المخزن **محجوزة** — ممنوع تشغيلها.
 * الإفراج بيحصل بإذن الاستلام الخام بعد ما الفحص والمعمل يخلّصوا.
 */
class StockAdditionController extends Controller
{
    public function index(Request $request)
    {
        $q = StockAddition::with(['supplier', 'warehouse', 'consignment'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);
        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($qq) => $qq->where('doc_no', 'like', "%{$term}%")
                                     ->orWhere('paper_serial', 'like', "%{$term}%"));
        }

        return view('additions.index', [
            'title' => 'أذون الإضافة (تحت الفحص)',
            'rows'  => $q->paginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('additions.form', $this->formData([
            'row'  => new StockAddition(['doc_date' => now()->toDateString(), 'status' => 'draft']),
            'mode' => 'create',
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $sa = DB::transaction(function () use ($data) {
            $sa = StockAddition::create($data['header'] + [
                'doc_no'     => DocNumber::next('stock_addition', 'stock_additions'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($sa, $data['lines']);
            $sa->refresh()->recalcTotals();
            return $sa;
        });

        return redirect()->route('stock-additions.edit', $sa)->with('success', 'تم إنشاء الإذن ' . $sa->doc_no);
    }

    public function edit(StockAddition $stock_addition)
    {
        $stock_addition->load(['lines.color', 'lines.fabricType', 'lines.accessory', 'approval.steps', 'consignment']);
        return view('additions.form', $this->formData(['row' => $stock_addition, 'mode' => 'edit']));
    }

    public function update(Request $request, StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($stock_addition, $data) {
            $stock_addition->update($data['header']);
            $this->syncLines($stock_addition, $data['lines'], true);
            $stock_addition->refresh()->recalcTotals();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isEditable(), 403);
        if (!$stock_addition->lines()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل إذن فاضي.']);
        }

        // لازم عدد أتواب على كل سطر قماش — الفحص هيجرد عليه
        $missing = $stock_addition->lines()
            ->whereNotNull('fabric_type_id')->where('rolls_count', '<=', 0)->count();

        if ($missing) {
            return back()->withErrors(['msg' => 'لازم تكتب عدد الأتواب لكل صنف قماش — الفحص هيجرد عليه.']);
        }
        ApprovalEngine::submit($stock_addition);
        return back()->with('success', 'تم الإرسال للاعتماد.');
    }

    public function print(StockAddition $stock_addition)
    {
        $stock_addition->load(['lines.color', 'lines.fabricType', 'lines.accessory', 'supplier', 'warehouse']);
        return view('print.stock_addition', ['sa' => $stock_addition]);
    }

    public function destroy(StockAddition $stock_addition)
    {
        abort_unless($stock_addition->isDraft(), 403);
        $stock_addition->delete();
        return redirect()->route('stock-additions.index')->with('success', 'تم الحذف.');
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'        => 'إذن إضافة',
            'suppliers'    => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses'   => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'       => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes'  => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'accessories'  => Accessory::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'pos'          => PurchaseOrder::whereIn('stage', ['approved', 'receiving'])
                                  ->latest('id')->pluck('po_no', 'id'),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'         => ['required', 'date'],
            'paper_serial'     => ['nullable', 'string', 'max:40'],
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'warehouse_id'      => ['required', 'exists:warehouses,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'consignment_no'    => ['nullable', 'string', 'max:60'],
            'notes'             => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.item_code'      => ['nullable', 'string', 'max:40'],
            'lines.*.item_name'      => ['nullable', 'string', 'max:191'],
            'lines.*.fabric_type_id' => ['nullable', 'exists:fabric_types,id'],
            'lines.*.color_id'       => ['nullable', 'exists:colors,id'],
            'lines.*.accessory_id'   => ['nullable', 'exists:accessories,id'],
            'lines.*.rolls_count'    => ['nullable', 'integer', 'min:0'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit'           => ['required', 'string', 'max:20'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'warehouse_id'        => 'المخزن',
            'supplier_id'         => 'المورد',
            'lines.*.qty'         => 'الكمية',
            'lines.*.rolls_count' => 'عدد الأتواب',
        ]);

        $lines = $v['lines'];
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(StockAddition $sa, array $lines, bool $replace = false): void
    {
        if ($replace) $sa->lines()->delete();

        foreach ($lines as $l) {
            $l['rolls_count'] = $l['rolls_count'] ?? 0;
            StockAdditionLine::create(['stock_addition_id' => $sa->id] + $l);
        }
    }
}
