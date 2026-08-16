<?php

namespace App\Http\Controllers;

use App\Models\DocumentComment;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** أنواع المستندات المسموح التعليق عليها */
    public const TYPES = [
        'purchase_order'     => \App\Models\PurchaseOrder::class,
        'stock_addition'     => \App\Models\StockAddition::class,
        'goods_receipt'      => \App\Models\GoodsReceipt::class,
        'fabric_inspection'  => \App\Models\FabricInspection::class,
        'lab_report'         => \App\Models\LabReport::class,
        'marker'             => \App\Models\Marker::class,
        'work_order'         => \App\Models\WorkOrder::class,
        'cut_declaration'    => \App\Models\CutDeclaration::class,
        'production_receipt' => \App\Models\ProductionReceipt::class,
        'material_issue'     => \App\Models\MaterialIssue::class,
        'consignment'        => \App\Models\Consignment::class,
    ];

    public function store(Request $request, string $type, int $id)
    {
        $class = self::TYPES[$type] ?? abort(404);
        $doc   = $class::findOrFail($id);

        $data = $request->validate([
            'body'       => ['required_without:attachment', 'nullable', 'string', 'max:4000'],
            'kind'       => ['required', 'in:note,question,answer,decision'],
            'reply_to_id'=> ['nullable', 'exists:document_comments,id'],
            'mentions'   => ['nullable', 'array'],
            'mentions.*' => ['exists:users,id'],
            'attachment' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
        ], [], [
            'body'       => 'التعليق',
            'attachment' => 'المرفق',
        ]);

        $payload = [
            'user_id'     => auth()->id(),
            'body'        => $data['body'] ?? null,
            'kind'        => $data['kind'],
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'mentions'    => $data['mentions'] ?? null,
        ];

        if ($file = $request->file('attachment')) {
            $payload['attachment_path'] = $file->store('comments', 'public');
            $payload['attachment_name'] = $file->getClientOriginalName();
            $payload['attachment_mime'] = $file->getMimeType();
            $payload['attachment_size'] = $file->getSize();
        }

        $comment = $doc->comments()->create($payload);

        $docNo = method_exists($doc, 'docNumber') ? $doc->docNumber() : ('#' . $doc->getKey());
        $link  = url()->previous();

        // إشعار المذكورين + صاحب المستند + آخر المتحدثين
        $targets = collect($data['mentions'] ?? [])
            // الماركر بيستخدم created_by_patternist بدل created_by
            ->merge([$doc->created_by ?? $doc->created_by_patternist ?? null])
            ->merge($doc->comments()->pluck('user_id'))
            ->filter()
            ->unique()
            ->reject(fn ($uid) => $uid == auth()->id());

        foreach ($targets as $uid) {
            Notifier::send($uid, 'comment',
                'تعليق جديد على ' . __('doc.' . $type),
                $docNo . ' — ' . auth()->user()?->name . ': ' . mb_substr((string) ($data['body'] ?? 'أرفق ملف'), 0, 90),
                $link, 'info');
        }

        ActivityLogger::log('commented', $doc, 'تعليق على ' . $docNo);

        return back()->with('success', 'تم إضافة التعليق.');
    }

    public function destroy(DocumentComment $comment)
    {
        // صاحب التعليق أو الأدمن بس — وفي أول 15 دقيقة
        $own = $comment->user_id === auth()->id();
        abort_unless($own || auth()->user()->isAdmin(), 403);

        if ($own && !auth()->user()->isAdmin() && $comment->created_at->diffInMinutes(now()) > 15) {
            return back()->withErrors(['msg' => 'مينفعش تمسح تعليق بقى له أكتر من ربع ساعة — النقاش سجل.']);
        }

        $comment->delete();
        return back()->with('success', 'تم حذف التعليق.');
    }

    /** المفتاح النصي لنوع المستند — بيستخدمه الفيو */
    public static function keyFor(object $doc): ?string
    {
        return array_search(get_class($doc), self::TYPES, true) ?: null;
    }
}
