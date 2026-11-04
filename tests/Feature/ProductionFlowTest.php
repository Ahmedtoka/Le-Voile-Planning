<?php

namespace Tests\Feature;

use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\ProductionReceipt;
use App\Models\StockMovement;
use App\Models\WorkOrder;

/**
 * الدورة من الرسالة المُفرَج عنها لحد قفل أمر الشغل:
 * أمر شغل ⇒ صرف خام ⇒ بيان قص ⇒ استلام إنتاج ⇒ قفل.
 *
 * الرسالة: 1950 وارد − 110 مرفوض = 1840 كجم متاح.
 */
class ProductionFlowTest extends FlowTestCase
{
    private Consignment $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = $this->releasedConsignment(1950, 110);
    }

    private function approvedWorkOrder(): WorkOrder
    {
        return $this->submitWorkOrder($this->createWorkOrder($this->c));
    }

    public function test_work_order_uses_planning_engine_numbers_and_allocates_the_consignment(): void
    {
        $wo = $this->createWorkOrder($this->c);
        $f  = $wo->fabrics()->firstOrFail();

        // هامش الأمان 5 سم بيتضاف على الفرشة لوحده (الميتينج 36): 3.10 + 0.05 = 3.15 م
        // راق = 3.15 × 1.72 × 0.192 = 1.040256 كجم ⇒ 600 ÷ 1.040256 = 576 راق × 12 = 6912 قطعة
        $this->assertEqualsWithDelta(3.15, (float) $f->spread_length_safe_m, 0.001);
        $this->assertEqualsWithDelta(1.0403, (float) $f->ply_weight_kg, 0.0001);
        $this->assertSame(576, (int) $f->plies);
        $this->assertSame(6912, (int) $f->expected_pieces);
        $this->assertEqualsWithDelta(0.08669, (float) $f->consumption_per_piece, 0.00001);
        $this->assertSame(6912, $wo->refresh()->target_qty);
        $this->assertSame(6912, (int) $wo->lines()->firstOrFail()->planned_qty);

        // المسودة ما بتحجزش
        $this->assertSame('draft', $wo->status);
        $this->assertEqualsWithDelta(0, (float) $this->c->refresh()->allocated_kg, 0.001);

        $wo = $this->submitWorkOrder($wo);
        $this->assertSame('approved', $wo->status);

        $c = $this->c->refresh();
        $this->assertSame('in_production', $c->status);
        $this->assertEqualsWithDelta(600, (float) $c->allocated_kg, 0.001);
        $this->assertEqualsWithDelta(1240, (float) $c->remaining_kg, 0.001);
    }

    public function test_work_order_over_remaining_is_blocked(): void
    {
        $wo = $this->createWorkOrder($this->c, 1900);   // المتاح 1840 بس

        $this->as('mostafa')->post(route('work-orders.submit', $wo))->assertSessionHasErrors('fabrics.0.planned_qty');   // على سطر الخامة نفسه
        $this->assertSame('draft', $wo->refresh()->status);
    }

    public function test_partial_issue_keeps_work_order_in_needs_material_even_after_cut(): void
    {
        $wo = $this->approvedWorkOrder();

        $this->issue($wo, 300)->assertSessionHasNoErrors();
        $wo->refresh();
        $this->assertSame('cutting', $wo->status);
        $this->assertEqualsWithDelta(300, (float) $wo->fabrics()->first()->issued_qty, 0.001);
        $this->assertTrue(WorkOrder::needsMaterial()->whereKey($wo->id)->exists());
        $this->assertDatabaseHas('stock_movements', [
            'consignment_id' => $this->c->id, 'direction' => 'out', 'quality_state' => 'released', 'qty' => 300,
        ]);

        $this->cut($wo, 3000);
        $wo->refresh();
        $this->assertSame('in_production', $wo->status);
        $this->assertTrue(WorkOrder::needsMaterial()->whereKey($wo->id)->exists(),
            'لسه فاضل 300 كجم تتصرف — الأمر لازم يفضل في طابور الصرف');

        // الصرف الباقي يطلّعه من الطابور
        $this->issue($wo, 300)->assertSessionHasNoErrors();
        $this->assertFalse(WorkOrder::needsMaterial()->whereKey($wo->id)->exists());
    }

    public function test_issue_over_released_is_blocked(): void
    {
        $wo = $this->approvedWorkOrder();

        $this->issue($wo, 1900)->assertSessionHasErrors('lines.0.qty');   // المفرج عنه 1840 — الغلط على حقل الكمية
        $this->assertEqualsWithDelta(0, (float) $wo->fabrics()->first()->issued_qty, 0.001);
    }

    public function test_partial_cut_below_target_is_not_danger(): void
    {
        $wo = $this->approvedWorkOrder();
        $this->issue($wo, 600)->assertSessionHasNoErrors();

        $cd = $this->cut($wo, 3000);   // −57% من المستهدف — بس قص على دفعات
        $this->assertSame('approved', $cd->status, 'القص الجزئي ما يحتاجش سبب');
        $this->assertNotSame('danger', $cd->variance_flag);

        $wo->refresh();
        $this->assertNotSame('danger', $wo->variance_flag);
        $this->assertSame(3000, (int) $wo->cut_pieces);
        $this->assertSame(3000, (int) $wo->lines()->first()->cut_qty);
    }

    public function test_cut_over_target_beyond_four_percent_requires_reason(): void
    {
        $wo = $this->approvedWorkOrder();
        $this->issue($wo, 600)->assertSessionHasNoErrors();

        // 7032 × 1.04 = 7313 ⇒ 7400 خارج الحدود
        $cd = $this->cut($wo, 7400);
        $this->assertSame('danger', $cd->variance_flag);
        $this->assertSame('draft', $cd->status, 'من غير سبب ما يتقبلش');
        $this->assertSame(0, (int) $wo->refresh()->cut_pieces);

        // بسبب مكتوب يعدّي
        $this->as('karim')->put(route('cut-declarations.update', $cd), [
            'doc_date'               => now()->toDateString(),
            'work_order_id'          => $wo->id,
            'actual_spread_length_m' => 3.10,
            'variance_reason'        => 'الفرشة طلعت أطول والمصنع زوّد رِقّات',
            'lines'                  => [['product_model_id' => $this->model->id,
                                          'size_id' => $this->size->id, 'qty' => 7400]],
        ])->assertSessionHasNoErrors();
        $this->as('karim')->post(route('cut-declarations.submit', $cd))->assertSessionHasNoErrors();

        $this->assertSame('approved', $cd->refresh()->status);
        $wo->refresh();
        $this->assertSame(7400, (int) $wo->cut_pieces);
        $this->assertSame('danger', $wo->variance_flag);
    }

    public function test_receipt_does_not_close_when_cut_below_target_then_closes_when_reached(): void
    {
        $wo = $this->approvedWorkOrder();
        $this->issue($wo, 590)->assertSessionHasNoErrors();

        // قص 3000 واستلام كله (2950 سليم + 50 مرفوض) — القص لسه تحت 7032 × 0.96 = 6750
        $this->cut($wo, 3000);
        $this->receiveProduction($wo, 2950, 50);

        $wo->refresh();
        $line = $wo->lines()->first();
        $this->assertSame(2950, (int) $line->received_qty, 'المرفوض من المصنع مش بيتحسب ضمن المستلم (قرار 28 أكتوبر)');
        $this->assertSame(50, (int) $line->rejected_qty);
        $this->assertSame(50, (int) $line->remaining_qty, 'المرفوض بيفضل على المصنع لحد ما يتعوّض');
        $this->assertSame(50, $wo->outstanding_pieces);
        $this->assertSame(50, (int) $wo->rejected_pieces);
        $this->assertSame('partially_received', $wo->status, 'القص تحت المستهدف — الأمر ما يتقفلش');

        $pr = ProductionReceipt::latest('id')->first();
        $mv = StockMovement::where('source_type', ProductionReceipt::class)->where('source_id', $pr->id)->get();
        $this->assertCount(1, $mv);
        $this->assertSame('finished', $mv->first()->item_type);
        $this->assertEqualsWithDelta(2950, (float) $mv->first()->qty, 0.001, 'المرفوض مش بيدخل مخزون التام');

        // وهو مفتوح: الحجز على الرسالة = المخطط
        $this->assertEqualsWithDelta(600, (float) $this->c->refresh()->allocated_kg, 0.001);

        // باقي القص: 3000 + 3800 = 6800 ≥ 6750 — لكن لسه 50 مرفوضين على المصنع ⇒ ما يتقفلش
        $this->cut($wo, 3800);
        $this->receiveProduction($wo, 3800);

        $wo->refresh();
        $this->assertSame(6800, (int) $wo->cut_pieces);
        $this->assertSame(6750, (int) $wo->received_pieces);
        $this->assertSame(50, $wo->outstanding_pieces);
        $this->assertSame('partially_received', $wo->status, 'المرفوض لسه ما اتعوّضش');

        // المصنع عوّض الـ50 المرفوضين سليمين ⇒ السليم = المقصوص ⇒ القفل
        $this->receiveProduction($wo, 50);

        $wo->refresh();
        $this->assertSame(6800, (int) $wo->received_pieces);
        $this->assertSame(0, $wo->outstanding_pieces);
        $this->assertSame('closed', $wo->status);

        // بعد القفل: الحجز = المنصرف فعلًا (590) مش المخطط (600)
        $c = $this->c->refresh();
        $this->assertEqualsWithDelta(590, (float) $c->allocated_kg, 0.001);
        $this->assertEqualsWithDelta(1840 - 590, (float) $c->remaining_kg, 0.001);
    }

    public function test_late_cut_declaration_does_not_reopen_closed_work_order(): void
    {
        $wo = $this->approvedWorkOrder();
        $this->issue($wo, 600)->assertSessionHasNoErrors();
        $this->cut($wo, 7000);
        $this->receiveProduction($wo, 7000);
        $this->assertSame('closed', $wo->refresh()->status);

        $this->cut($wo, 10);
        $this->assertSame('closed', $wo->refresh()->status);
    }

    /** أمر الشغل مبني على طلب تحضير اتوزن + ماركر — الأرقام من السيرفر، والحجز ما بيتفكّش وهو مسودة */
    public function test_work_order_is_built_from_weighed_request_and_marker_only(): void
    {
        // طلب لسه ما اتوزنش ⇒ مينفعش يتبني عليه أمر
        $pr = $this->readyRequest($this->c, 500);
        $pr->update(['status' => 'open', 'actual_qty' => null]);
        $this->as('mostafa')->post(route('work-orders.store'), [
            'wo_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(), 'factory_id' => $this->factory->id,
            'fabrics' => [['preparation_request_id' => $pr->id, 'marker_id' => $pr->markerRequest->marker_id]],
        ])->assertSessionHasErrors('fabrics.0.preparation_request_id');
        $pr->update(['status' => 'cancelled']);

        // أرقام مكتوبة باليد في الفورم بتتجاهل — الكمية = الوزن الفعلي، الطول والقطع = الماركر
        $wo = $this->createWorkOrder($this->c, 600, ['planned_qty' => 9999, 'spread_length_m' => 1, 'pieces_per_spread' => 99]);
        $f = $wo->fabrics()->firstOrFail();
        $this->assertEqualsWithDelta(600, (float) $f->planned_qty, 0.001);
        $this->assertEqualsWithDelta(3.10, (float) $f->spread_length_m, 0.001);
        $this->assertSame(12, (int) $f->pieces_per_spread);
        $this->assertNotNull($f->preparation_request_id);
        $this->assertNotNull($f->marker_id);

        // اسم المنتج من الموديلات — مفيش خانة تتكتب باليد
        $this->assertSame($this->model->name, $wo->product_title);

        // وهو مسودة: طلب التحضير لسه حاجز الـ 600 (ما اتفكّش)، ومش متخصص للأمر لسه
        $this->assertSame('weighed', $f->preparationRequest->status);
        $this->assertEqualsWithDelta(1240, (float) $this->c->refresh()->remaining_kg, 0.001);

        // بعد الاعتماد: الحجز اتحول لتخصيص الأمر — من غير ما يتحسب مرتين
        $this->submitWorkOrder($wo);
        $this->assertSame('used', $f->preparationRequest->refresh()->status);
        $this->assertEqualsWithDelta(1240, (float) $this->c->refresh()->remaining_kg, 0.001);
        $this->assertEqualsWithDelta(600, (float) $this->c->allocated_kg, 0.001);
    }

    /** شاشة البداية: الطلب الجاهز بيظهر، واختياره بيملا الأمر والموديلات من الماركر */
    public function test_create_starts_from_ready_requests_and_prefills_from_marker(): void
    {
        $pr = $this->readyRequest($this->c, 400);

        $this->as('mostafa')->get(route('work-orders.create'))->assertOk()
            ->assertViewIs('workorders.pick')->assertSee($pr->doc_no)->assertSee('افتح أمر شغل');

        $page = $this->as('mostafa')->get(route('work-orders.create', ['preparation_request_id' => $pr->id]))->assertOk()
            ->assertViewIs('workorders.form');
        $this->assertSame($this->model->id, $page->viewData('products')[0]['product_model_id']);
        $this->assertSame($this->size->id, $page->viewData('products')[0]['size_id']);
        $this->assertEqualsWithDelta(400, $page->viewData('fabrics')[0]['planned_qty'], 0.001);
    }
}
