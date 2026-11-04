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
        $consignments = Consignment::with(['color', 'fabricType', 'purchaseOrder.models'])->readyForProduction()
            ->orderBy('arrival_date')->orderBy('id')->get();

        $pick = $request->get('consignment_id') ? $consignments->firstWhere('id', (int) $request->get('consignment_id')) : null;

        return view('preparation.form', [
            'title'        => 'طلب تحضير جديد',
            'consignments' => $consignments,
            'pick'         => $pick,
            'factories'    => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'models'       => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'fabricTypes'  => $consignments->pluck('fabricType.name', 'fabric_type_id')->filter()->unique()->sortBy(fn ($v) => $v),
            'colors'       => $consignments->map(fn ($c) => [$c->color_id, $c->color?->label ?? $c->color?->code])->filter(fn ($x) => $x[0])->unique(0)->sortBy(1)->mapWithKeys(fn ($x) => [$x[0] => $x[1]]),
        ]);
    }

    /**
     * طلب تحضير على رسالة أو أكتر (الميتينج 37): التخطيط يعلّم على الرسايل ويكتب كمية لكل واحدة
     * (أو «الباقي كله»)، وكل رسالة بتنزل للمخزن كطلب لوحده يتوزن لوحده — كلهم بنفس الموديلات والمصنع.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items'               => ['required', 'array'],
            'items.*.on'          => ['nullable', 'boolean'],
            'items.*.qty'         => ['nullable', 'numeric', 'min:0'],
            'items.*.rem'         => ['nullable', 'boolean'],
            'factory_id'          => ['nullable', 'exists:factories,id'],
            'product_model_ids'   => ['nullable', 'array'],
            'product_model_ids.*' => ['integer', 'exists:product_models,id'],
            'models_note'         => ['nullable', 'string'],
            'notes'               => ['nullable', 'string'],
            'also_marker'         => ['nullable', 'boolean'],
        ], [], ['items' => 'الرسايل']);

        $picked = collect($data['items'])->filter(fn ($it) => !empty($it['on']));
        if ($picked->isEmpty()) {
            return back()->withInput()->withErrors(['items' => 'علّم على رسالة واحدة على الأقل.']);
        }

        $modelIds = array_values(array_unique(array_map('intval', $data['product_model_ids'] ?? [])));

        // تحقق قبل أي حفظ: كل رسالة ليها كمية (أو الباقي) ومش أكتر من المتاح
        $plan = [];
        foreach ($picked as $cid => $it) {
            $c = Consignment::find((int) $cid);
            if (!$c) continue;
            $c->recalcRemaining(); $c->refresh();
            $remainder = !empty($it['rem']);
            $qty = $remainder ? (float) $c->remaining_kg : (float) ($it['qty'] ?? 0);
            if ($qty <= 0) {
                return back()->withInput()->withErrors(['items' => $remainder
                    ? 'الرسالة ' . $c->consignment_no . ': مفيش باقي متاح — كله محجوز أو اتصرف.'
                    : 'الرسالة ' . $c->consignment_no . ': اكتب الكمية التقريبية اللي محتاجها.']);
            }
            if (!$remainder && $qty > (float) $c->remaining_kg + 0.0005) {
                return back()->withInput()->withErrors(['items' =>
                    'الرسالة ' . $c->consignment_no . ': المتاح ' . lv_num($c->remaining_kg) . ' ' . $c->unit_label
                    . ' بس (بعد خصم الطلبات والأوامر المفتوحة) — قلّل الكمية أو اختار «الباقي كله».']);
            }
            $plan[] = [$c, $qty, $remainder];
        }
        if (!$plan) return back()->withInput()->withErrors(['items' => 'علّم على رسالة واحدة على الأقل.']);

        $created = []; $markers = []; $markerIssues = [];
        DB::transaction(function () use ($plan, $data, $modelIds, $request, &$created, &$markers, &$markerIssues) {
            $batch = null;
            foreach ($plan as [$c, $qty, $remainder]) {
                $pr = PreparationRequest::create([
                    'doc_no'         => DocNumber::next('preparation_request', 'preparation_requests'),
                    'doc_date'       => now()->toDateString(),   // بياخد تاريخ اليوم
                    'consignment_id' => $c->id,
                    'factory_id'     => $data['factory_id'] ?? null,
                    'requested_qty'  => round($qty, 3),
                    'is_remainder'   => $remainder,
                    'unit'           => $c->unit ?: 'كجم',
                    'models_note'    => $data['models_note'] ?? null,
                    'status'         => 'open',
                    'notes'          => $data['notes'] ?? null,
                    'requested_by'   => auth()->id(),
                    'created_by'     => auth()->id(),
                ]);
                $batch = $batch ?: $pr->doc_no;
                if (count($plan) > 1) $pr->update(['batch_key' => $batch]);
                $pr->models()->sync($modelIds);
                $c->recalcRemaining();   // الحجز الفوري على الرسالة
                $created[] = $pr;

                // الطلب الموازي للباترونست — لكل رسالة (العرض والبنشر بيختلفوا من رسالة للتانية)
                if ($request->boolean('also_marker')) {
                    if (!$c->min_width_cm)   $markerIssues[] = $c->consignment_no . ' ملهاش أقل عرض من الفحص';
                    elseif (!$modelIds)      $markerIssues[] = 'اختار الموديلات الأول';
                    else                     $markers[] = $this->createMarkerRequest($pr, $c, $modelIds);
                }
            }
        });

        foreach ($created as $pr) {
            $pr->load('models', 'consignment');
            ActivityLogger::log('created', $pr, 'طلب تحضير ' . $pr->doc_no . ' على الرسالة ' . $pr->consignment?->consignment_no);
        }
        $lines = collect($created)->map(fn ($pr) => $pr->doc_no . ' — ' . $pr->consignment?->consignment_no . ' · '
            . ($pr->is_remainder ? 'الباقي كله' : lv_num($pr->requested_qty) . ' ' . ($pr->consignment?->unit_label ?? 'كجم') . ' تقريبًا'))->implode(' | ');
        Notifier::broadcastToRole('storekeeper', 'prep_request',
            count($created) > 1 ? 'طلب تحضير من التخطيط على ' . count($created) . ' رسايل — اوزن كل واحدة ورد' : 'طلب تحضير من التخطيط — اوزن ورد',
            $lines . ($created[0]->models_label ? ' · ' . $created[0]->models_label : ''),
            route('preparation-requests.index'), 'warning');

        $msg = count($created) > 1
            ? 'اتسجل ' . count($created) . ' طلبات تحضير (' . collect($created)->pluck('doc_no')->implode('، ') . ') واتحجزت الكميات على الرسايل — المخزن هيوزن كل رسالة لوحدها ويرد.'
            : 'اتسجل طلب التحضير ' . $created[0]->doc_no . ' واتحجزت الكمية على الرسالة — المخزن هيوزن ويرد.';
        if ($markers)      $msg .= ' · واتعمل طلب الماركر ' . collect($markers)->pluck('doc_no')->implode('، ') . ' ونزل للباترونست.';
        if ($markerIssues) $msg .= ' · طلب الماركر ما اتعملش: ' . implode(' / ', array_unique($markerIssues)) . '.';

        return redirect()->route('preparation-requests.index')->with('success', $msg);
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
            'fabric_width_cm'        => $c->usable_width ?? $c->min_width_cm,   // عرض التشغيل (أقل عرض − تأمين العروض)
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
            'actual_qty'       => ['nullable', 'numeric', 'min:0'],
            'actual_gross_qty' => ['nullable', 'numeric', 'min:0'],   // القائم على الميزان (الميتينج 37)
            'actual_rolls'     => ['nullable', 'integer', 'min:0'],
            'store_note'       => ['nullable', 'string'],
        ], [], ['actual_qty' => 'الوزن الفعلي', 'actual_gross_qty' => 'الوزن على الميزان', 'actual_rolls' => 'عدد الأتواب']);

        $pr = $preparation_request;
        $c  = $pr->consignment;

        /* بالكيلو: المخزن بيكتب القائم وعدد الأتواب، والصافي = القائم − (كيس + ماسورة) × الأتواب */
        $packaging = 0.0;
        if (!$pr->is_remainder && !$c->is_meter && (float) ($data['actual_gross_qty'] ?? 0) > 0) {
            $packaging = $c->packagingFor((int) ($data['actual_rolls'] ?? 0));
            $data['actual_qty'] = round(max(0, (float) $data['actual_gross_qty'] - $packaging), 3);
        }

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

        DB::transaction(function () use ($pr, $c, $actual, $data, $packaging) {
            $pr->update([
                'actual_qty'       => round($actual, 3),
                'actual_gross_qty' => $pr->is_remainder ? null : ($data['actual_gross_qty'] ?? null),
                'packaging_kg'     => $packaging,
                'actual_rolls'     => $data['actual_rolls'] ?? null,
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
