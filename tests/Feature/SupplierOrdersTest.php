<?php

namespace Tests\Feature;

use App\Models\PoSourcing;
use App\Models\Supplier;

/**
 * طلب شراء واحد اتشرى من موردين = أمرين شراء (تسكينتين):
 * كل مورد بأسعاره وإجماليه وورقته وعلم الحسابات بتاعه.
 */
class SupplierOrdersTest extends FlowTestCase
{
    private function source(\App\Models\PurchaseOrder $po, int $supplierId, array $prices)
    {
        return $this->as('ahmed')->post(route('purchase-orders.sourcing', $po), [
            'supplier_id'    => $supplierId,
            'warehouse_id'   => $this->warehouse->id,
            'delivery_date'  => now()->addDays(10)->toDateString(),
            'payment_method' => 'نقدي',
            'tax_pct'        => 14,
            'prices'         => $prices,
        ]);
    }

    public function test_one_request_from_two_suppliers_is_two_supplier_orders(): void
    {
        $other = Supplier::create(['code' => 'SN2', 'name' => 'الصنع تكس']);
        $po    = $this->createPo([['color_id' => $this->color->id, 'qty' => 1000, 'unit' => 'كجم', 'tolerance_pct' => 5]]);
        $line  = $po->lines->first();

        // 500 من المورد الأول بـ 90، والـ 500 الباقيين من التاني بـ 100
        $this->source($po, $this->supplier->id, [['id' => $line->id, 'qty' => 500, 'unit_price' => 90]])->assertSessionHasNoErrors();
        $this->source($po->refresh(), $other->id, [['id' => $line->id, 'qty' => 500, 'unit_price' => 100]])->assertSessionHasNoErrors();

        [$o1, $o2] = PoSourcing::where('purchase_order_id', $po->id)->orderBy('seq')->get()->all();
        $this->assertEqualsWithDelta(45000 * 1.14, (float) $o1->total, 0.01);
        $this->assertEqualsWithDelta(50000 * 1.14, (float) $o2->total, 0.01);

        // سعر سطر الطلب = متوسط مرجّح (95)، مش آخر سعر (100)
        $line->refresh();
        $this->assertEqualsWithDelta(95, (float) $line->unit_price, 0.001);
        $this->assertEqualsWithDelta(95000, (float) $line->line_total, 0.01);

        // الحسابات: صفين، كل مورد بإجماليه — و«حسب المورد» بيجمع كل واحد لوحده
        $page = $this->as('dina')->get(route('finance.payables'))->assertOk();
        $page->assertSee($o1->label)->assertSee($o2->label)->assertSee('الصنع تكس');
        $by = $page->viewData('bySupplier')->keyBy('supplier_id');
        $this->assertEqualsWithDelta(51300, (float) $by[$this->supplier->id]->total, 0.01);
        $this->assertEqualsWithDelta(57000, (float) $by[$other->id]->total, 0.01);

        // العلم بأمر واحد ⇒ الطلب لسه «ما اتشافش» لحد ما التاني يتعلم
        $this->as('dina')->post(route('finance.supplier-order.ack', $o1))->assertRedirect(route('finance.supplier-order', $o2));
        $this->assertNull($po->refresh()->finance_at);
        $this->as('dina')->post(route('finance.supplier-order.ack', $o2))->assertRedirect(route('finance.payables'));
        $this->assertNotNull($po->refresh()->finance_at);

        // الطباعة: كل مورد بورقته وأسعاره
        $this->as('ahmed')->get(route('purchase-orders.print', [$po, 'order' => 2]))
            ->assertOk()->assertSee('الصنع تكس')->assertSee($o2->label)->assertSee('100.00')->assertDontSee('مورد الاختبار');

        // تقرير الأسعار: الخامة من الموردين الاتنين، والأرخص متعلّم
        $this->as('ahmed')->get(route('reports.purchase-prices'))->assertOk()
            ->assertSee('الصنع تكس')->assertSee('مورد الاختبار')->assertSee('الأرخص');
    }
}
