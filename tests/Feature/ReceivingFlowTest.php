<?php

namespace Tests\Feature;

use App\Models\Consignment;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptRejection;
use App\Models\StockAddition;
use App\Models\StockMovement;

/**
 * الدورة من طلب الشراء لحد ما القماش يبقى متاح للتشغيل:
 * طلب ⇒ تسعير ⇒ إذن إضافة ⇒ فحص ⇒ معمل ⇒ إذن استلام خام.
 */
class ReceivingFlowTest extends FlowTestCase
{
    public function test_po_goes_to_purchasing_and_sourcing_converts_ton_to_kilo(): void
    {
        $po = $this->createPo();
        $this->assertSame('purchasing', $po->stage);
        $this->assertSame('طن', $po->lines->first()->unit);
        $this->assertEqualsWithDelta(2, (float) $po->lines->first()->qty, 0.0001);

        $po = $this->sourcePo($po, 'كيلو');
        $line = $po->lines->first();

        $this->assertSame('approved', $po->stage);
        $this->assertSame($this->supplier->id, $po->supplier_id);
        $this->assertSame('كيلو', $line->unit);
        $this->assertEqualsWithDelta(2000, (float) $line->qty, 0.0001, '2 طن لازم تبقى 2000 كيلو');
        $this->assertEqualsWithDelta(200000, (float) $line->line_total, 0.01);
    }

    public function test_stock_addition_creates_held_consignment_and_partial_then_full_receipt_closes_po(): void
    {
        $po = $this->sourcedPo();   // 2000 كيلو بزيادة 5% ⇒ يتقفل من 1900، أقصى 2100

        // وصول جزئي 1000
        $sa = $this->addStock($po, 1000);
        $c  = Consignment::findOrFail($sa->consignment_id);

        $this->assertSame('under_inspection', $c->status);
        $this->assertEqualsWithDelta(1000, (float) $c->total_kg, 0.001);
        $this->assertEqualsWithDelta(1000, (float) $c->hold_kg, 0.001);
        $this->assertEqualsWithDelta(0, (float) $c->remaining_kg, 0.001, 'المحجوز مش متاح للتشغيل');
        $this->assertSame(10, (int) $c->rolls_count);
        $this->assertSame(10, $c->rolls()->count());

        $po->refresh()->load('lines');
        $this->assertSame('receiving', $po->stage);
        $this->assertEqualsWithDelta(1000, (float) $po->lines->first()->received_qty, 0.001);

        $this->assertDatabaseHas('stock_movements', [
            'consignment_id' => $c->id, 'direction' => 'in', 'quality_state' => 'hold',
            'qty' => 1000, 'unit' => 'كجم', 'source_type' => StockAddition::class,
        ]);

        // الباقي 950 ⇒ 1950 ≥ 1900 ⇒ الطلب يتقفل
        $this->addStock($po, 950);
        $po->refresh();
        $this->assertSame('closed', $po->stage);
        $this->assertSame('received', $po->status);
        $this->assertSame(2, Consignment::where('purchase_order_id', $po->id)->count());
    }

    public function test_stock_addition_over_tolerance_is_rejected(): void
    {
        $po = $this->sourcedPo();   // أقصى 2100

        $this->postAddition($po, [['po_line' => $po->lines->first(), 'qty' => 2200]])
             ->assertSessionHasErrors('msg');

        $sa = StockAddition::latest('id')->firstOrFail();
        $this->assertSame('draft', $sa->status, 'الإذن بيفضل مسودة');
        $this->assertSame(0, Consignment::count());
        $this->assertEqualsWithDelta(0, (float) $po->refresh()->lines->first()->received_qty, 0.001);
    }

