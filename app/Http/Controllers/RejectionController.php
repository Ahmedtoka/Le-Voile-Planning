<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptRejection;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * المرفوضات والمعلّق.
 *
 * الاستلام مش «قبول كله أو رفض كله». الورق الحقيقي بيسجّل على نفس الإذن:
 *   • «تم رفض عدد 2 توب كود 1132 بوزنه 8.36 كيلو — مصلحة الجودة»
 *   • «تم تعليق اللون الروز كود 2580 لحين الرد من إدارة التخطيط والمشتريات»
 *
 * المرفوض ما بيدخلش المخزون وما بيتحسبش على أمر الشراء، وبيفضل مطالبة
 * على المورد لحد ما يتقفل. والمعلّق بيفضل واقف لحد ما التخطيط والمشتريات
 * يردوا — وده رقم لازم يبان، لأنه فلوس واقفة.
 */
class RejectionController extends Controller
{
    public function index(Request $request)
    {
        $q = GoodsReceiptRejection::with(['consignment.supplier', 'color', 'goodsReceipt', 'resolver'])
            ->latest('id');

        if ($k = $request->get('kind'))        $q->where('kind', $k);
        if ($r = $request->get('resolution'))  $q->where('resolution', $r);
        if ($s = $request->get('supplier_id')) {
            $q->whereHas('consignment', fn ($x) => $x->where('supplier_id', $s));
        }
        if ($request->boolean('open'))         $q->open();

        $base = GoodsReceiptRejection::query();

        return view('rejections.index', [
            'title'     => 'المرفوضات والمعلّق',
            'rows'      => $q->paginate(30)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'summary'   => [
                ['label' => 'معلّق لحين الرد',
                 'value' => (clone $base)->onHold()->count(), 'tone' => 'warn',
                 'note'  => 'ألوان واقفة مستنية قرار التخطيط والمشتريات.'],
                ['label' => 'مرفوض مفتوح',
                 'value' => (clone $base)->where('kind', 'rejected')->open()->count(), 'tone' => 'danger',
                 'note'  => 'لسه ما اترجعش للمورد ولا اتقفل.'],
                ['label' => 'أتواب مرفوضة',
                 'value' => number_format((int) (clone $base)->where('kind', 'rejected')->sum('rolls_count')),
                 'note'  => 'إجمالي الأتواب اللي اترفضت.'],
                ['label' => 'كجم مرفوضة',
                 'value' => number_format((float) (clone $base)->where('kind', 'rejected')->sum('qty'), 1),
                 'tone'  => 'danger', 'note' => 'وزن مطالب بيه المورد.'],
                ['label' => 'اتقفل',
                 'value' => (clone $base)->where('resolution', '!=', 'open')->count(), 'tone' => 'ok',
                 'note'  => 'اترجع أو اتقبل أو اترفض نهائي.'],
            ],
        ]);
    }

    /** تسجيل رفض أو تعليق على إذن استلام */
    public function store(Request $request, GoodsReceipt $goods_receipt)
    {
        abort_unless($goods_receipt->isEditable(), 403, 'المستند مقفول عن التعديل.');

        $data = $request->validate([
            'kind'        => ['required', 'in:rejected,on_hold'],
            'color_id'    => ['nullable', 'exists:colors,id'],
            'color_code'  => ['nullable', 'string', 'max:40'],
            'lot_label'   => ['nullable', 'string', 'max:191'],
            'rolls_count' => ['required', 'integer', 'min:0'],
            'qty'         => ['required', 'numeric', 'min:0'],
            'unit'        => ['required', 'string', 'max:20'],
            'party'       => ['required', 'in:quality,planning,purchasing'],
            'reason'      => ['required', 'string'],
        ], [], [
            'rolls_count' => 'عدد الأتواب',
            'qty'         => 'الوزن',
            'reason'      => 'السبب',
        ]);

        $rej = GoodsReceiptRejection::create($data + [
            'goods_receipt_id'     => $goods_receipt->id,
            'consignment_id'       => $goods_receipt->consignment_id,
            'fabric_inspection_id' => $goods_receipt->fabric_inspection_id,
            'created_by'           => auth()->id(),
        ]);

        if ($rej->kind === 'on_hold') {
            foreach (['planner', 'purchasing'] as $role) {
                Notifier::broadcastToRole($role, 'color_on_hold',
                    'لون معلّق محتاج ردكم',
                    $rej->label . ' — ' . $rej->reason,
                    route('rejections.index'), 'warning');
            }
        } else {
            Notifier::broadcastToRole('purchasing', 'fabric_rejected',
                'قماش مرفوض — مطالبة على المورد',
                $rej->label . ' — ' . ($goods_receipt->supplier?->name ?? ''),
                route('rejections.index'), 'danger');
        }

        ActivityLogger::log('rejected_partial', $goods_receipt, 'تسجيل ' . $rej->kind_name . ': ' . $rej->label);

        return back()->with('success', 'تم تسجيل ' . $rej->kind_name . '.');
    }

    /** قفل التعليق أو المرفوض */
    public function resolve(Request $request, GoodsReceiptRejection $rejection)
    {
        $data = $request->validate([
            'resolution'      => ['required', 'in:accepted,rejected,returned'],
            'resolution_note' => ['required', 'string'],
        ], [], [
            'resolution'      => 'القرار',
            'resolution_note' => 'الملاحظة',
        ]);

        DB::transaction(function () use ($rejection, $data) {
            $rejection->update($data + ['resolved_by' => auth()->id(), 'resolved_at' => now()]);

            // اللون المعلّق اللي اتقبل بيرجع للكمية المفرج عنها
            if ($rejection->kind === 'on_hold' && $data['resolution'] === 'accepted' && $rejection->consignment) {
                $c = $rejection->consignment;
                $c->forceFill(['released_kg' => (float) $c->released_kg + (float) $rejection->qty])->save();
                $c->recalcRemaining();
            }
        });

        Notifier::send($rejection->created_by, 'hold_resolved',
            'اتقفل بند معلّق',
            $rejection->label . ' — ' . $rejection->resolution_name,
            route('rejections.index'), 'info');

        ActivityLogger::log('resolved', $rejection, 'قفل ' . $rejection->kind_name . ': ' . $rejection->label);

        return back()->with('success', 'تم قفل البند.');
    }

    public function destroy(GoodsReceiptRejection $rejection)
    {
        abort_unless($rejection->resolution === 'open', 403, 'مينفعش تحذف بند اتقفل.');
        $rejection->delete();
        return back()->with('success', 'تم الحذف.');
    }
}
