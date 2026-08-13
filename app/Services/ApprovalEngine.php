<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * محرك الاعتمادات الموحّد.
 *
 * جدول واحد بيخدم كل المستندات. الدورة نفسها بتتعرّف من شاشة إعدادات
 * (approval_flows + approval_flow_steps) — يعني تغيير "مين يعتمد إيه"
 * مش محتاج كود ولا نشر جديد.
 */
class ApprovalEngine
{
    /**
     * إرسال مستند للاعتماد — بينسخ خطوات الدورة نسخة حيّة على المستند.
     */
    public static function submit(Model $doc, ?User $user = null): Approval
    {
        $user ??= auth()->user();
        $docType = method_exists($doc, 'docType') ? $doc->docType() : class_basename($doc);

        return DB::transaction(function () use ($doc, $user, $docType) {

            // إلغاء أي دورة قديمة معلّقة على نفس المستند
            Approval::where('subject_type', $doc->getMorphClass())
                ->where('subject_id', $doc->getKey())
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $flow = ApprovalFlow::with('steps')->where('doc_type', $docType)->where('is_active', true)->first();

            $steps = $flow
                ? $flow->steps->filter(function ($s) use ($doc) {
                    // خطوة بحد مبلغ: تشتغل بس لو المستند تعدّى المبلغ
                    if ($s->min_amount === null) return true;
                    $amount = (float) ($doc->total ?? 0);
                    return $amount >= (float) $s->min_amount;
                })->values()
                : collect();

            $approval = Approval::create([
                'doc_type'     => $docType,
                'subject_type' => $doc->getMorphClass(),
                'subject_id'   => $doc->getKey(),
                'subject_no'   => method_exists($doc, 'docNumber') ? $doc->docNumber() : (string) $doc->getKey(),
                'current_step' => 1,
                'total_steps'  => max(1, $steps->count()),
                'status'       => 'pending',
                'requested_by' => $user?->id,
            ]);

            if ($steps->isEmpty()) {
                // مفيش دورة معرّفة ⇒ اعتماد مباشر من مقدّم الطلب، مع تسجيله
                ApprovalStep::create([
                    'approval_id' => $approval->id,
                    'step_no'     => 1,
                    'title'       => 'اعتماد مباشر (مفيش دورة معرّفة)',
                    'user_id'     => $user?->id,
                    'status'      => 'pending',
                ]);
            } else {
                foreach ($steps as $i => $s) {
                    ApprovalStep::create([
                        'approval_id' => $approval->id,
                        'step_no'     => $i + 1,
                        'title'       => $s->title,
                        'role_id'     => $s->role_id,
                        'user_id'     => $s->user_id,
                        'status'      => $i === 0 ? 'pending' : 'waiting',
                    ]);
                }
            }

            $doc->forceFill(['status' => 'pending'])->save();

            self::notifyCurrentStep($approval);

            return $approval;
        });
    }

    /** هل المستخدم ده مسموح له يعتمد الخطوة الحالية؟ */
    public static function canAct(Approval $approval, User $user): bool
    {
        if ($approval->status !== 'pending') return false;

        $step = $approval->currentStepRow();
        if (!$step || $step->status !== 'pending') return false;

        if ($user->isAdmin()) return true;
        if ($step->user_id && $step->user_id === $user->id) return true;
        if ($step->role_id && $user->roles->contains('id', $step->role_id)) return true;

        return false;
    }

    /** اعتماد الخطوة الحالية والانتقال للي بعدها */
    public static function approve(Approval $approval, User $user, ?string $comment = null): void
    {
        if (!self::canAct($approval, $user)) {
            throw new \RuntimeException('مش مسموح لك تعتمد الخطوة دي.');
        }

        DB::transaction(function () use ($approval, $user, $comment) {
            $step = $approval->currentStepRow();
            $step->update([
                'status'   => 'approved',
                'acted_by' => $user->id,
                'acted_at' => now(),
                'comment'  => $comment,
            ]);

            $next = $approval->steps()->where('step_no', '>', $step->step_no)
                        ->orderBy('step_no')->first();

            if ($next) {
                $next->update(['status' => 'pending']);
                $approval->update(['current_step' => $next->step_no]);
                self::notifyCurrentStep($approval);
                return;
            }

            // آخر خطوة ⇒ المستند معتمد
            $approval->update(['status' => 'approved', 'completed_at' => now()]);
            self::applyToDocument($approval, 'approved');
        });
    }

    /** رفض — بيوقف الدورة كلها ويرجّع المستند قابل للتعديل */
    public static function reject(Approval $approval, User $user, ?string $comment = null): void
    {
        if (!self::canAct($approval, $user)) {
            throw new \RuntimeException('مش مسموح لك ترفض الخطوة دي.');
        }

        DB::transaction(function () use ($approval, $user, $comment) {
            $approval->currentStepRow()?->update([
                'status'   => 'rejected',
                'acted_by' => $user->id,
                'acted_at' => now(),
                'comment'  => $comment,
            ]);

            $approval->update(['status' => 'rejected', 'completed_at' => now()]);
            self::applyToDocument($approval, 'rejected');
        });
    }

    /** تطبيق نتيجة الدورة على المستند نفسه + أي آثار جانبية */
    protected static function applyToDocument(Approval $approval, string $result): void
    {
        $class = $approval->subject_type;
        if (!class_exists($class)) return;

        $doc = $class::find($approval->subject_id);
        if (!$doc) return;

        $doc->forceFill(['status' => $result])->save();

        if ($result === 'approved') {
            DocumentEffects::onApproved($doc);
        } elseif ($doc instanceof \App\Models\PurchaseOrder) {
            // الرفض بيرجّع الطلب للمشتريات عشان يتراجع — مش يفضل معلّق
            $doc->forceFill(['stage' => 'purchasing'])->save();
        }

        Notifier::send(
            $approval->requested_by,
            $result === 'approved' ? 'approval_done' : 'approval_rejected',
            $result === 'approved' ? 'تم اعتماد المستند' : 'تم رفض المستند',
            ($approval->subject_no ?? '') . ' — ' . ($result === 'approved' ? 'معتمد' : 'مرفوض'),
            null,
            $result === 'approved' ? 'info' : 'danger'
        );
    }

    /** إشعار أصحاب الخطوة الحالية */
    protected static function notifyCurrentStep(Approval $approval): void
    {
        $step = $approval->currentStepRow();
        if (!$step) return;

        $userIds = [];
        if ($step->user_id) {
            $userIds[] = $step->user_id;
        } elseif ($step->role_id) {
            $userIds = DB::table('role_user')->where('role_id', $step->role_id)->pluck('user_id')->all();
        }

        foreach ($userIds as $uid) {
            Notifier::send(
                $uid,
                'approval_pending',
                'مستند مستني اعتمادك',
                ($approval->subject_no ?? '') . ' — ' . $step->title,
                route('approvals.index'),
                'warning'
            );
        }
    }

    /** المستندات المستنية اعتماد المستخدم ده */
    public static function pendingFor(User $user)
    {
        $roleIds = $user->roles->pluck('id')->all();

        return Approval::with(['steps', 'requester'])
            ->where('status', 'pending')
            ->whereHas('steps', function ($q) use ($user, $roleIds) {
                $q->where('status', 'pending')
                  ->where(function ($qq) use ($user, $roleIds) {
                      $qq->where('user_id', $user->id);
                      if ($roleIds) $qq->orWhereIn('role_id', $roleIds);
                  });
            })
            ->latest('id');
    }
}