    public function test_one_consignment_per_fabric_and_color(): void
    {
        $po = $this->sourcePo($this->createPo([
            ['color_id' => $this->color->id,  'qty' => 1, 'unit' => 'طن', 'tolerance_pct' => 5],
            ['color_id' => $this->color2->id, 'qty' => 1, 'unit' => 'طن', 'tolerance_pct' => 5],
        ]));
        [$l1, $l2] = [$po->lines[0], $po->lines[1]];

        $this->postAddition($po, [
            ['po_line' => $l1, 'qty' => 1000, 'rolls' => 6],
            ['po_line' => $l2, 'qty' => 980,  'rolls' => 5],
        ])->assertSessionHasNoErrors();

        $cs = Consignment::orderBy('id')->get();
        $this->assertCount(2, $cs);
        $this->assertEquals([$this->color->id, $this->color2->id], $cs->pluck('color_id')->all());
        $this->assertEqualsWithDelta(1000, (float) $cs[0]->total_kg, 0.001);
        $this->assertEqualsWithDelta(980,  (float) $cs[1]->total_kg, 0.001);
        $this->assertTrue($cs->every(fn ($c) => $c->status === 'under_inspection'));
        $this->assertNotSame($cs[0]->consignment_no, $cs[1]->consignment_no);
        $this->assertSame('closed', $po->refresh()->stage);
    }

    public function test_inspection_and_lab_feed_the_consignment(): void
    {
        $sa = $this->addStock($this->sourcedPo(), 1950);
        $c  = Consignment::findOrFail($sa->consignment_id);

        // 10 أتواب في الإذن، الجرد 9، اتقاس منهم 3
        $this->inspect($c, [175, 172, 174], 9)->assertSessionHasNoErrors();

        $insp = $c->inspections()->firstOrFail();
        $this->assertSame('approved', $insp->status);
        $this->assertEqualsWithDelta(172, (float) $insp->min_width_cm, 0.001);
        $this->assertSame(-1, (int) $insp->rolls_variance);
        $this->assertEqualsWithDelta(33.33, (float) $insp->sample_pct, 0.01);

        $c->refresh();
        $this->assertEqualsWithDelta(172, (float) $c->min_width_cm, 0.001, 'أقل عرض مش المتوسط');
        $this->assertSame(9, (int) $c->rolls_count);
        $this->assertSame('inspected', $c->status);

        $this->lab($c, [192, 190, 194])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertEqualsWithDelta(192, (float) $c->avg_gsm, 0.001);
        $this->assertSame('lab_done', $c->status);
        $this->assertEqualsWithDelta(0, (float) $c->remaining_kg, 0.001, 'لسه محجوز لحد إذن الاستلام');
    }

