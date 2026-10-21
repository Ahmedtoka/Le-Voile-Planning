<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;

/**
 * بوابة المصنع — شاشة واحدة تتفتح من الموبايل أو اللاب (الميتينج).
 * مستخدم المصنع بيشوف أوامر الشغل بتاعته بس، ويسجل بيان القص واستلام الإنتاج من نفس المكان.
 */
class FactoryPortalController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $factoryId = $u->factory_id;

        // الأدمن ومتابعة المصانع يقدروا يختاروا أي مصنع للتجربة
        if (!$factoryId && ($u->isAdmin() || $u->can2('cut.manage'))) {
            $factoryId = (int) $request->get('factory_id') ?: null;
        }

        $factories = \App\Models\Factory::where('is_active', true)->orderBy('name')->pluck('name', 'id');

        if (!$factoryId) {
            return view('factory.portal', [
                'title'     => 'بوابة المصنع',
                'factory'   => null,
                'factories' => $factories,
                'open'      => collect(), 'done' => collect(),
            ]);
        }

        $factory = \App\Models\Factory::findOrFail($factoryId);

        $open = WorkOrder::with(['fabrics.consignment', 'fabrics.fabricType', 'fabrics.color', 'lines.productModel'])
            ->where('factory_id', $factoryId)
            ->whereIn('status', ['approved', 'sent_to_factory', 'cutting', 'cut_declared', 'in_production', 'partially_received'])
            ->orderByRaw("FIELD(status,'sent_to_factory','cutting','cut_declared','in_production','partially_received','approved')")
            ->latest('id')->get();

        $done = WorkOrder::with('factory')->where('factory_id', $factoryId)
            ->where('status', 'closed')->latest('id')->limit(10)->get();

        return view('factory.portal', [
            'title'     => 'بوابة المصنع — ' . $factory->name,
            'factory'   => $factory,
            'factories' => $factories,
            'open'      => $open,
            'done'      => $done,
        ]);
    }
}
