<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Factory;
use App\Models\Marker;
use App\Models\MarkerLine;
use App\Models\MarkerRequest;
use App\Models\ProductModel;
use App\Models\Size;
use App\Models\User;
use App\Services\DocNumber;
use App\Services\FlowEngine;
use App\Services\FlowMessage;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * الماركرات وطلباتها.
 *
 * الفلو: المخطط بيفتح طلب ماركر ويقول "موديل X عند مصنع Y على عرض 185".
 * الباترونست بيدخل بنفسه على السيستم ويرفع الماركر ببياناته
 * (طول الفرشة + عدد القطع في الفرشة + الموديلات والمقاسات).
 */
class MarkerController extends Controller
{
    // ── طلبات الماركر ──

    public function requests(Request $request)
    {
        $q = MarkerRequest::with(['consignment', 'factory', 'patternist', 'marker', 'models', 'suggestedMarker', 'preparationRequest'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);

        return view('markers.requests', [
            'title'    => 'طلبات الماركر',
            'rows'     => $q->paginate(25)->withQueryString(),
            'statuses' => MarkerRequest::STATUSES,
        ]);
    }

    public function createRequest(Request $request)
    {
        $row = new MarkerRequest(['doc_date' => now()->toDateString(), 'copies' => 2]);
        $row->consignment_id         = $request->get('consignment_id');
        $row->preparation_request_id = $request->get('preparation_request_id');

        // جاي من طلب تحضير؟ نفس الرسالة والموديلات
        $picked = array_map('intval', (array) $request->get('models', []));
        if ($row->preparation_request_id && ($pr = \App\Models\PreparationRequest::with('models')->find($row->preparation_request_id))) {
            $row->consignment_id = $row->consignment_id ?: $pr->consignment_id;
            $picked = $picked ?: $pr->models->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        return view('markers.request_form', [
            'title'        => 'طلب ماركر جديد',
            'row'          => $row,
            'picked'       => $picked,
            // الأحواض اللي اتفرج عنها — الأقدم أولًا، والعرض والبنشر بييجوا منها مقفولين
            'consignments' => Consignment::with(['color', 'fabricType'])->readyForProduction()
                                ->orderBy('arrival_date')->orderBy('id')->get(),
            'models'       => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'patternists'  => User::whereHas('roles', fn ($q) => $q->where('key', 'patternist'))
                                ->where('is_active', true)->pluck('name', 'id'),
        ]);
    }

    /**
     * اقتراح ماركر قديم (الميتينج): نفس الموديلات على نفس العرض أو قريب منه.
     * الباترونست ممكن يعمل تعشيق أكفأ — بس لو فيه ماركر جاهز، التخطيط يقدر يستخدمه على طول.
     */
    public function suggest(Request $request)
    {
        $c = Consignment::find($request->get('consignment_id'));
        $modelIds = array_values(array_filter(array_map('intval', (array) $request->get('models', []))));
        if (!$c || !$c->min_width_cm) return response()->json([]);

        $tol   = (float) config('lvplanning.marker_suggest_width_tolerance_cm', 2);
        $width = (float) ($c->usable_width ?? $c->min_width_cm);   // الاقتراح على عرض التشغيل

        $q = Marker::with(['lines.productModel', 'lines.size'])
            ->where('status', 'approved')->where('is_active', true)
            ->whereBetween('fabric_width_cm', [$width - $tol, $width + 0.001]);   // ما يتعداش عرض القماش
        if ($modelIds) {
            $q->whereHas('lines', fn ($l) => $l->whereIn('product_model_id', $modelIds));
        }

        $out = $q->get()->map(function ($m) use ($modelIds, $width) {
            $mids  = $m->lines->pluck('product_model_id')->unique()->map(fn ($v) => (int) $v)->all();
            $cover = $modelIds ? count(array_intersect($modelIds, $mids)) / max(1, count($modelIds)) : 1;
            return [
                'id'         => $m->id,
                'code'       => $m->code,
                'width'      => (float) $m->fabric_width_cm,
                'spread'     => (float) $m->spread_length_m,
                'pieces'     => (int) $m->pieces_per_spread,
                'efficiency' => $m->efficiency_pct !== null ? (float) $m->efficiency_pct : null,
                'models'     => $m->lines->map(fn ($l) => ($l->productModel?->name ?? '') . ($l->size ? ' ' . $l->size->name : '') . ' ×' . $l->qty_per_spread)->implode('، '),
                'exact'      => abs((float) $m->fabric_width_cm - $width) < 0.01,
                'cover'      => round($cover, 2),
                'url'        => route('markers.edit', $m),
            ];
        })->sortBy([['cover', 'desc'], ['exact', 'desc'], ['efficiency', 'desc']])->values()->take(6);

        return response()->json($out);
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'doc_date'              => ['required', 'date'],
            'consignment_id'        => ['required', 'exists:consignments,id'],
            'preparation_request_id'=> ['nullable', 'exists:preparation_requests,id'],
            'product_model_ids'     => ['required', 'array', 'min:1'],
            'product_model_ids.*'   => ['integer', 'exists:product_models,id'],
            'sizes_note'            => ['nullable', 'string', 'max:191'],
            'copies'                => ['nullable', 'integer', 'min:0', 'max:50'],
            'assigned_to'           => ['nullable', 'exists:users,id'],
            'needed_by'             => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string'],
            'use_marker_id'         => ['nullable', 'exists:markers,id'],   // ماركر قديم مقترح — استخدمه على طول
        ], [], [
            'consignment_id'    => 'الرسالة',
            'product_model_ids' => 'الموديلات المطلوبة',
        ]);

        // العرض والبنشر من الجودة — مش من الفورم (labels مقفولة)
        $c = Consignment::findOrFail($data['consignment_id']);
        if (!$c->min_width_cm) {
            return back()->withInput()->withErrors(['consignment_id' => 'الرسالة دي ملهاش أقل عرض من الفحص — مينفعش نطلب ماركر عليها.']);
        }

        $modelIds = array_values(array_unique(array_map('intval', $data['product_model_ids'])));
        $names    = ProductModel::whereIn('id', $modelIds)->orderBy('name')->get()->pluck('label')->implode(' + ');

        $useMarker = !empty($data['use_marker_id']) ? Marker::find($data['use_marker_id']) : null;

        $req = DB::transaction(function () use ($data, $c, $modelIds, $names, $useMarker) {
            $req = MarkerRequest::create([
                'doc_no'                 => DocNumber::next('marker_request', 'marker_requests'),
                'doc_date'               => $data['doc_date'],
                'consignment_id'         => $c->id,
                'preparation_request_id' => $data['preparation_request_id'] ?? null,
                'fabric_width_cm'        => $c->usable_width ?? $c->min_width_cm,   // عرض التشغيل (الميتينج 37)
                'avg_gsm'                => $c->avg_gsm,
                'requested_models'       => $names . (!empty($data['sizes_note']) ? ' — ' . $data['sizes_note'] : ''),
                'sizes_note'             => $data['sizes_note'] ?? null,
                'copies'                 => $data['copies'] ?? 2,
                'assigned_to'            => $data['assigned_to'] ?? null,
                'needed_by'              => $data['needed_by'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'suggested_marker_id'    => $useMarker?->id,
                'marker_id'              => $useMarker?->id,
                'status'                 => $useMarker ? 'delivered' : 'open',
                'created_by'             => auth()->id(),
            ]);
            $req->models()->sync($modelIds);

            if ($req->preparation_request_id) {
                \App\Models\PreparationRequest::where('id', $req->preparation_request_id)->update(['marker_request_id' => $req->id]);
            }
            return $req;
        });

        if ($useMarker) {
            return redirect()->route('markers.requests')
                ->with('success', 'اتسجل الطلب ' . $req->doc_no . ' واتقفل على الماركر القديم ' . $useMarker->code . ' — جاهز لأمر الشغل.');
        }

        $body = $req->doc_no . ' — الرسالة ' . $c->consignment_no . ' · عرض ' . $c->min_width_cm . ' سم'
            . ' · ' . $names . ' · ' . ($req->copies) . ' نسخة مطبوعة';
        if ($req->assigned_to) {
            Notifier::send($req->assigned_to, 'marker_request', 'طلب ماركر جديد', $body, route('markers.requests'), 'info');
        } else {
            Notifier::broadcastToRole('patternist', 'marker_request', 'طلب ماركر جديد', $body, route('markers.requests'), 'info');
        }

        return redirect()->route('markers.requests')->with(FlowMessage::flash('marker_request.created', null, ['no' => $req->doc_no]));
    }

    // ── الماركرات ──

    public function index(Request $request)
    {
        $q = Marker::with(['factory', 'lines.productModel', 'lines.size', 'patternist'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);
        if ($w = $request->get('width'))  $q->where('fabric_width_cm', '<=', $w);

        return view('markers.index', ['title' => 'الماركرات', 'rows' => $q->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $row = new Marker(['status' => 'draft', 'is_active' => true]);

        $preset = [];
        if ($reqId = $request->get('request_id')) {
            if ($mr = MarkerRequest::with('models')->find($reqId)) {
                $row->marker_request_id = $mr->id;
                $row->factory_id        = $mr->factory_id;
                $row->fabric_width_cm   = $mr->fabric_width_cm;
                $row->name              = $mr->requested_models ? \Illuminate\Support\Str::limit($mr->requested_models, 120, '') : null;
                // الموديلات المطلوبة تنزل جاهزة — الباترونست يكمّل القطع في الفرشة
                foreach ($mr->models as $m) $preset[] = ['product_model_id' => $m->id, 'qty_per_spread' => 1];
            }
        }

        return view('markers.form', $this->formData(['row' => $row, 'mode' => 'create', 'preset' => $preset]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $marker = DB::transaction(function () use ($data, $request) {
            $marker = Marker::create($data['header'] + [
                'code'                  => DocNumber::next('marker', 'markers', 'code'),
                'status'                => 'draft',
                'is_active'             => true,
                'created_by_patternist' => auth()->id(),
            ]);

            $this->syncLines($marker, $data['lines']);

            if ($request->hasFile('marker_file')) {
                $marker->forceFill(['file_path' => $request->file('marker_file')->store('markers', 'public')])->saveQuietly();
            }

            $marker->refresh()->recalcPieces();

            if ($marker->marker_request_id) {
                MarkerRequest::where('id', $marker->marker_request_id)
                    ->update(['marker_id' => $marker->id, 'status' => 'delivered']);
            }

            return $marker;
        });

        return redirect()->route('markers.edit', $marker)->with('success', 'تم إنشاء الماركر ' . $marker->code);
    }

    public function edit(Marker $marker)
    {
        $marker->load(['lines.productModel', 'lines.size', ]);
        return view('markers.form', $this->formData(['row' => $marker, 'mode' => 'edit']));
    }

    public function update(Request $request, Marker $marker)
    {
        abort_unless($marker->isEditable(), 403);
        $data = $this->validated($request);

        DB::transaction(function () use ($marker, $data, $request) {
            $marker->update($data['header']);
            $this->syncLines($marker, $data['lines'], true);

            if ($request->hasFile('marker_file')) {
                $marker->forceFill(['file_path' => $request->file('marker_file')->store('markers', 'public')])->saveQuietly();
            }

            $marker->refresh()->recalcPieces();
        });

        return back()->with('success', 'تم الحفظ.');
    }

    public function submit(Marker $marker)
    {
        abort_unless($marker->isEditable(), 403);

        if (!$marker->lines()->count()) {
            return back()->withErrors(['msg' => 'الماركر لازم يكون فيه موديل واحد على الأقل.']);
        }

        // عرض التعشيقة ما ينفعش يتعدى عرض القماش
        $need = (float) ($marker->marker_width_cm ?: $marker->fabric_width_cm);
        if ($marker->marker_width_cm && $need > (float) $marker->fabric_width_cm) {
            return back()->withErrors(['msg' =>
                'عرض التعشيقة (' . $marker->marker_width_cm . ') أكبر من عرض القماش ('
                . $marker->fabric_width_cm . '). ده هيخلّينا نحرق الجنب ونرميه.']);
        }

        FlowEngine::complete($marker, 'الماركر ' . $marker->code . ' اتسجّل وخلص');
        return back()->with(FlowMessage::flash('marker.done', $marker));
    }

    public function destroy(Marker $marker)
    {
        abort_unless($marker->isDraft(), 403);
        $marker->delete();
        return redirect()->route('markers.index')->with('success', 'تم الحذف.');
    }

    private function formData(array $extra): array
    {
        return array_merge([
            'title'     => 'ماركر',
            'factories' => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'models'    => ProductModel::where('is_active', true)->orderBy('name')->get()->pluck('label', 'id'),
            'sizes'     => Size::ordered()->where('is_active', true)->pluck('name', 'id'),
            'requests'  => MarkerRequest::whereIn('status', ['open','in_progress'])->pluck('doc_no', 'id'),
        ], $extra);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name'              => ['nullable', 'string', 'max:191'],
            'marker_request_id' => ['nullable', 'exists:marker_requests,id'],
            'factory_id'        => ['nullable', 'exists:factories,id'],
            'fabric_width_cm'   => ['required', 'numeric', 'min:1'],
            'marker_width_cm'   => ['nullable', 'numeric', 'min:1'],
            'spread_length_m'   => ['required', 'numeric', 'min:0.01'],
            'pieces_per_spread' => ['nullable', 'integer', 'min:1'],
            'efficiency_pct'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'             => ['nullable', 'string'],
            'marker_file'       => ['nullable', 'file', 'max:8192'],

            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.product_model_id'     => ['required', 'exists:product_models,id'],
            'lines.*.size_id'              => ['nullable', 'exists:sizes,id'],
            'lines.*.qty_per_spread'       => ['required', 'integer', 'min:1'],
            'lines.*.notes'                => ['nullable', 'string'],
        ], [], [
            'fabric_width_cm'        => 'عرض القماش',
            'spread_length_m'        => 'طول الفرشة',
            'pieces_per_spread'      => 'عدد القطع في الفرشة',
            'lines'                  => 'الموديلات',
            'lines.*.qty_per_spread' => 'عدد القطع',
        ]);

        $lines = $v['lines'];
        unset($v['lines'], $v['marker_file']);

        // لو المستخدم ما دخلش الإجمالي، بيتحسب من السطور.
        // ?: مش ?? — لأن الحقل الفاضي بييجي null مش مفقود.
        $v['pieces_per_spread'] = $v['pieces_per_spread']
            ?: array_sum(array_column($lines, 'qty_per_spread'));

        return ['header' => $v, 'lines' => $lines];
    }

    private function syncLines(Marker $marker, array $lines, bool $replace = false): void
    {
        if ($replace) $marker->lines()->delete();

        foreach ($lines as $l) {
            MarkerLine::create(['marker_id' => $marker->id] + $l);
        }
    }
}
