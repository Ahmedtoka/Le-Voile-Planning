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
use App\Services\ApprovalEngine;
use App\Services\DocNumber;
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
        $q = MarkerRequest::with(['consignment', 'factory', 'patternist', 'marker'])->latest('id');
        if ($s = $request->get('status')) $q->where('status', $s);

        return view('markers.requests', [
            'title'    => 'طلبات الماركر',
            'rows'     => $q->paginate(25)->withQueryString(),
            'statuses' => MarkerRequest::STATUSES,
        ]);
    }

    public function createRequest()
    {
        return view('markers.request_form', [
            'title'        => 'طلب ماركر جديد',
            'row'          => new MarkerRequest(['doc_date' => now()->toDateString()]),
            'consignments' => Consignment::readyForProduction()->orWhere('status', 'inspected')
                                ->latest('id')->get(),
            'factories'    => Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'patternists'  => User::whereHas('roles', fn ($q) => $q->where('key', 'patternist'))
                                ->where('is_active', true)->pluck('name', 'id'),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'doc_date'         => ['required', 'date'],
            'consignment_id'   => ['nullable', 'exists:consignments,id'],
            'factory_id'       => ['nullable', 'exists:factories,id'],
            'fabric_width_cm'  => ['required', 'numeric', 'min:1'],
            'requested_models' => ['required', 'string'],
            'assigned_to'      => ['nullable', 'exists:users,id'],
            'needed_by'        => ['nullable', 'date'],
            'notes'            => ['nullable', 'string'],
        ], [], [
            'fabric_width_cm'  => 'عرض القماش',
            'requested_models' => 'الموديلات المطلوبة',
        ]);

        $req = MarkerRequest::create($data + [
            'doc_no'     => DocNumber::next('marker_request', 'marker_requests'),
            'status'     => 'open',
            'created_by' => auth()->id(),
        ]);

        if ($req->assigned_to) {
            Notifier::send($req->assigned_to, 'marker_request', 'طلب ماركر جديد',
                $req->doc_no . ' — على عرض ' . $req->fabric_width_cm . ' سم',
                route('markers.requests'), 'info');
        }

        return redirect()->route('markers.requests')->with('success', 'تم إنشاء الطلب ' . $req->doc_no);
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

        if ($reqId = $request->get('request_id')) {
            if ($mr = MarkerRequest::find($reqId)) {
                $row->marker_request_id = $mr->id;
                $row->factory_id        = $mr->factory_id;
                $row->fabric_width_cm   = $mr->fabric_width_cm;
            }
        }

        return view('markers.form', $this->formData(['row' => $row, 'mode' => 'create']));
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
        $marker->load(['lines.productModel', 'lines.size', 'approval.steps']);
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

        ApprovalEngine::submit($marker);
        return back()->with('success', 'تم الإرسال للاعتماد.');
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

        // لو المستخدم ما دخلش الإجمالي، بيتحسب من السطور
        $v['pieces_per_spread'] = $v['pieces_per_spread']
            ?? array_sum(array_column($lines, 'qty_per_spread'));

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
