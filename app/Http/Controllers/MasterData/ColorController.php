<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * شاشة الألوان.
 *
 * ملاحظة مهمة: مفيش حذف هنا خالص. عندهم ~3000 كود لون اتراكموا لأن كل
 * صبغة رجعت بكود جديد. الحذف كان هيكسر كل الداتا التاريخية. البديل:
 *   • دمج  (merge)  — الكود القديم يفضل ويشاور على الجديد
 *   • إيقاف (retire) — الكود يتقفل عن الاستخدام الجديد بس يفضل مقروء
 */
class ColorController extends Controller
{
    public function index(Request $request)
    {
        $q = Color::query()->with('mergedInto');

        if ($term = trim((string) $request->get('q'))) {
            $q->where(fn ($qq) => $qq->where('code', 'like', "%{$term}%")
                                     ->orWhere('name', 'like', "%{$term}%")
                                     ->orWhere('legacy_code', 'like', "%{$term}%"));
        }

        if ($status = $request->get('status')) $q->where('status', $status);
        if ($request->boolean('basic'))        $q->where('is_basic', true);
        if ($family = $request->get('family')) $q->where('family', $family);

        return view('crud.colors', [
            'title'    => 'الألوان',
            'rows'     => $q->orderBy('code')->paginate(50)->withQueryString(),
            'families' => Color::whereNotNull('family')->distinct()->orderBy('family')->pluck('family'),
            'counts'   => [
                'all'     => Color::count(),
                'active'  => Color::where('status', 'active')->count(),
                'merged'  => Color::where('status', 'merged')->count(),
                'retired' => Color::where('status', 'retired')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:40', 'unique:colors,code'],
            'name'        => ['required', 'string', 'max:191'],
            'family'      => ['nullable', 'string', 'max:191'],
            'hex'         => ['nullable', 'string', 'max:10'],
            'is_basic'    => ['boolean'],
            'legacy_code' => ['nullable', 'string', 'max:60'],
        ], [], ['code' => 'كود اللون', 'name' => 'اسم اللون']);

        $data['is_basic'] = $request->boolean('is_basic');
        $color = Color::create($data);

        ActivityLogger::log('created', $color, 'إضافة لون: ' . $color->code);
        return back()->with('success', 'تمت إضافة اللون.');
    }

    public function update(Request $request, $id)
    {
        $color = Color::findOrFail($id);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:191'],
            'family'      => ['nullable', 'string', 'max:191'],
            'hex'         => ['nullable', 'string', 'max:10'],
            'is_basic'    => ['boolean'],
            'legacy_code' => ['nullable', 'string', 'max:60'],
        ], [], ['name' => 'اسم اللون']);

        $data['is_basic'] = $request->boolean('is_basic');
        $color->update($data);

        ActivityLogger::log('updated', $color, 'تعديل لون: ' . $color->code);
        return back()->with('success', 'تم التعديل.');
    }

    /** دمج لون في لون — من غير حذف */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'from_color_id' => ['required', 'exists:colors,id'],
            'to_color_id'   => ['required', 'exists:colors,id', 'different:from_color_id'],
            'reason'        => ['nullable', 'string'],
        ], [], ['from_color_id' => 'اللون المدموج', 'to_color_id' => 'اللون الهدف']);

        try {
            Color::merge(
                Color::findOrFail($data['from_color_id']),
                Color::findOrFail($data['to_color_id']),
                auth()->id(),
                $data['reason'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

        return back()->with('success', 'تم الدمج. الكود القديم لسه موجود وبيشاور على الجديد.');
    }

    /** إيقاف / تفعيل — بديل الحذف */
    public function toggleStatus($id)
    {
        $color = Color::findOrFail($id);

        if ($color->status === 'merged') {
            return back()->withErrors(['msg' => 'اللون ده مدموج — مينفعش تغيّر حالته.']);
        }

        $color->update(['status' => $color->status === 'active' ? 'retired' : 'active']);
        ActivityLogger::log('updated', $color, 'تغيير حالة لون: ' . $color->code . ' ← ' . $color->status);

        return back()->with('success', 'تم تغيير الحالة.');
    }
}
