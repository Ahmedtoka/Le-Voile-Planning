<?php

namespace App\Console\Commands;

use App\Models\CutDeclaration;
use App\Models\FabricInspection;
use App\Models\GoodsReceipt;
use App\Models\LabReport;
use App\Models\Marker;
use App\Models\MaterialIssue;
use App\Models\ProductionReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAddition;
use App\Models\WorkOrder;
use App\Services\FlowEngine;
use Illuminate\Console\Command;

/**
 * ══════════════════════════════════════════════════════════════════
 *  lv:finish-pending — تنضيف داتا ما قبل شيل الاعتمادات
 * ══════════════════════════════════════════════════════════════════
 *
 * قبل كده كان المستند لما يتبعت يقعد «تحت الاعتماد» لحد ما حد يعتمده،
 * وأثره على المخزون ما بيتطبّقش غير بعد آخر اعتماد.
 * دلوقتي مفيش اعتمادات — فالمستندات القديمة اللي عالقة في الحالة دي
 * لازم تخلص وتطبّق أثرها، وإلا هتفضل مخفية عن كل الشاشات.
 *
 * الأمر ده بيمشي عليها واحد واحد بنفس المسار الطبيعي (FlowEngine)
 * علشان الأثر يتطبّق صح — مش مجرد UPDATE على الحالة.
 * تشغيله تاني ما بيعملش حاجة، لأن مفيش مستندات pending تانية.
 */
class FinishPendingCommand extends Command
{
    protected $signature = 'lv:finish-pending {--dry : عرض بس من غير تنفيذ}';

    protected $description = 'إنهاء المستندات العالقة «تحت الاعتماد» من النسخة القديمة وتطبيق أثرها';

    /** بالترتيب ده علشان الأثر يتسلسل صح: الشراء ← الاستلام ← الفحص ← المعمل ← الإفراج ← التشغيل */
    private const ORDER = [
        PurchaseOrder::class     => 'طلب شراء',
        StockAddition::class     => 'إذن إضافة',
        FabricInspection::class  => 'تقرير فحص',
        LabReport::class         => 'تقرير معمل',
        GoodsReceipt::class      => 'إذن استلام خام',
        Marker::class            => 'ماركر',
        WorkOrder::class         => 'أمر شغل',
        MaterialIssue::class     => 'إذن صرف',
        CutDeclaration::class    => 'بيان قص',
        ProductionReceipt::class => 'استلام إنتاج',
    ];

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry');
        $total = 0;

        foreach (self::ORDER as $class => $label) {
            $docs = $class::where('status', 'pending')->orderBy('id')->get();
            if ($docs->isEmpty()) {
                continue;
            }

            $this->line("• {$label}: {$docs->count()} مستند");

            foreach ($docs as $doc) {
                $total++;
                if ($dry) {
                    continue;
                }

                try {
                    FlowEngine::complete($doc, 'إنهاء تلقائي لمستند قديم كان تحت الاعتماد');
                } catch (\Throwable $e) {
                    $this->warn("   ✗ #{$doc->id}: {$e->getMessage()}");
                }
            }
        }

        // مراحل طلب الشراء القديمة اللي كانت بتقف على الاعتماد
        $stages = PurchaseOrder::whereIn('stage', ['approval'])->get();
        foreach ($stages as $po) {
            $total++;
            if (! $dry) {
                $po->forceFill(['stage' => 'approved'])->saveQuietly();
            }
        }
        if ($stages->isNotEmpty()) {
            $this->line("• مراحل شراء قديمة: {$stages->count()}");
        }

        if ($total === 0) {
            $this->info('✓ مفيش مستندات عالقة — الداتا نضيفة.');

            return self::SUCCESS;
        }

        $this->info($dry
            ? "لو اتنفّذ هيخلّص {$total} مستند. شغّل الأمر من غير ‎--dry."
            : "✓ اتخلّص {$total} مستند وأثرهم اتطبّق.");

        return self::SUCCESS;
    }
}
