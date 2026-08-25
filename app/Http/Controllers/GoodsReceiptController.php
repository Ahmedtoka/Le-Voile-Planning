<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricInspection;
use App\Models\FabricType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
use App\Services\FlowMessage;
use App\Support\FiltersIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * إذن استلام خام — آخر خطوة في دورة وصول القماش.
 *
 * الترتيب: إذن إضافة (الحوض بيتولّد ويتحجز) ⇒ تقرير فحص ⇒ تقرير معمل
 * ⇒ **إذن استلام خام** = الإفراج. اعتماد الإذن ده هو اللي بيخلّي
 * القماش متاح فعليًا لأوامر الشغل.
 */
class GoodsReceiptController extends Controller
{
    use FiltersIndex;

    public function index(Request $request)
    {
        $q = GoodsReceipt::with(['supplier', 'warehouse', 'consignment']);
        $this->applyFilters($q, $request,
            ['doc_no', 'paper_serial', 'supplier.name'],
            'doc_date',
            ['status' => 'status', 'supplier_id' => 'supplier_id']
        );

        $base    = GoodsReceipt::query();
        $waiting = Consignment::whereIn('status', ['inspected','lab_done'])
            ->whereHas('inspections', fn ($x) => $x->where('status','approved'))
            ->whereHas('labReports',  fn ($x) => $x->where('status','approved'))
            ->whereDoesntHave('goodsReceipts', fn ($x) => $x->where('status','approved'))
            ->count();

        /* طابور الإفراج: فحص + معمل معتمدين */
        $awaiting = \App\Models\Consignment::with(['fabricType', 'color', 'supplier'])
            ->where('status', 'lab_done')
            ->whereDoesntHave('goodsReceipts', fn ($x) => $x->whereIn('status', ['pending', 'approved']))
            ->latest('id')->limit(10)->get();

        return view('receipts.index', [
            'awaiting' => $awaiting,
            'title'   => 'أذون استلام الخام (الإفراج)',
            'rows'    => $this->applySort($q, $request, ['doc_no','paper_serial','doc_date','total_qty','total_rolls','status'])->paginate(25)->withQueryString(),
            'filters' => [
                ['name' => 'status', 'label' => 'كل الحالات', 'options' => ['draft'=>'مسودة','pending'=>'تحت الاعتماد','approved'=>'معتمد','rejected'=>'مرفوض']],
                ['name' => 'supplier_id', 'label' => 'كل الموردين', 'options' => \App\Models\Supplier::orderBy('name')->pluck('name','id'), 'width' => 160],
            ],
            'summary' => [
                ['label' => 'أحواض مستنية إفراج', 'value' => $waiting, 'tone' => $waiting ? 'warn' : 'ok',
                 'note' => 'اتفحصت وخلّصت معمل — ناقصها إذن استلام.',
                 'link' => [route('consignments.index'), 'شوف الأحواض']],
                ['label' => 'إجمالي الأذون', 'value' => $base->count(), 'note' => 'كل أذون الاستلام المسجلة.'],
                ['label' => 'مستنية اعتماد', 'value' => (clone $base)->where('status','pending')->count(), 'tone' => 'warn',
                 'note' => 'الاعتماد هو اللي بيفرج عن القماش.'],
                ['label' => 'كجم مفرج عنها', 'value' => number_format((float) (clone $base)->where('status','approved')->sum('total_qty'), 0), 'tone' => 'ok',
                 'note' => 'قماش بقى متاح فعليًا لأوامر الشغل.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $row    = new GoodsReceipt(['doc_date' => now()->toDateString(), 'status' => 'draft']);
        $preset = [];

        // بنملا الإذن من الحوض المتفحص — الأرقام بتيجي من الفحص، مش من المورد
        if ($cid = $request->get('consignment_id')) {
            if ($c = Consignment::with(['fabricType', 'color', 'supplier',
                    'inspections' => fn ($q) => $q->where('status', 'approved')->latest('id')])->find($cid)) {
                $row->consignment_id      = $c->id;
                $row->supplier_id         = $c->supplier_id;
                $row->warehouse_id        = $c->warehouse_id;
                $row->purchase_order_id   = $c->purchase_order_id;
                // الحوض ممكن يكون من استلامة متعددة الألوان — الربط الأدق من سطر الإذن
                $row->stock_addition_id   = $c->stockAdditions()->latest('id')->value('id')
                    ?: \App\Models\StockAdditionLine::where('consignment_id', $c->id)->value('stock_addition_id');
                $row->fabric_inspection_id = $c->inspections->first()?->id;

                // سطر جاهز بأرقام الفحص — المستلم بيأكد وبيسجل الرفض والتعليق بس
                $insp = $c->inspections->first();
                $preset[] = [
                    'item_code'      => $c->fabricType?->code,
                    'fabric_type_id' => $c->fabric_type_id,
                    'color_id'       => $c->color_id,
                    'unit'           => 'كجم',
                    'width_cm'       => $c->min_width_cm,
                    'rolls_count'    => (int) ($insp?->counted_rolls ?: $c->rolls_count),
                    'qty'            => (float) ($insp?->counted_kg ?: $c->total_kg),
                    'consignment_no' => $c->consignment_no,
                ];
            }
        }

        return view('receipts.form', $this->formData([
            'row'     => $row,
            'mode'    => 'create',
            'preset'  => $preset,
            'arrived' => $c ?? null,
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
            $this->stampConsignment($gr->refresh());

            return $gr;
        });

        ActivityLogger::log('created', $gr, 'إذن استلام خام ' . $gr->doc_no);
        return redirect()->route('goods-receipts.edit', $gr)->with('success', 'تم إنشاء الإذن ' . $gr->doc_no);
    }

    public function edit(GoodsReceipt $goods_receipt)
    {
        $goods_receipt->load(['lines.color', 'lines.fabricType',
            'consignment.fabricType', 'consignment.color', 'consignment.supplier', 'approval.steps']);

        return view('receipts.form', $this->formData([
            'row'     => $goods_receipt,
            'mode'    => 'edit',
            'arrived' => $goods_receipt->consignment,
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
            $this->stampConsignment($goods_receipt->refresh());
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(GoodsReceipt $goods_receipt)
    {
        abort_unless($goods_receipt->isEditable(), 403);

        if (!$goods_receipt->lines()->count()) {
            return back()->withErrors(['msg' => 'مينفعش ترسل إذن فاضي.']);
        }

        // الإفراج مالوش معنى قبل الفحص — ده جوهر الدورة
        $c = $goods_receipt->consignment;
        if (!$c) {
            return back()->withErrors(['msg' => 'لازم تحدد الحوض.']);
        }
        if (!$c->inspections()->where('status', 'approved')->exists()) {
            return back()->withErrors(['msg' =>
                'الحوض ' . $c->consignment_no . ' لسه ما اتفحصش. مينفعش تفرج عن قماش من غير تقرير فحص معتمد.']);
        }
        if (!$c->labReports()->where('status', 'approved')->exists()) {
            return back()->withErrors(['msg' =>
                'الحوض ' . $c->consignment_no . ' لسه مالوش تقرير معمل معتمد — مفيش بنشر نحسب عليه.']);
        }

        /* حارس نسبة الزيادة اتنقل لإذن الإضافة — الاستلام بيتحسب هناك.
           هنا إفراج جودة بس. */

        ApprovalEngine::submit($goods_receipt);
        return back()->with(FlowMessage::flash('receipt.submitted', $goods_receipt));
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
            'pos'         => PurchaseOrder::whereIn('stage', ['approved','receiving'])
                                ->latest('id')->pluck('po_no', 'id'),
            // الأحواض المتفحصة بس — مينفعش تفرج عن حوض ما اتفحصش
            'consignments' => Consignment::with(['color','fabricType','inspections'])
                                ->whereIn('status', ['inspected','lab_done'])
                                ->latest('id')->get(),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'doc_date'          => ['required', 'date'],
            'paper_serial'      => ['nullable', 'string', 'max:40'],
            'warehouse_id'         => ['required', 'exists:warehouses,id'],
            'supplier_id'          => ['required', 'exists:suppliers,id'],
            'purchase_order_id'    => ['nullable', 'exists:purchase_orders,id'],
            'consignment_id'       => ['required', 'exists:consignments,id'],
            'stock_addition_id'    => ['nullable', 'exists:stock_additions,id'],
            'fabric_inspection_id' => ['nullable', 'exists:fabric_inspections,id'],
            'supplier_rep'         => ['nullable', 'string', 'max:191'],
            'notes'                => ['nullable', 'string'],

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
            'consignment_id'      => 'الحوض',
            'lines.*.rolls_count' => 'عدد الأتواب',
            'lines.*.qty'         => 'الكمية',
        ]);

        $lines = $v['lines'];
        unset($v['lines']);

        return ['header' => $v, 'lines' => $lines];
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

    /** ربط سطور الإذن برقم الرسالة بتاع الحوض */
    private function stampConsignment(GoodsReceipt $gr): void
    {
        if ($no = $gr->consignment?->consignment_no) {
            $gr->lines()->update(['consignment_no' => $no]);
        }
    }
}
