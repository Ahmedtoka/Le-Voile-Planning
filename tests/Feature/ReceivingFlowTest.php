<?php

namespace Tests\Feature;

use App\Models\Consignment;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptRejection;
use App\Models\FabricInspection;
use App\Models\StockAddition;
use App\Models\StockMovement;

/**
 * الدورة من طلب الشراء لحد ما القماش يبقى متاح للتشغيل:
 * طلب ⇒ تسعير ⇒ إذن إضافة ⇒ فحص ⇒ معمل ⇒ إذن استلام خام.
 */
class ReceivingFlowTest extends FlowTestCase
{
    public function test_po_goes_to_purchasing_and_sourcing_keeps_kilo(): void
    {
        $po = $this->createPo();
        $this->assertSame('purchasing', $po->stage);
        $this->assertSame('كجم', $po->lines->first()->unit);
        $this->assertEqualsWithDelta(2000, (float) $po->lines->first()->qty, 0.0001);

        $po = $this->sourcePo($po, 'كيلو');
        $line = $po->lines->first();

        $this->assertSame('approved', $po->stage);
        $this->assertSame($this->supplier->id, $po->supplier_id);
        $this->assertSame('كجم', $line->unit, 'التسعير ما بيغيّرش وحدة البند');
        $this->assertEqualsWithDelta(2000, (float) $line->qty, 0.0001, 'الكمية بتفضل بالكيلو زي ما اتطلبت');
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

        // نسبة الزيادة للزيادة بس — البند بيتقفل لما يوصل المطلوب كله: الباقي 1000 ⇒ 2000
        $this->addStock($po, 1000);
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
            ['color_id' => $this->color->id,  'qty' => 1000, 'unit' => 'كجم', 'tolerance_pct' => 5],
            ['color_id' => $this->color2->id, 'qty' => 1000, 'unit' => 'كجم', 'tolerance_pct' => 5],
        ]));
        [$l1, $l2] = [$po->lines[0], $po->lines[1]];

        $this->postAddition($po, [
            ['po_line' => $l1, 'qty' => 1000, 'rolls' => 6],
            ['po_line' => $l2, 'qty' => 980,  'rolls' => 5, 'eta' => now()->addDays(5)->toDateString()],
        ])->assertSessionHasNoErrors();

        $cs = Consignment::orderBy('id')->get();
        $this->assertCount(2, $cs);
        $this->assertEquals([$this->color->id, $this->color2->id], $cs->pluck('color_id')->all());
        $this->assertEqualsWithDelta(1000, (float) $cs[0]->total_kg, 0.001);
        $this->assertEqualsWithDelta(980,  (float) $cs[1]->total_kg, 0.001);
        $this->assertTrue($cs->every(fn ($c) => $c->status === 'under_inspection'));
        $this->assertNotSame($cs[0]->consignment_no, $cs[1]->consignment_no);

        // قفل البند اللي اكتمل مش الطلب كله (الميتينج 36): الأول اكتمل، التاني ناقص 20 كجم
        $po->refresh()->load('lines');
        $this->assertTrue((bool) $po->lines[0]->is_closed, 'البند اللي اكتمل بيتقفل لوحده');
        $this->assertFalse((bool) $po->lines[1]->is_closed, 'الناقص بيفضل مفتوح للاستلام');
        $this->assertSame('receiving', $po->stage, 'الطلب بيفضل في الاستلام لحد ما كل البنود تكتمل');
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
        // الاستلام المباشر بيتحدد من صاحب الطلب على طلب الشراء (الميتينج 36) — مش من المخزن
        $po = $this->sourcePo($this->createPo(null, ['inspection_exempt' => 1]));
        $sa = $this->addStock($po, 1950, 10);
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

    /** قرار 28 أكتوبر: التوب المرفوض بيتوزن قائم — الصافي يتخصم من الرسالة، والقائم من الطلب والمطالبة */
    public function test_rejected_roll_is_weighed_gross_and_net_is_deducted_from_stock(): void
    {
        $po   = $this->sourcedPo();
        $line = $po->lines->first();

        // 10 أتواب مفتوحة، كيس 100 جم + ماسورة 200 جم ⇒ 0.3 كجم للتوب — قائم 1003 ⇒ صافي 1000
        $this->postAddition($po, [['po_line' => $line, 'qty' => 1000, 'rolls' => 10]], [
            'lines' => [[
                'po_line_id' => $line->id, 'fabric_type_id' => $line->fabric_type_id,
                'color_id' => $line->color_id, 'po_color_id' => $line->color_id,
                'rolls_count' => 10, 'unit' => 'كجم',
                'fabric_form' => 'open', 'bag_g' => 100, 'tube_g' => 200, 'gross_qty' => 1003, 'qty' => 1000,
                'remainder_eta' => now()->addDays(5)->toDateString(),
            ]],
        ])->assertSessionHasNoErrors();

        $c = Consignment::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $c->total_kg, 0.001, 'الصافي هو الرصيد');
        $this->assertEqualsWithDelta(1003, (float) $c->gross_kg, 0.001);
        $this->assertEqualsWithDelta(3, (float) $c->packaging_kg, 0.001);
        $this->assertEqualsWithDelta(1003, (float) $line->refresh()->received_qty, 0.001, 'المستلم على الطلب بالقائم');

        // الفحص: 3 أتواب سليمة + توب مرفوض وزنه على الميزان (قائم) 20.3 كجم
        $this->as('ibrahim')->post(route('inspections.store'), [
            'doc_date'       => now()->toDateString(),
            'consignment_id' => $c->id,
            'declared_rolls' => 10,
            'counted_rolls'  => 10,
            'counted_kg'     => 1000,
            'result'         => 'accepted',
            'rolls'          => [
                ['length_m' => 50, 'width_cm' => 175, 'defects_count' => 0],
                ['length_m' => 50, 'width_cm' => 172, 'defects_count' => 0],
                ['length_m' => 50, 'width_cm' => 174, 'defects_count' => 0],
                ['roll_no' => '004', 'status' => 'rejected', 'net_kg' => 20.3, 'length_m' => 50, 'width_cm' => 170, 'defect_type' => 'بقع'],
            ],
        ])->assertSessionHasNoErrors();

        $insp = FabricInspection::latest('id')->firstOrFail();
        $this->assertSame(1, (int) $insp->rejected_rolls);
        $this->assertEqualsWithDelta(20.3, (float) $insp->rejected_gross_kg, 0.001, 'القائم زي ما اتكتب');
        $this->assertEqualsWithDelta(20.0, (float) $insp->rejected_kg, 0.001, 'الصافي = القائم − (كيس + ماسورة) للتوب');
        $this->assertEqualsWithDelta(0.3, $insp->rejected_packaging_kg, 0.001);

        // الرسالة: الصافي والقائم والخصم كلهم اتظبطوا
        $c->refresh();
        $this->assertSame(9, (int) $c->rolls_count);
        $this->assertEqualsWithDelta(980.0, (float) $c->total_kg, 0.001, 'الصافي اتخصم من الرصيد');
        $this->assertEqualsWithDelta(982.7, (float) $c->gross_kg, 0.001);
        $this->assertEqualsWithDelta(2.7, (float) $c->packaging_kg, 0.001);

        // طلب الشراء: القائم اتخصم من المستلم
        $this->assertEqualsWithDelta(982.7, (float) $line->refresh()->received_qty, 0.001);

        // المطالبة على المورد بالقائم، وحركة المخزون بالصافي
        $rej = GoodsReceiptRejection::where('fabric_inspection_id', $insp->id)->firstOrFail();
        $this->assertEqualsWithDelta(20.3, (float) $rej->qty, 0.001);
        $this->assertSame('كجم', $rej->unit);
        $this->assertSame(1, (int) $rej->rolls_count);

        $this->assertDatabaseHas('stock_movements', [
            'consignment_id' => $c->id, 'direction' => 'in', 'quality_state' => 'rejected',
            'qty' => 20.0, 'unit' => 'كجم', 'source_type' => FabricInspection::class, 'source_id' => $insp->id,
        ]);
    }

    /**
     * طلب متسكّن جزئي: ريب 2,500 وسنجل 5,000 — التسكينة الأولى 1,000 و4,000.
     * وصل الريب 1,000 كامل (على التسكينة) والسنجل 3,000 ⇒ الإذن يتحفظ من غير «إمتى»
     * للريب، و«استلم الباقي» يفتح السنجل بس بباقي 1,000.
     */
    public function test_partial_sourcing_remaining_is_counted_on_the_sourcing(): void
    {
        $po = $this->createPo([
            ['color_id' => $this->color->id,  'qty' => 2500, 'unit' => 'كجم', 'tolerance_pct' => 5],
            ['color_id' => $this->color2->id, 'qty' => 5000, 'unit' => 'كجم', 'tolerance_pct' => 5],
        ]);
        [$rib, $single] = [$po->lines[0], $po->lines[1]];

        $this->as('ahmed')->post(route('purchase-orders.sourcing', $po), [
            'supplier_id'    => $this->supplier->id,
            'warehouse_id'   => $this->warehouse->id,
            'delivery_date'  => now()->addDays(10)->toDateString(),
            'payment_method' => 'نقدي',
            'prices'         => [
                ['id' => $rib->id,    'qty' => 1000, 'unit_price' => 100, 'unit' => 'كيلو'],
                ['id' => $single->id, 'qty' => 4000, 'unit_price' => 100, 'unit' => 'كيلو'],
            ],
        ])->assertSessionHasNoErrors();
        $sourcing = $po->refresh()->sourcings()->with('lines')->firstOrFail();

        // الريب كامل على التسكينة (من غير «إمتى») والسنجل جزئي (بـ«إمتى»)
        $this->postAddition($po, [
            ['po_line' => $rib,    'qty' => 1000, 'rolls' => 20],
            ['po_line' => $single, 'qty' => 3000, 'rolls' => 10, 'eta' => now()->addDays(8)->toDateString()],
        ], ['po_sourcing_id' => $sourcing->id])->assertSessionHasNoErrors();

        $sa = StockAddition::latest('id')->firstOrFail();
        $this->assertSame('approved', $sa->status, 'الريب كمّل تسكينته — مفيش باقي عليه يطلب تاريخ');

        $sourcing->load('lines');
        $this->assertEqualsWithDelta(1000, (float) $sourcing->lines->firstWhere('purchase_order_line_id', $rib->id)->received_qty, 0.001);
        $this->assertEqualsWithDelta(3000, (float) $sourcing->lines->firstWhere('purchase_order_line_id', $single->id)->received_qty, 0.001);

        // «استلم الباقي»: السنجل بس، والباقي عليه 1,000 من التسكينة
        $preset = $this->as('hazem')->get(route('stock-additions.create', ['purchase_order_id' => $po->id]))
            ->assertOk()->viewData('preset');
        $this->assertCount(1, $preset, 'الريب خلّص تسكينته — مايظهرش تاني');
        $this->assertSame($single->id, $preset[0]['po_line_id']);
        $this->assertEqualsWithDelta(4000, $preset[0]['src_qty'], 0.001);
        $this->assertEqualsWithDelta(3000, $preset[0]['src_received'], 0.001);
    }
}
