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
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * طلب الشراء — مستند واحد بيمر على تلات أيادي، زي الورقة بالظبط.
 *
 *   ① التخطيط   يكتب اللون والصنف والكمية والوحدة ونسبة الزيادة وملاحظة لكل لون
 *   ② المشتريات تحدد المورد وبياناته والسعر وطريقة الدفع وتاريخ التوريد
 *   ③ الحسابات  تعلم بالمستحق المتوقع للمورد وتتابعه
 *   ④ الاعتماد   دورة الاعتماد ⇒ يتبعت للمورد
 *
 * كل مرحلة بتقفل اللي قبلها عن التعديل.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $q = PurchaseOrder::with(['supplier', 'requester', 'sourcer'])->latest('id');

        if ($s = $request->get('stage'))         $q->where('stage', $s);
        if ($sup = $request->get('supplier_id')) $q->where('supplier_id', $sup);
        if ($term = trim((string) $request->get('q'))) $q->where('po_no', 'like', "%{$term}%");
        if ($request->boolean('mine'))           $q->where('requested_by', auth()->id());

        return view('po.index', [
            'title'     => 'طلبات الشراء',
            'rows'      => $q->paginate(25)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'stages'    => PurchaseOrder::STAGES,
            'counts'    => PurchaseOrder::query()
                              ->selectRaw('stage, COUNT(*) c')->groupBy('stage')->pluck('c', 'stage'),
        ]);
    }

    public function create()
    {
        return view('po.form', $this->formData([
            'row'  => new PurchaseOrder([
                'po_date'      => now()->toDateString(),
                'stage'        => 'planning',
                'status'       => 'draft',
                'tax_pct'      => 14,
                'discount_pct' => 0,
            ]),
            'mode' => 'create',
        ]));
    }

    /** ① التخطيط بينشئ الطلب — أصناف وكميات بس */
    public function store(Request $request)
    {
        $data = $this->validatePlanning($request);

        $po = DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create($data['header'] + [
                'po_no'        => DocNumber::next('purchase_order', 'purchase_orders', 'po_no'),
                'stage'        => 'planning',
                'status'       => 'draft',
                'created_by'   => auth()->id(),
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);
            $this->syncLines($po, $data['lines']);
            $po->recalcTotals();
            return $po;
        });

        ActivityLogger::log('created', $po, 'طلب شراء جديد ' . $po->po_no);
        return redirect()->route('purchase-orders.edit', $po)->with('success', 'تم إنشاء الطلب ' . $po->po_no);
    }

    public function edit(PurchaseOrder $purchase_order)
    {
        $purchase_order->load([
            'lines.color', 'lines.fabricType', 'supplier',
            'requester', 'sourcer', 'financer',
            'approval.steps.user', 'approval.steps.role',
        ]);

        return view('po.form', $this->formData([
            'row'  => $purchase_order,
            'mode' => 'edit',
        ]));
    }

    /** تعديل بند التخطيط — متاح في مرحلة التخطيط بس */
    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->planningEditable(), 403, 'الطلب خرج من مرحلة التخطيط — مينفعش تعدّل الأصناف.');

        $data = $this->validatePlanning($request);

        DB::transaction(function () use ($purchase_order, $data) {
            $purchase_order->update($data['header']);
            $this->syncLines($purchase_order, $data['lines'], true);
            $purchase_order->refresh()->recalcTotals();
        });

        ActivityLogger::log('updated', $purchase_order, 'تعديل طلب شراء ' . $purchase_order->po_no);
        return back()->with('success', 'تم الحفظ.');
    }

    /** ① ← ② تنزيل الطلب للمشتريات */
    public function toPurchasing(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->stage === 'planning', 403);

        if (!$purchase_order->readyForPurchasing()) {
            return back()->withErrors(['msg' => 'مينفعش تنزّل طلب من غير أصناف.']);
        }

        $purchase_order->forceFill(['stage' => 'purchasing'])->save();

        Notifier::broadcastToRole('purchasing', 'po_sourcing',
            'طلب شراء محتاج تسعير ومورد',
            $purchase_order->po_no . ' — ' . $purchase_order->lines()->count() . ' صنف · '
                . rtrim(rtrim(number_format((float) $purchase_order->total_qty, 3), '0'), '.') . ' إجمالي',
            route('purchase-orders.edit', $purchase_order), 'warning');

        ActivityLogger::log('sent', $purchase_order, 'تنزيل طلب شراء للمشتريات ' . $purchase_order->po_no);
        return back()->with('success', 'الطلب نزل للمشتريات.');
    }

    /** ② المشتريات: المورد والأسعار وتاريخ التوريد */
    public function saveSourcing(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->purchasingEditable(), 403, 'الطلب مش في مرحلة المشتريات.');

        $data = $request->validate([
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'warehouse_id'   => ['nullable', 'exists:warehouses,id'],
            'delivery_date'  => ['required', 'date'],
            'delivery_place' => ['nullable', 'string', 'max:191'],
            'payment_method' => ['nullable', 'string', 'max:191'],
            'discount_pct'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'prices'                 => ['required', 'array'],
            'prices.*.id'            => ['required', 'exists:purchase_order_lines,id'],
            'prices.*.unit_price'    => ['required', 'numeric', 'min:0'],
        ], [], [
            'supplier_id'   => 'المورد',
            'delivery_date' => 'تاريخ التوريد',
            'prices'        => 'الأسعار',
        ]);

        DB::transaction(function () use ($purchase_order, $data) {
            foreach ($data['prices'] as $p) {
                $line = $purchase_order->lines()->find($p['id']);
                if (!$line) continue;
                $line->update([
                    'unit_price' => $p['unit_price'],
                    'line_total' => (float) $line->qty * (float) $p['unit_price'],
                ]);
            }

            $purchase_order->update([
                'supplier_id'    => $data['supplier_id'],
                'warehouse_id'   => $data['warehouse_id'] ?? null,
                'delivery_date'  => $data['delivery_date'],
                'delivery_place' => $data['delivery_place'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'discount_pct'   => $data['discount_pct'] ?? 0,
                'tax_pct'        => $data['tax_pct'] ?? 0,
                'sourced_by'     => auth()->id(),
                'sourced_at'     => now(),
            ]);

            $purchase_order->refresh()->recalcTotals();
        });

        ActivityLogger::log('sourced', $purchase_order, 'تسعير طلب شراء ' . $purchase_order->po_no);
        return back()->with('success', 'تم حفظ بيانات المورد والأسعار.');
    }

    /** ② ← ③ تنزيل الطلب للحسابات */
    public function toFinance(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->stage === 'purchasing', 403);

        if (!$purchase_order->readyForFinance()) {
            return back()->withErrors(['msg' => 'لازم تحدد المورد وتاريخ التوريد الأول.']);
        }

        $purchase_order->forceFill(['stage' => 'finance'])->save();

        Notifier::broadcastToRole('finance', 'po_payable',
            'طلب شراء للعلم — مستحق متوقع لمورد',
            $purchase_order->po_no . ' — ' . $purchase_order->supplier?->name . ' · '
                . number_format((float) $purchase_order->total, 2) . ' ' . config('lvplanning.currency'),
            route('finance.payables'), 'info');

        ActivityLogger::log('sent', $purchase_order, 'تنزيل طلب شراء للحسابات ' . $purchase_order->po_no);
        return back()->with('success', 'الطلب نزل للحسابات.');
    }

    /** ③ الحسابات: علم ومتابعة — مش بتوقف الطلب */
    public function financeAck(Request $request, PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->stage === 'finance', 403);

        $data = $request->validate(['finance_note' => ['nullable', 'string']], [], ['finance_note' => 'ملاحظة الحسابات']);

        $purchase_order->forceFill([
            'stage'        => 'approval',
            'finance_by'   => auth()->id(),
            'finance_at'   => now(),
            'finance_note' => $data['finance_note'] ?? null,
        ])->save();

        ApprovalEngine::submit($purchase_order->refresh(), auth()->user());

        ActivityLogger::log('acknowledged', $purchase_order, 'علم الحسابات بطلب الشراء ' . $purchase_order->po_no);
        return back()->with('success', 'تم التسجيل — الطلب راح لدورة الاعتماد.');
    }

    public function print(PurchaseOrder $purchase_order)
    {
        $purchase_order->load(['lines.color', 'lines.fabricType', 'supplier', 'requester', 'sourcer']);
        return view('print.purchase_order', ['po' => $purchase_order]);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        abort_unless($purchase_order->stage === 'planning', 403, 'مينفعش تحذف طلب خرج من التخطيط.');
        $no = $purchase_order->po_no;
        $purchase_order->delete();
        ActivityLogger::log('deleted', null, 'حذف طلب شراء ' . $no);
        return redirect()->route('purchase-orders.index')->with('success', 'تم الحذف.');
    }

    // ── داخلي ────────────────────────────────────────────────────

    private function formData(array $extra): array
    {
        return array_merge([
            'title'       => 'طلب شراء',
            'suppliers'   => Supplier::where('is_active', true)->orderBy('name')->get(),
            'warehouses'  => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'      => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes' => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'employees'   => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'defaultTolerance' => config('lvplanning.default_po_tolerance_pct', 5),
        ], $extra);
    }

    /** التحقق من بند التخطيط — من غير مورد ولا أسعار */
    private function validatePlanning(Request $request): array
    {
        $v = $request->validate([
            'po_date'       => ['required', 'date'],
            'employee_id'   => ['nullable', 'exists:users,id'],
            'planning_note' => ['nullable', 'string'],
            'notes'         => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.color_id'       => ['required', 'exists:colors,id'],
            'lines.*.fabric_type_id' => ['required', 'exists:fabric_types,id'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit'           => ['required', 'string', 'max:20'],
            'lines.*.tolerance_pct'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'po_date'                => 'التاريخ',
            'lines'                  => 'الأصناف',
            'lines.*.color_id'       => 'كود اللون',
            'lines.*.fabric_type_id' => 'اسم الصنف',
            'lines.*.qty'            => 'الكمية',
        ]);

        $lines = collect($v['lines'])->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values()->all();
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(PurchaseOrder $po, array $lines, bool $replace = false): void
    {
        if ($replace) $po->lines()->delete();

        foreach ($lines as $i => $l) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $po->id,
                'line_no'           => $i + 1,
                'color_id'          => $l['color_id'],
                'fabric_type_id'    => $l['fabric_type_id'],
                'qty'               => $l['qty'],
                'unit'              => $l['unit'],
                'unit_price'        => 0,
                'line_total'        => 0,
                'tolerance_pct'     => $l['tolerance_pct'] ?? config('lvplanning.default_po_tolerance_pct', 5),
                'notes'             => $l['notes'] ?? null,
            ]);
        }
    }
}
