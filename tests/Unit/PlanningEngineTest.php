<?php

namespace Tests\Unit;

use App\Services\PlanningEngine;
use Tests\TestCase;

/**
 * اختبارات محرك الحسابات — أهمها الاختبار الإلزامي للماركر المختلط:
 * فرشة 3.10 × عرض 1.72 × بنشر 192 جم/م² × 12 قطعة = 1.023744 كجم للراق،
 * موديلين 60 جم و30 جم لازم ياخدوا استهلاك بنسبة 2:1 مع الحفاظ على
 * إجمالي وزن الراق بالظبط — مش رقم واحد متعمّم زي الشيت القديم.
 */
class PlanningEngineTest extends TestCase
{
    /** الفرشة بالوزن: راق = طول × عرض × بنشر · استهلاك = راق ÷ قطع */
    public function test_weight_fabric_matches_job_order_sheet(): void
    {
        $r = PlanningEngine::computeFabric([
            'calc_mode'         => 'weight',
            'spread_length_m'   => 3.10,
            'fabric_width_m'    => 1.72,
            'gsm_kg_m2'         => 0.192,
            'pieces_per_spread' => 12,
            'available'         => 152.9,
        ]);

        $this->assertTrue($r['ok']);
        $this->assertEqualsWithDelta(1.023744, $r['ply_weight_kg'], 0.0001);
        $this->assertEqualsWithDelta(0.085312, $r['consumption_per_piece'], 0.0001);
        $this->assertSame(149, $r['plies']);            // الرقات المنفذة عدد صحيح — مش 149.35
        $this->assertSame(1788, $r['expected_pieces']);
        $this->assertGreaterThan(0, $r['leftover']);    // المتبقي من التقريب بيظهر مش بيتساب
    }

    /** الفرشة بالطول: استهلاك = طول الفرشة ÷ قطع الفرشة */
    public function test_length_fabric(): void
    {
        $r = PlanningEngine::computeFabric([
            'calc_mode'         => 'length',
            'spread_length_m'   => 4.05,
            'pieces_per_spread' => 4,
            'available'         => 396,
        ]);

        $this->assertTrue($r['ok']);
        $this->assertEqualsWithDelta(1.0125, $r['consumption_per_piece'], 0.0001);
        $this->assertSame(97, $r['plies']);
        $this->assertSame(388, $r['expected_pieces']);
    }

    /** الاختبار الإلزامي: ماركر مختلط 6×60جم + 6×30جم ⇒ توزيع 2:1 والوزن محفوظ */
    public function test_mixed_marker_splits_by_baseline_ratio(): void
    {
        $actualPerPiece = 1.023744 / 12;   // 0.085312
        $plies = 149;

        $s = PlanningEngine::splitConsumption([
            ['product_model_id' => 1, 'label' => 'موديل 60جم', 'pieces_in_spread' => 6, 'avg_kg' => 0.060],
            ['product_model_id' => 2, 'label' => 'موديل 30جم', 'pieces_in_spread' => 6, 'avg_kg' => 0.030],
        ], $actualPerPiece, $plies);

        $this->assertTrue($s['ok']);
        [$a, $b] = $s['rows'];

        // مش نفس الاستهلاك — النسبة 2:1 بالظبط
        $this->assertNotEquals($a['per_piece'], $b['per_piece']);
        $this->assertEqualsWithDelta(2.0, $a['per_piece'] / $b['per_piece'], 0.001);

        // مجموع أوزان الموديلات داخل الراق = وزن الراق الفعلي
        $plyTotal = $a['per_piece'] * 6 + $b['per_piece'] * 6;
        $this->assertEqualsWithDelta(1.023744, $plyTotal, 0.001);

        // مجموع النسب = 100% (مرجّح بعدد القطع)
        $ratioSum = ($a['ratio'] * 6 + $b['ratio'] * 6) / 12;
        $this->assertEqualsWithDelta(1.0, $ratioSum, 0.001);

        // القطع والدسات المتوقعة لكل موديل
        $this->assertSame(149 * 6, $a['expected_pieces']);
        $this->assertEqualsWithDelta(74.5, $a['expected_dozens'], 0.01);

        // إجمالي الخامة الموزعة = إجمالي المستخدم (رقات × وزن الراق)
        $totalKg = $a['planned_kg'] + $b['planned_kg'];
        $this->assertEqualsWithDelta(149 * 1.023744, $totalKg, 0.5);
    }

