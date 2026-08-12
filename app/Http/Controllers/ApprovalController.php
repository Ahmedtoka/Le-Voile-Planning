<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Services\ActivityLogger;
use App\Services\ApprovalEngine;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /** صندوق الاعتمادات — اللي مستني مني */
    public function index(Request $request)
    {
        $user = auth()->user();

        $mine = ApprovalEngine::pendingFor($user)->paginate(25);

        $all = Approval::with(['steps', 'requester'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('doc_type'), fn ($q, $t) => $q->where('doc_type', $t))
            ->latest('id')->limit(50)->get();

        return view('approvals.index', [
            'title' => 'الاعتمادات',
            'mine'  => $mine,
            'all'   => $all,
            'docTypes' => Approval::query()->distinct()->pluck('doc_type'),
        ]);
    }

    public function approve(Request $request, Approval $approval)
    {
        try {
            ApprovalEngine::approve($approval, auth()->user(), $request->input('comment'));
        } catch (\Throwable $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

        ActivityLogger::log('approved', $approval, 'اعتماد ' . $approval->subject_no);
        return back()->with('success', 'تم الاعتماد.');
    }

    public function reject(Request $request, Approval $approval)
    {
        $request->validate(['comment' => ['required', 'string']], [], ['comment' => 'سبب الرفض']);

        try {
            ApprovalEngine::reject($approval, auth()->user(), $request->input('comment'));
        } catch (\Throwable $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

        ActivityLogger::log('rejected', $approval, 'رفض ' . $approval->subject_no);
        return back()->with('success', 'تم الرفض.');
    }
}
