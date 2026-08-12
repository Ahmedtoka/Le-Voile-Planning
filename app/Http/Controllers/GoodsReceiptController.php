<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricRoll;
use App\Models\FabricType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إذن استلام خام.
 *
 * ده المستند اللي بيولّد "الحوض/الرسالة" — وحدة الشغل الأساسية.
 * لو المستخدم ما دخلش رقم رسالة، السيستم بيولّده بنمط الشركة:
 * SL30-090826-196-00
 */
class GoodsReceiptController extends Controller
{
    public function index(Request $request)
    {
        $q = GoodsReceipt::with(['supplier', 'warehouse', 'consignment'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);
        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($qq) => $qq->where('doc_no', 'like', "%{$term}%")
                                     ->orWhere('paper_serial', 'like', "%{$term}%"));
        }

        return view('receipts.index', [
            'title' => 'أذون استلام الخام',
            'rows'  => $q->paginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('receipts.form', $this->formData([
            'row'  => new GoodsReceipt(['doc_date' => now()->toDateString(), 'status' => 'draft']),
            'mode' => 'create',
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $gr = DB::transaction(function () use ($data, $request) {
            $gr = GoodsReceipt::create($data['header'] + [
                'doc_no'     => DocNumber::next('goods_receipt', 'goods_receipts'),
                'status'     => 'draft',
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($gr, $data['lines']);
            $gr->refresh()->recalcTotals();
            $this->buildConsignment($gr, $request);

            return $gr;
        });

        ActivityLogger::log('created', $gr, 'إذن استلام خام ' . $gr->doc_no);
        return redirect()->route('goods-receipts.edit', $gr)->with('success', 'تم إنشاء الإذن ' . $gr->doc_no);
    }

    public function edit(GoodsReceipt $goods_receipt)
    {
        $goods_receipt->load(['lines.color', 'lines.fabricType', 'consignment', 'approval.steps']);

        return view('receipts.form', $this->formData([
            'row'  => $goods_receipt,
            'mode' => 'edit',
        ]));
    }

    public function update(Request $request, GoodsReceipt $goods_receipt)
    {
        abort_unless($goods_receipt->isEditable(), 403, 'المستند مقفول عن التعديل.');
        $data = $this->validated($request);

        DB::transaction(function () use ($goods_receipt, $data, $request) {
            $goods_receipt->update($data['header']);
            $this->syncLines($goods_receipt, $data['lines'], true);
            $goods_receipt->refresh()->recalcTotals();
            $this->buildConsignment($goods_receipt, $request);
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(GoodsReceipt $goods_receipt)
    {
        abort_unless($goods_receipt->isEditable(), 403);

        if (!$goods_receipt->lines()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل إذن فاضي.']);
        }

        // تحذير تجاوز نسبة الزيادة المسموح بها في أمر الشراء
        if ($po = $goods_receipt->purchaseOrder) {
            $po->load('lines');
            foreach ($goods_receipt->lines as $line) {
                $poLine = $po->lines->first(fn ($l) =>
                    $l->fabric_type_id == $line->fabric_type_id && $l->color_id == $line->color_id);
                if (!$poLine) continue;

                $qtyTon = strtolower($line->unit) === 'طن' ? (float) $line->qty : (float) $line->qty / 1000;
                if ((float) $poLine->received_qty + $qtyTon > $poLine->max_allowed_qty + 0.0001) {
                    return back()->withErrors(['msg' =>
                        'الكمية المستلمة بتتعدى نسبة الزيادة المسموح بها ('
                        . $poLine->tolerance_pct . '%) في أمر الشراء. راجع الكمية أو عدّل أمر الشراء.']);
                }
            }
        }

        ApprovalEngine::submit($goods_receipt);
        return back()->with('success', 'تم الإرسال للاعتماد.');
    }

    public function print(GoodsReceipt $goods_receipt)
    {
        $goods_receipt->load(['lines.color', 'lines.fabricType', 'supplier', 'warehouse', 'purchaseOrder']);
        return view('print.goods_receipt', ['gr' => $goods_receipt]);
    }

    public function destroy(GoodsReceipt $goods_receipt)
    {
        abort_unless($goods_receipt->isDraft(), 403);
        $goods_receipt->delete();
        return redirect()->route('goods-receipts.index')->with('success', 'تم الحذف.');
    }

    // ── داخلي ──

    private function formData(array $extra): array
    {
        return array_merge([
            'title'       => 'إذن استلام خام',
            'suppliers'   => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses'  => Warehouse::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'colors'      => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes' => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'pos'         => PurchaseOrder::whereIn('status', ['approved','partially_received'])
                                ->latest('id')->pluck('po_no', 'id'),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'          => ['required', 'date'],
            'paper_serial'      => ['nullable', 'string', 'max:40'],
            'warehouse_id'      => ['required', 'exists:warehouses,id'],
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_rep'      => ['nullable', 'string', 'max:191'],
            'consignment_no'    => ['nullable', 'string', 'max:60'],
            'notes'             => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.item_code'      => ['nullable', 'string', 'max:40'],
            'lines.*.fabric_type_id' => ['required', 'exists:fabric_types,id'],
            'lines.*.color_id'       => ['required', 'exists:colors,id'],
            'lines.*.unit'           => ['required', 'string', 'max:20'],
            'lines.*.width_cm'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.rolls_count'    => ['required', 'integer', 'min:1'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes'          => ['nullable', 'string'],
        ], [], [
            'warehouse_id'        => 'المخزن',
            'supplier_id'         => 'المورد',
            'lines.*.rolls_count' => 'عدد الأتواب',
            'lines.*.qty'         => 'الكمية',
        ]);

        $consignmentNo = $v['consignment_no'] ?? null;
        unset($v['consignment_no']);

        $lines = $v['lines'];
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines, 'consignment_no' => $consignmentNo];
    }

    private function syncLines(GoodsReceipt $gr, array $lines, bool $replace = false): void
    {
        if ($replace) $gr->lines()->delete();

        foreach ($lines as $l) {
            GoodsReceiptLine::create([
                'goods_receipt_id' => $gr->id,
                'item_code'        => $l['item_code'] ?? null,
                'fabric_type_id'   => $l['fabric_type_id'],
                'color_id'         => $l['color_id'],
                'unit'             => $l['unit'],
                'width_cm'         => $l['width_cm'] ?? null,
                'rolls_count'      => $l['rolls_count'],
                'qty'              => $l['qty'],
                'consignment_no'   => $gr->consignment?->consignment_no,
                'notes'            => $l['notes'] ?? null,
            ]);
        }
    }

    /**
     * توليد/تحديث الحوض من الإذن + إنشاء سجل لكل توب.
     * الحوض بيتعمل لكل (خامة + لون) في الإذن — لأن الحوض بالتعريف
     * لون واحد وخامة واحدة.
     */
    private function buildConsignment(GoodsReceipt $gr, Request $request): void
    {
        $gr->load('lines');
        if ($gr->lines->isEmpty()) return;

        $first = $gr->lines->first();

        $no = trim((string) $request->input('consignment_no'));
        if ($no === '') {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $gr->supplier?->code ?? 'CN'), 0, 4)) ?: 'CN';
            $seq = Consignment::whereDate('arrival_date', $gr->doc_date)->count();
            $no = DocNumber::consignmentNo($prefix, $gr->doc_date, $gr->purchaseOrder?->po_no, $seq);
        }

        $totalKg   = (float) $gr->lines->sum('qty');
        $totalRoll = (int) $gr->lines->sum('rolls_count');

        $consignment = Consignment::updateOrCreate(
            ['consignment_no' => $no],
            [
                'arrival_date'      => $gr->doc_date,
                'purchase_order_id' => $gr->purchase_order_id,
                'supplier_id'       => $gr->supplier_id,
                'fabric_type_id'    => $first->fabric_type_id,
                'color_id'          => $first->color_id,
                'warehouse_id'      => $gr->warehouse_id,
                'total_kg'          => $totalKg,
                'rolls_count'       => $totalRoll,
                'remaining_kg'      => $totalKg,
                'status'            => 'received',
                'created_by'        => auth()->id(),
            ]
        );

        $gr->forceFill(['consignment_id' => $consignment->id])->save();
        $gr->lines()->update(['consignment_no' => $no]);

        // سجل لكل توب — لو مش موجود
        if ($consignment->rolls()->count() === 0 && $totalRoll > 0) {
            $avgLen = null; // الطول الفعلي بييجي من الفحص
            $avgKg  = round($totalKg / $totalRoll, 3);

            for ($i = 1; $i <= $totalRoll; $i++) {
                FabricRoll::create([
                    'consignment_id' => $consignment->id,
                    'roll_no'        => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'width_cm'       => $first->width_cm,
                    'net_kg'         => $avgKg,
                    'length_m'       => $avgLen,
                    'status'         => 'in_stock',
                ]);
            }
        }
    }
}
