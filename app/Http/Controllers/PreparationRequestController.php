<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Factory;
use App\Models\PreparationRequest;
use App\Models\ProductModel;
use App\Services\ActivityLogger;
use App\Services\DocNumber;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * طلب تحضير (تقسيم رسالة) — التخطيط يطلب كمية تقريبية، المخزن يوزن ويرد بالفعلي.
 * بيحل محل الورقة/الواتساب اللي كانت بتتبعت للمخزن.
 */
class PreparationRequestController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $q = PreparationRequest::with(['consignment.fabricType', 'consignment.color', 'factory', 'requester', 'weigher', 'workOrder', 'models', 'markerRequest'])
            ->latest('id');

        if ($s = $request->get('status'))            $q->where('status', $s);
        if ($c = $request->get('consignment_id'))    $q->where('consignment_id', $c);
        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($x) => $x->where('doc_no', 'like', "%{$term}%")
                ->orWhereHas('consignment', fn ($cc) => $cc->where('consignment_no', 'like', "%{$term}%")));
        }

        $base = PreparationRequest::query();

        return view('preparation.index', [
            'title'    => 'طلبات التحضير (تقسيم الرسايل)',
            'rows'     => $q->paginate(25)->withQueryString(),
            'statuses' => PreparationRequest::STATUSES,
            'canWeigh' => $u?->can2('prep.weigh'),
            'canAsk'   => $u?->can2('prep.request'),
            'summary'  => [
                ['label' => 'مستنية وزن المخزن', 'value' => (clone $base)->where('status', 'open')->count(), 'tone' => 'warn',
                 'note' => 'التخطيط طلب كمية تقريبية — المخزن يوزن ويرد.'],
                ['label' => 'اتوزنت ومستنية أمر شغل', 'value' => (clone $base)->where('status', 'weighed')->count(), 'tone' => 'ok',
                 'note' => 'الوزن الفعلي جاهز — اعمل عليها أمر الشغل.'],
                ['label' => 'دخلت أوامر شغل', 'value' => (clone $base)->where('status', 'used')->count(), 'tone' => 'brand',
                 'note' => 'اتحولت لأمر شغل بالوزن الفعلي.'],
                ['label' => 'كجم محجوزة بطلبات', 'value' => number_format((float) Consignment::sum('reserved_kg'), 1), 'tone' => 'muted',
                 'note' => 'محجوزة على الرسايل لحد ما تبقى أوامر شغل.'],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $consignments = Consignment::with(['color', 'fabricType'])->readyForProduction()
            ->orderBy('arrival_date')->orderBy('id')->get();

        $pick = $request->get('consignment_id') ? $consignments->firstWhere('id', (int) $request->get('consignment_id')) : null;

        return view('preparation.form', [
            'title'        => 'طلب تحضير جديد',
            'consignments' => $consignments,
            'pick'         => $pick,
            'factories'    => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'models'       => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'openOnPick'   => $pick ? PreparationRequest::with('factory')->where('consignment_id', $pick->id)->reserving()->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'consignment_id'      => ['required', 'exists:consignments,id'],
            'factory_id'          => ['nullable', 'exists:factories,id'],
            'requested_qty'       => ['nullable', 'numeric', 'min:0'],
            'is_remainder'        => ['nullable', 'boolean'],
            'product_model_ids'   => ['nullable', 'array'],
            'product_model_ids.*' => ['integer', 'exists:product_models,id'],
            'models_note'         => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'also_marker'         => ['nullable', 'boolean'],
        ], [], [
            'consignment_id' => 'الرسالة',
            'requested_qty'  => 'الكمية المطلوبة',
        ]);

        $c = Consignment::findOrFail($data['consignment_id']);
        $c->recalcRemaining();
        $c->refresh();

        $remainder = $request->boolean('is_remainder');
        $qty = $remainder ? (float) $c->remaining_kg : (float) ($data['requested_qty'] ?? 0);

        if ($qty <= 0) {
            return back()->withInput()->withErrors(['requested_qty' => $remainder
                ? 'مفيش باقي متاح على الرسالة دي — كله محجوز أو اتصرف.'
                : 'اكتب الكمية التقريبية اللي محتاجها (بالكيلو).']);
        }

        // مجموع طلبات التحضير ما يتعداش رصيد الرسالة (الميتينج: 150 + 150 + 200 من 500)
        if (!$remainder && $qty > (float) $c->remaining_kg + 0.0005) {
            return back()->withInput()->withErrors(['requested_qty' =>
                'المتاح على الرسالة ' . rtrim(rtrim(number_format((float) $c->remaining_kg, 3), '0'), '.')
                . ' كجم بس (بعد خصم الطلبات والأوامر المفتوحة) — قلّل الكمية أو اختار «الباقي».']);
        }

        $modelIds = array_values(array_unique(array_map('intval', $data['product_model_ids'] ?? [])));

        $pr = DB::transaction(function () use ($data, $c, $qty, $remainder, $modelIds) {
            $pr = PreparationRequest::create([
                'doc_no'         => DocNumber::next('preparation_request', 'preparation_requests'),
                'doc_date'       => now()->toDateString(),   // بياخد تاريخ اليوم
                'consignment_id' => $c->id,
                'factory_id'     => $data['factory_id'] ?? null,
                'requested_qty'  => round($qty, 3),
                'is_remainder'   => $remainder,
                'unit'           => 'كجم',
                'models_note'    => $data['models_note'] ?? null,
                'status'         => 'open',
                'notes'          => $data['notes'] ?? null,
                'requested_by'   => auth()->id(),
                'created_by'     => auth()->id(),
            ]);
            $pr->models()->sync($modelIds);

            // الحجز الفوري على الرسالة
            $c->recalcRemaining();

            return $pr;
        });

        $pr->load('models');
        Notifier::broadcastToRole('storekeeper', 'prep_request',
            'طلب تحضير من التخطيط — اوزن ورد',
            $pr->doc_no . ' — الرسالة ' . $c->consignment_no . ' · '
                . ($remainder ? 'الباقي كله' : rtrim(rtrim(number_format($qty, 3), '0'), '.') . ' كجم تقريبًا')
                . ($pr->models_label ? ' · ' . $pr->models_label : ''),
            route('preparation-requests.index'), 'warning');

        ActivityLogger::log('created', $pr, 'طلب تحضير ' . $pr->doc_no . ' على الرسالة ' . $c->consignment_no);

        // الطلب الموازي للباترونست — بيتعمل لوحده مع نفس الحفظ (الميتينج: الاتنين في نفس الوقت)
        $markerMsg = '';
        if ($request->boolean('also_marker')) {
            if (!$c->min_width_cm) {
                $markerMsg = ' · طلب الماركر ما اتعملش: الرسالة ملهاش أقل عرض من الفحص.';
            } elseif (!$modelIds) {
                $markerMsg = ' · طلب الماركر ما اتعملش: اختار الموديلات الأول.';
            } else {
                $mr = $this->createMarkerRequest($pr, $c, $modelIds);
                $markerMsg = ' · واتعمل طلب الماركر ' . $mr->doc_no . ' ونزل للباترونست.';
            }
        }

        return redirect()->route('preparation-requests.index')
            ->with('success', 'اتسجل طلب التحضير ' . $pr->doc_no . ' واتحجزت الكمية على الرسالة — المخزن هيوزن ويرد.' . $markerMsg);
    }

    /** طلب الماركر الموازي: نفس الرسالة والموديلات، العرض والبنشر من الجودة */
    private function createMarkerRequest(PreparationRequest $pr, Consignment $c, array $modelIds): \App\Models\MarkerRequest
    {
        $names = ProductModel::whereIn('id', $modelIds)->orderBy('name')->get()->pluck('label')->implode(' + ');

        $mr = \App\Models\MarkerRequest::create([
            'doc_no'                 => DocNumber::next('marker_request', 'marker_requests'),
            'doc_date'               => now()->toDateString(),
            'consignment_id'         => $c->id,
            'preparation_request_id' => $pr->id,
            'fabric_width_cm'        => $c->min_width_cm,
            'avg_gsm'                => $c->avg_gsm,
            'requested_models'       => $names,
            'copies'                 => 2,
            'notes'                  => $pr->notes ? 'من طلب التحضير ' . $pr->doc_no . ' — ' . $pr->notes : 'من طلب التحضير ' . $pr->doc_no,
            'status'                 => 'open',
            'created_by'             => auth()->id(),
        ]);
        $mr->models()->sync($modelIds);
        $pr->update(['marker_request_id' => $mr->id]);

        Notifier::broadcastToRole('patternist', 'marker_request', 'طلب ماركر جديد',
            $mr->doc_no . ' — الرسالة ' . $c->consignment_no . ' · عرض ' . $c->min_width_cm . ' سم · ' . $names,
            route('markers.requests'), 'info');
        ActivityLogger::log('created', $mr, 'طلب ماركر ' . $mr->doc_no . ' اتعمل مع طلب التحضير ' . $pr->doc_no);

        return $mr;
    }

    /** المخزن يرد بالوزن الفعلي */
    public function weigh(Request $request, PreparationRequest $preparation_request)
    {
        abort_unless($preparation_request->status === 'open', 403, 'الطلب ده اتوزن قبل كده أو اتلغى.');

        $data = $request->validate([
            'actual_qty'   => ['nullable', 'numeric', 'min:0'],
            'actual_rolls' => ['nullable', 'integer', 'min:0'],
            'store_note'   => ['nullable', 'string'],
        ], [], ['actual_qty' => 'الوزن الفعلي', 'actual_rolls' => 'عدد الأتواب']);

        $pr = $preparation_request;
        $c  = $pr->consignment;

        /* آخر تقسيمة «الباقي» ما بتتوزنش: بتاخد اللي فاضل فعلًا على الرسالة
           بعد كل الحجوزات التانية — والفرق بالزيادة أو النقص على الرسالة. */
        if ($pr->is_remainder) {
            $others = (float) PreparationRequest::where('consignment_id', $c->id)->reserving()
                ->where('id', '!=', $pr->id)->get()->sum('reserved_qty');
            $actual = max(0, (float) $c->released_kg - (float) $c->allocated_kg - $others);
        } else {
            $actual = (float) ($data['actual_qty'] ?? 0);
            if ($actual <= 0) {
                return back()->withErrors(['actual_qty' => 'اكتب الوزن الفعلي للأتواب اللي اتوزنت.']);
            }
        }

        DB::transaction(function () use ($pr, $c, $actual, $data) {
            $pr->update([
                'actual_qty'   => round($actual, 3),
                'actual_rolls' => $data['actual_rolls'] ?? null,
                'store_note'   => $data['store_note'] ?? null,
                'status'       => 'weighed',
                'weighed_by'   => auth()->id(),
                'weighed_at'   => now(),
            ]);
            $c->recalcRemaining();
        });

        $diff = round($actual - (float) $pr->requested_qty, 3);
        Notifier::send($pr->requested_by, 'prep_weighed',
            'المخزن وزن طلب التحضير ' . $pr->doc_no,
            'الرسالة ' . $c->consignment_no . ' — الفعلي ' . rtrim(rtrim(number_format($actual, 3), '0'), '.') . ' كجم'
                . ($pr->is_remainder ? ' (الباقي)' : ' (المطلوب كان ' . rtrim(rtrim(number_format((float) $pr->requested_qty, 3), '0'), '.') . ' · فرق ' . ($diff >= 0 ? '+' : '') . $diff . ')')
                . ' · اعمل أمر الشغل بالرقم ده.',
            route('work-orders.create', ['preparation_request_id' => $pr->id]), 'info');

        ActivityLogger::log('weighed', $pr, 'وزن طلب التحضير ' . $pr->doc_no . ' — الفعلي ' . $actual . ' كجم');

        return back()->with('success', 'اتسجل الوزن الفعلي — التخطيط وصله إشعار يعمل أمر الشغل.');
    }

    public function cancel(PreparationRequest $preparation_request)
    {
        abort_unless(in_array($preparation_request->status, ['open', 'weighed'], true), 403, 'الطلب ده دخل أمر شغل — مينفعش يتلغي.');

        DB::transaction(function () use ($preparation_request) {
            $preparation_request->update(['status' => 'cancelled']);
            $preparation_request->consignment?->recalcRemaining();
        });

        ActivityLogger::log('cancelled', $preparation_request, 'إلغاء طلب التحضير ' . $preparation_request->doc_no);

        return back()->with('success', 'اتلغى الطلب والحجز رجع للرسالة.');
    }
}