    public function test_goods_receipt_with_partial_reject_releases_the_rest(): void
    {
        $sa = $this->addStock($this->sourcedPo(), 1950);
        $c  = Consignment::findOrFail($sa->consignment_id);
        $this->inspect($c)->assertSessionHasNoErrors();
        $this->lab($c->refresh())->assertSessionHasNoErrors();

        $this->postReceipt($c->refresh(), 1950, [
            ['kind' => 'rejected', 'qty' => 110, 'rolls_count' => 1, 'reason' => 'ثقوب في التوب'],
            ['kind' => 'rejected', 'qty' => '', 'reason' => ''],   // سطر فاضي بيتجاهل
        ])->assertSessionHasNoErrors();

        $gr = GoodsReceipt::firstOrFail();
        $this->assertSame('approved', $gr->status);

        $c->refresh();
        $this->assertSame('released', $c->status);
        $this->assertEqualsWithDelta(1840, (float) $c->released_kg, 0.001);
        $this->assertEqualsWithDelta(0, (float) $c->hold_kg, 0.001);
        $this->assertEqualsWithDelta(1840, (float) $c->remaining_kg, 0.001);

        $rej = GoodsReceiptRejection::where('goods_receipt_id', $gr->id)->get();
        $this->assertCount(1, $rej);
        $this->assertEqualsWithDelta(110, (float) $rej->first()->qty, 0.001);
        $this->assertSame('rejected', $rej->first()->kind);
        $this->assertSame($c->id, $rej->first()->consignment_id);

        $mv = StockMovement::where('source_type', GoodsReceipt::class)->where('source_id', $gr->id)->get();
        $this->assertCount(3, $mv);
        $this->assertTrue($mv->every(fn ($m) => $m->unit === 'كجم'));

        $outHold = $mv->first(fn ($m) => $m->direction === 'out' && $m->quality_state === 'hold');
        $inRel   = $mv->first(fn ($m) => $m->direction === 'in'  && $m->quality_state === 'released');
        $inRej   = $mv->first(fn ($m) => $m->direction === 'in'  && $m->quality_state === 'rejected');

        $this->assertEqualsWithDelta(1950, (float) $outHold->qty, 0.001, 'خروج المحجوز = السليم + المرفوض');
        $this->assertSame($this->warehouse->id, $outHold->warehouse_id);
        $this->assertEqualsWithDelta(1840, (float) $inRel->qty, 0.001);
        $this->assertSame($this->warehouse->id, $inRel->warehouse_id);
        $this->assertEqualsWithDelta(110, (float) $inRej->qty, 0.001);
        $this->assertSame($this->returns->id, $inRej->warehouse_id, 'المرفوض يروح مخزن RTN');

        // رصيد المحجوز في الحركات اتصفّر
        $hold = StockMovement::where('consignment_id', $c->id)->where('quality_state', 'hold')->get()
            ->sum(fn ($m) => $m->direction === 'in' ? (float) $m->qty : -(float) $m->qty);
        $this->assertEqualsWithDelta(0, $hold, 0.001);
    }

    public function test_goods_receipt_before_inspection_is_blocked(): void
    {
        $sa = $this->addStock($this->sourcedPo(), 1950);
        $c  = Consignment::findOrFail($sa->consignment_id);

        $this->postReceipt($c, 1950)->assertSessionHasErrors('msg');

        $this->assertSame('draft', GoodsReceipt::firstOrFail()->status);
        $c->refresh();
        $this->assertSame('under_inspection', $c->status);
        $this->assertEqualsWithDelta(0, (float) $c->remaining_kg, 0.001);
        $this->assertSame(0, StockMovement::where('source_type', GoodsReceipt::class)->count());
    }

    public function test_goods_receipt_after_inspection_but_before_lab_is_blocked(): void
    {
        $sa = $this->addStock($this->sourcedPo(), 1950);
        $c  = Consignment::findOrFail($sa->consignment_id);
        $this->inspect($c)->assertSessionHasNoErrors();

        $this->postReceipt($c->refresh(), 1950)->assertSessionHasErrors('msg');
        $this->assertSame('inspected', $c->refresh()->status);
    }

    public function test_container_receipt_is_released_immediately_and_lab_does_not_move_it_back(): void
    {
        $sa = $this->addStock($this->sourcedPo(), 1950, 10, ['receipt_type' => 'container']);
        $c  = Consignment::findOrFail($sa->consignment_id);

        $this->assertSame('released', $c->status);
        $this->assertEqualsWithDelta(1950, (float) $c->released_kg, 0.001);
        $this->assertEqualsWithDelta(1950, (float) $c->remaining_kg, 0.001);
        $this->assertEqualsWithDelta(0, (float) $c->hold_kg, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'consignment_id' => $c->id, 'direction' => 'in', 'quality_state' => 'released', 'qty' => 1950,
        ]);

        // فحص استدلالي ومعمل بعد الإفراج — بياخدوا القياسات بس
        $this->inspect($c)->assertSessionHasNoErrors();
        $this->lab($c->refresh())->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame('released', $c->status);
        $this->assertEqualsWithDelta(172, (float) $c->min_width_cm, 0.001);
        $this->assertEqualsWithDelta(192, (float) $c->avg_gsm, 0.001);
        $this->assertEqualsWithDelta(1950, (float) $c->remaining_kg, 0.001);
    }
}
