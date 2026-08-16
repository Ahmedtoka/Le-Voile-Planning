<?php

namespace App\Http\Controllers;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\FabricType;
use App\Models\Supplier;
use App\Services\PlanningEngine;
use Illuminate\Http\Request;

/** الأحواض/الرسائل — الشاشة اللي بيبدأ منها كل تشغيل. */
class ConsignmentController extends Controller
{
    public function index(Request $request)
    {
        $q = Consignment::with(['supplier', 'fabricType', 'color', 'warehouse'])->latest('id');

        if ($s = $request->get('status'))   $q->where('status', $s);
        if ($c = $request->get('color_id')) $q->where('color_id', $c);
        if ($f = $request->get('fabric_type_id')) $q->where('fabric_type_id', $f);
        if ($request->boolean('ready'))     $q->readyForProduction();
        if ($term = trim((string) $request->get('q'))) $q->where('consignment_no', 'like', "%{$term}%");

        if ($from = $request->get('from')) $q->whereDate('arrival_date', '>=', $from);
        if ($to   = $request->get('to'))   $q->whereDate('arrival_date', '<=', $to);
        if ($sup  = $request->get('supplier_id')) $q->where('supplier_id', $sup);

        $b = fn () => Consignment::query();

        return view('consignments.index', [
            'title'       => 'الأحواض (الرسائل)',
            'rows'        => $q->paginate(25)->withQueryString(),
            'suppliers'   => \App\Models\Supplier::orderBy('name')->pluck('name', 'id'),
            'summary'     => [
                ['label' => 'تحت الفحص', 'value' => $b()->where('status','under_inspection')->count(), 'tone' => 'warn',
                 'note' => 'وصلت بإذن إضافة ومحجوزة — ممنوع تشغيلها.'],
                ['label' => 'متفحصة/خلّصت معمل', 'value' => $b()->whereIn('status',['inspected','lab_done'])->count(), 'tone' => 'brand',
                 'note' => 'ناقصها إذن استلام عشان تتفرج.'],
                ['label' => 'جاهزة للتشغيل', 'value' => Consignment::readyForProduction()->count(), 'tone' => 'ok',
                 'sub' => number_format((float) Consignment::readyForProduction()->sum('remaining_kg'), 0) . ' كجم',
                 'note' => 'مفرج عنها وعندها عرض وبنشر.'],
                ['label' => 'كجم محجوزة', 'value' => number_format((float) Consignment::onHold()->sum('total_kg'), 0), 'tone' => 'warn',
                 'note' => 'قماش في المخزن ممنوع تشغيله.'],
                ['label' => 'مرفوضة', 'value' => $b()->where('status','rejected')->count(), 'tone' => 'danger',
                 'note' => 'اترفضت في الفحص.'],
            ],
            'statuses'    => Consignment::STATUSES,
            'colors'      => Color::usable()->orderBy('code')->get()->pluck('label', 'id'),
            'fabricTypes' => FabricType::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Consignment $consignment)
    {
        $consignment->load([
            'supplier', 'fabricType', 'color', 'warehouse', 'purchaseOrder',
            'rolls', 'inspections.rolls', 'labReports.readings',
            'workOrderFabrics.workOrder.factory',
        ]);

        return view('consignments.show', [
            'title'    => 'الحوض ' . $consignment->consignment_no,
            'row'      => $consignment,
            'warnings' => PlanningEngine::warnings($consignment, null),
        ]);
    }

    public function update(Request $request, Consignment $consignment)
    {
        $data = $request->validate([
            'total_kg'       => ['required', 'numeric', 'min:0'],
            'rolls_count'    => ['required', 'integer', 'min:0'],
            'total_length_m' => ['nullable', 'numeric', 'min:0'],
            // الحالة بتتحرك بالمستندات — التعديل اليدوي للرفض/القفل بس
            'status'         => ['required', 'in:rejected,closed,' . $consignment->status],
            'notes'          => ['nullable', 'string'],
        ], [], ['total_kg' => 'إجمالي الوزن', 'rolls_count' => 'عدد الأتواب']);

        $data['total_length_m'] = $data['total_length_m'] ?? 0;

        $consignment->update($data);
        $consignment->recalcRemaining();

        return back()->with('success', 'تم الحفظ.');
    }
}
