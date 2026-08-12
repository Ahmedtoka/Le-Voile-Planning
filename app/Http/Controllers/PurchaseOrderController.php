<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\FabricType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $q = PurchaseOrder::with(['supplier', 'creator'])->latest('id');

        if ($s = $request->get('status'))     $q->where('status', $s);
        if ($sup = $request->get('supplier_id')) $q->where('supplier_id', $sup);
        if ($term = trim((string) $request->get('q'))) $q->where('po_no', 'like', "%{$term}%");

        return view('po.index', [
            'title'     => 'طلبات الشراء',
            'rows'      => $q->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'statuses'  => PurchaseOrder::STATUSES,
        ]);
    }

    public function create()
    {
        return view('po.form', $this->formData([
            'row'  => new PurchaseOrder(['po_date' => now()->toDateString(), 'status' => 'draft',
                                          'tax_pct' => 0, 'discount_pct' => 0]),
            'mode' => 'create',
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create($data['header'] + [
                'po_no'      => DocNumber::next('purchase_order', 'purchase_orders', 'po_no'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($po, $data['lines']);
            $po->recalcTotals();
            return $po;
        });

        ActivityLogger::log('created', $po, 'إنشاء طلب شراء ' . $po->po_no);
        return redirect()->route('purchase-orders.edit', $po)->with('success', 'تم إنشاء طلب الشراء ' . $po->po_no);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['lines.color', 'lines.fabricType', 'approval.steps.user', 'approval.steps.role']);

        return view('po.form', $this->formData([
            'row'  => $purchase_order,
            'mode' => 'edit',
        ]));
    }

    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->isEditable(), 403, 'المستند مقفول عن التعديل.');

        $data = $this->validated($request);

        DB::transaction(function () use ($purchase_order, $data) {
            $purchase_order->update($data['header']);
            $this->syncLines($purchase_order, $data['lines'], true);
            $purchase_order->refresh()->recalcTotals();
        });

        ActivityLogger::log('updated', $purchase_order, 'تعديل طلب شراء ' . $purchase_order->po_no);
        return back()->with('success', 'تم الحفظ.');
    }

    /** إرسال للاعتماد */
    public function submit(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->isEditable(), 403);

        if ($purchase_order->lines()->count() === 0) {
            return back()->withErrors(['msg' => 'مينفعش ترسل طلب شراء من غير أصناف.']);
        }

        ApprovalEngine::submit($purchase_order);
        ActivityLogger::log('submitted', $purchase_order, 'إرسال طلب شراء للاعتماد ' . $purchase_order->po_no);

        return back()->with('success', 'تم الإرسال للاعتماد.');
    }

    public function print(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['lines.color', 'lines.fabricType', 'supplier', 'employee']);
        return view('print.purchase_order', ['po' => $purchase_order]);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->isDraft(), 403, 'مينفعش تحذف مستند خرج من المسودة.');
        $no = $purchase_order->po_no;
        $purchase_order->delete();
        ActivityLogger::log('deleted', null, 'حذف طلب شراء ' . $no);
        return redirect()->route('purchase-orders.index')->with('success', 'تم الحذف.');
    }

    // ── داخلي ──

    private function formData(array $extra): array
    {
        return array_merge([
            'title'       => 'طلب شراء',
            'suppliers'   => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses'  => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'      => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes' => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'employees'   => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'defaultTolerance' => config('lvplanning.default_po_tolerance_pct', 5),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'po_date'        => ['required', 'date'],
            'supplier_id'    => ['nullable', 'exists:suppliers,id'],
            'warehouse_id'   => ['nullable', 'exists:warehouses,id'],
            'employee_id'    => ['nullable', 'exists:users,id'],
            'delivery_date'  => ['nullable', 'date'],
            'delivery_place' => ['nullable', 'string', 'max:191'],
            'payment_method' => ['nullable', 'string', 'max:191'],
            'discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'          => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.color_id'       => ['nullable', 'exists:colors,id'],
            'lines.*.fabric_type_id' => ['nullable', 'exists:fabric_types,id'],
            'lines.*.item_name'      => ['nullable', 'string', 'max:191'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit'           => ['required', 'string', 'max:20'],
            'lines.*.unit_price'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.tolerance_pct'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'po_date'     => 'التاريخ',
            'lines'       => 'الأصناف',
            'lines.*.qty' => 'الكمية',
        ]);

        $lines = collect($v['lines'])->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values()->all();
        unset($v['lines']);

        $v['discount_pct'] = $v['discount_pct'] ?? 0;
        $v['tax_pct']      = $v['tax_pct'] ?? 0;

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(PurchaseOrder $po, array $lines, bool $replace = false): void
    {
        if ($replace) $po->lines()->delete();

        foreach ($lines as $i => $l) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_no'           => $i + 1,
                'color_id'          => $l['color_id'] ?? null,
                'fabric_type_id'    => $l['fabric_type_id'] ?? null,
                'item_name'         => $l['item_name'] ?? null,
                'qty'               => $l['qty'],
                'unit'              => $l['unit'],
                'unit_price'        => $l['unit_price'] ?? 0,
                'line_total'        => (float) $l['qty'] * (float) ($l['unit_price'] ?? 0),
                'tolerance_pct'     => $l['tolerance_pct'] ?? config('lvplanning.default_po_tolerance_pct', 5),
                'notes'             => $l['notes'] ?? null,
            ]);
        }
    }
}
