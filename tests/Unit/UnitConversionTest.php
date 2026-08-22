<?php

namespace Tests\Unit;

use App\Services\DocumentEffects;
use Tests\TestCase;

/**
 * تحويل الوحدات بين الطلب والاستلام:
 * الطلب ممكن يكون بالطن والإذن بالكيلو — التحويل بيمر على الكيلو دايمًا.
 */
class UnitConversionTest extends TestCase
{
    public function test_ton_to_kg(): void
    {
        $this->assertSame(1500.0, DocumentEffects::toUnit(1.5, 'طن', 'كجم'));
    }

    public function test_kg_to_ton(): void
    {
        $this->assertSame(1.5, DocumentEffects::toUnit(1500, 'كجم', 'طن'));
    }

    public function test_same_unit_passthrough(): void
    {
        $this->assertSame(396.0, DocumentEffects::toUnit(396, 'متر', 'متر'));
        $this->assertSame(50.0, DocumentEffects::toUnit(50, 'كجم', 'كجم'));
    }

    public function test_cumulative_receipt_respects_tolerance_math(): void
    {
        // طالب 50 طن بزيادة 5% ⇒ أقصى استلام بالكيلو 52,500
        $ordered = DocumentEffects::toUnit(50, 'طن', 'كجم');
        $max     = $ordered * 1.05;

        $this->assertSame(50000.0, $ordered);
        $this->assertSame(52500.0, $max);

        // استلم 30 طن ثم 22.4 طن ⇒ 52,400 جوه الحد · 22.6 ⇒ 52,600 بره الحد
        $this->assertLessThanOrEqual($max, DocumentEffects::toUnit(30, 'طن', 'كجم') + DocumentEffects::toUnit(22.4, 'طن', 'كجم'));
        $this->assertGreaterThan($max, DocumentEffects::toUnit(30, 'طن', 'كجم') + DocumentEffects::toUnit(22.6, 'طن', 'كجم'));
    }
}