    /** موديل من غير متوسط ⇒ تعميم بالتساوي + تحذير واضح — مش فشل صامت */
    public function test_missing_average_falls_back_to_uniform_with_warning(): void
    {
        $s = PlanningEngine::splitConsumption([
            ['product_model_id' => 1, 'label' => 'أ', 'pieces_in_spread' => 6, 'avg_kg' => 0.060],
            ['product_model_id' => 2, 'label' => 'ب', 'pieces_in_spread' => 6, 'avg_kg' => 0],
        ], 0.085312, 100);

        $this->assertTrue($s['ok']);
        $this->assertSame($s['rows'][0]['per_piece'], $s['rows'][1]['per_piece']);
        $this->assertNotEmpty($s['warnings']);
    }

    /** موديل بصفر قطع في الفرشة بيتشال من التوزيع */
    public function test_zero_pieces_model_is_excluded(): void
    {
        $s = PlanningEngine::splitConsumption([
            ['product_model_id' => 1, 'label' => 'أ', 'pieces_in_spread' => 12, 'avg_kg' => 0.060],
            ['product_model_id' => 2, 'label' => 'ب', 'pieces_in_spread' => 0,  'avg_kg' => 0.030],
        ], 0.085312, 100);

        $this->assertCount(1, $s['rows']);
        $this->assertSame(12, $s['total_pps']);
    }

    /** موديل واحد في الماركر: استهلاكه = الاستهلاك الفعلي زي ما هو */
    public function test_single_model_marker_keeps_actual_consumption(): void
    {
        $s = PlanningEngine::splitConsumption([
            ['product_model_id' => 1, 'label' => 'أ', 'pieces_in_spread' => 12, 'avg_kg' => 0.060],
        ], 0.085312, 149);

        $this->assertEqualsWithDelta(0.085312, $s['rows'][0]['per_piece'], 0.0001);
        $this->assertSame(1788, $s['rows'][0]['expected_pieces']);
    }

    /** الانحراف: 2% أخضر · لحد 4% أصفر · فوقها أحمر ولازم سبب */
    public function test_variance_flags(): void
    {
        $this->assertSame('ok',     PlanningEngine::variance(100, 101)['flag']);
        $this->assertSame('warn',   PlanningEngine::variance(100, 103)['flag']);
        $this->assertSame('danger', PlanningEngine::variance(100, 106)['flag']);
        $this->assertTrue(PlanningEngine::variance(100, 106)['needs_reason']);
    }

    /** زيادة طول الفرشة بتاكل رقات — أكبر مصدر فاقد صامت */
    public function test_spread_impact(): void
    {
        $r = PlanningEngine::spreadImpact(256, 3.70, 3.75, 16);
        $this->assertTrue($r['ok']);
        $this->assertGreaterThanOrEqual($r['actual_plies'], $r['planned_plies']);
        $this->assertSame($r['lost_plies'] * 16, $r['lost_pieces']);
    }

    /** بيانات ناقصة = رفض واضح مش نتيجة غلط */
    public function test_weight_mode_requires_width_and_gsm(): void
    {
        $r = PlanningEngine::computeFabric([
            'calc_mode'         => 'weight',
            'spread_length_m'   => 3.1,
            'pieces_per_spread' => 12,
            'available'         => 100,
        ]);

        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['errors']);
    }
}
