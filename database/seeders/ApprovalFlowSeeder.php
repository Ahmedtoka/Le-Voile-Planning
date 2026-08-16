<?php

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * دورات الاعتماد الافتراضية.
 *
 * دي نقطة بداية مبنية على التوقيعات الموجودة على المستندات الورقية.
 * تقدر تغيّرها كلها من شاشة "دورات الاعتماد" من غير أي كود.
 */
class ApprovalFlowSeeder extends Seeder
{
    public const FLOWS = [
        // طلب الشراء مالوش دورة اعتماد — الدورة بتنتهي بعلم الحسابات
        // ① أول مستند: وصول القماش وحجزه
        'stock_addition' => ['إذن إضافة (تحت الفحص)', [
            ['مراجعة مراقب المخزون', 'stock_controller'],
        ]],
        // ④ آخر مستند: الإفراج بعد الفحص — محتاج توقيعين
        'goods_receipt' => ['إذن استلام خام (الإفراج)', [
            ['اعتماد مراقب المخزون', 'stock_controller'],
            ['اعتماد التخطيط', 'planner'],
        ]],
        'fabric_inspection' => ['تقرير فحص قماش', [
            ['اعتماد الجودة', 'stock_controller'],
        ]],
        'lab_report' => ['تقرير معمل', [
            ['اعتماد التخطيط', 'planner'],
        ]],
        'marker' => ['ماركر', [
            ['اعتماد المخطط', 'planner'],
        ]],
        'work_order' => ['أمر شغل', [
            ['اعتماد المخطط', 'planner'],
            ['اعتماد المدير العام', 'gm'],
        ]],
        'cut_declaration' => ['بيان قص', [
            ['مراجعة متابعة المصانع', 'factory_follow'],
            ['اعتماد المخطط', 'planner'],
        ]],
        'material_issue' => ['إذن صرف خام', [
            ['اعتماد أمين المخزن', 'storekeeper'],
            ['مراجعة مراقب المخزون', 'stock_controller'],
        ]],
        'production_receipt' => ['استلام إنتاج', [
            ['اعتماد أمين المخزن', 'storekeeper'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::FLOWS as $docType => [$name, $steps]) {
            $flow = ApprovalFlow::updateOrCreate(['doc_type' => $docType], [
                'name' => $name, 'is_active' => true,
            ]);

            $flow->steps()->delete();

            foreach ($steps as $i => [$title, $roleKey]) {
                ApprovalFlowStep::create([
                    'approval_flow_id' => $flow->id,
                    'step_no'          => $i + 1,
                    'title'            => $title,
                    'role_id'          => Role::where('key', $roleKey)->value('id'),
                    'is_mandatory'     => true,
                ]);
            }
        }
    }
}
