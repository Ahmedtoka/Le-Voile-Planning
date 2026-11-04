<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Consignment;
use App\Models\CutDeclaration;
use App\Models\Factory;
use App\Models\FabricType;
use App\Models\GoodsReceipt;
use App\Models\MaterialIssue;
use App\Models\ProductModel;
use App\Models\ProductionReceipt;
use App\Models\PurchaseOrder;
use App\Models\Size;
use App\Models\StockAddition;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * أساس اختبارات الدورة: بيانات أساسية قليلة + خطوة لكل مستند،
 * كل خطوة بتتعمل HTTP على الكنترولر الحقيقي بالمستخدم صاحب الدور.
 *
 * ⚠ القاعدة لازم تكون قاعدة الاختبار (lvplanning_test) — RefreshDatabase بيمسحها.
 */
abstract class FlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Warehouse $returns;
    protected Warehouse $finished;
    protected FabricType $fabric;
    protected Color $color;
    protected Color $color2;
    protected Factory $factory;
    protected ProductModel $model;
    protected Size $size;

    protected function setUp(): void
    {
        parent::setUp();

        $db = DB::connection()->getDatabaseName();
        if (! str_ends_with($db, '_test')) {
            $this->fail("الاختبارات لازم تشتغل على قاعدة اختبار (*_test) — القاعدة الحالية «{$db}».");
        }

        $this->seed([RolePermissionSeeder::class, UserSeeder::class]);

        $this->supplier  = Supplier::create(['code' => 'SL30', 'name' => 'مورد الاختبار']);
        $this->warehouse = Warehouse::create(['code' => '043', 'name' => 'مخزن القماش', 'type' => 'fabric']);
        $this->returns   = Warehouse::create(['code' => 'RTN', 'name' => 'مخزن المرتجعات', 'type' => 'other']);
        $this->finished  = Warehouse::create(['code' => 'FG', 'name' => 'مخزن التام', 'type' => 'finished']);
        $this->fabric    = FabricType::create(['code' => 'VFL', 'name' => 'فسكوز فل ليكرا',
                                               'spec_gsm_min' => 180, 'spec_gsm_max' => 200]);
        $this->color     = Color::create(['code' => 'BG3', 'name' => 'بيج 3']);
        $this->color2    = Color::create(['code' => 'BK1', 'name' => 'أسود']);
        $this->factory   = Factory::create(['code' => 'F1', 'name' => 'سيني']);
        $this->model     = ProductModel::create(['code' => 'BD1', 'name' => 'بادي كات',
                                                 'fabric_type_id' => $this->fabric->id, 'std_consumption_kg' => 0.085]);
        $this->size      = Size::create(['code' => 'M', 'name' => 'ميديم']);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    protected function as(string $username): static
    {
        return $this->actingAs($this->user($username));
    }

    // ── ① طلب الشراء ─────────────────────────────────────────────

    /** التخطيط بينشئ الطلب — بيروح للمشتريات على طول */
    protected function createPo(?array $lines = null, array $extra = []): PurchaseOrder
    {
        // الميتينج 37: الوحدة كيلو أو متر بس — الطن اتشال
        $lines ??= [['color_id' => $this->color->id, 'qty' => 2000, 'unit' => 'كجم', 'tolerance_pct' => 5]];

        $this->as('mostafa')->post(route('purchase-orders.store'), $extra + [
            'lines' => array_map(fn ($l) => $l + ['fabric_type_id' => $this->fabric->id], $lines),
        ])->assertSessionHasNoErrors()->assertRedirect(route('purchase-orders.index'));

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    /** المشتريات بتسعّر — ممكن بوحدة غير وحدة الطلب */
    protected function sourcePo(PurchaseOrder $po, string $unit = 'كيلو'): PurchaseOrder
    {
        $this->as('ahmed')->post(route('purchase-orders.sourcing', $po), [
            'supplier_id'    => $this->supplier->id,
            'warehouse_id'   => $this->warehouse->id,
            'delivery_date'  => now()->addDays(10)->toDateString(),
            'payment_method' => 'نقدي',
            // الميتينج 37: التسكين بكمية لكل بند — الهيلبر بيسكّن البند كله
            'prices'         => $po->lines->map(fn ($l) => [
                'id' => $l->id, 'qty' => $l->unsourced_qty, 'unit_price' => 100, 'unit' => $unit,
            ])->all(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('purchasing.queue'));

        return $po->refresh()->load('lines');
    }

    protected function sourcedPo(): PurchaseOrder
    {
        return $this->sourcePo($this->createPo());
    }

    // ── ② إذن الإضافة ────────────────────────────────────────────

    /**
     * أمين المخزن بيسجّل الوارد.
     * @param array $lines [[po_line, qty, rolls, color_id?], ...]
     */
    protected function postAddition(PurchaseOrder $po, array $lines, array $extra = [])
    {
        return $this->as('hazem')->post(route('stock-additions.store'), $extra + [
            'doc_date'          => now()->toDateString(),
            'paper_serial'      => 'SA-' . uniqid(),      // مسلسل الدفتر الورقي — إجباري
            'supplier_doc_no'   => 'SUP-' . uniqid(),     // رقم إذن المورد — إجباري
            'supplier_id'       => $this->supplier->id,
            'warehouse_id'      => $this->warehouse->id,
            'purchase_order_id' => $po->id,
            'lines'             => array_map(fn ($l) => [
                'po_line_id'     => $l['po_line']->id,
                'fabric_type_id' => $l['po_line']->fabric_type_id,
                'color_id'       => $l['color_id'] ?? $l['po_line']->color_id,
                'po_color_id'    => $l['po_line']->color_id,
                'rolls_count'    => $l['rolls'] ?? 10,
                'qty'            => $l['qty'],
                'unit'           => 'كجم',
                'remainder_eta'  => $l['eta'] ?? null,
            ], $lines),
        ]);
    }

    /** إذن إضافة لازم يعدّي — بيرجّع الإذن بعد ما خلص */
    protected function addStock(PurchaseOrder $po, float $kg, int $rolls = 10, array $extra = []): StockAddition
    {
        $this->postAddition($po, [['po_line' => $po->lines->first(), 'qty' => $kg, 'rolls' => $rolls,
                                   'eta' => now()->addDays(5)->toDateString()]], $extra)
             ->assertSessionHasNoErrors();

        $sa = StockAddition::latest('id')->firstOrFail();
        $this->assertSame('approved', $sa->status, 'إذن الإضافة المفروض يخلص من أول حفظ');

        return $sa;
    }

    // ── ③ الفحص والمعمل ──────────────────────────────────────────

    protected function inspect(Consignment $c, array $widths = [175, 172, 174], int $counted = 9)
    {
        return $this->as('ibrahim')->post(route('inspections.store'), [
            'doc_date'       => now()->toDateString(),
            'consignment_id' => $c->id,
            'declared_rolls' => $c->rolls_count,
            'counted_rolls'  => $counted,
            'counted_kg'     => $c->total_kg,
            'result'         => 'accepted',
            'rolls'          => array_map(fn ($w) => ['length_m' => 50, 'width_cm' => $w, 'defects_count' => 0], $widths),
        ]);
    }

    protected function lab(Consignment $c, array $gsm = [192, 190, 194])
    {
        return $this->as('nourhan')->post(route('lab-reports.store'), [
            'doc_date'            => now()->toDateString(),
            'consignment_id'      => $c->id,
            's1_shrink_len_pct'   => 3,
            's1_shrink_width_pct' => 2,
            'color_match_ok'      => 1,
            'readings'            => array_map(fn ($g) => ['gsm' => $g], $gsm),
        ]);
    }

    // ── ④ إذن الاستلام الخام ─────────────────────────────────────

    protected function postReceipt(Consignment $c, float $kg, array $rejects = [])
    {
        return $this->as('hazem')->post(route('goods-receipts.store'), [
            'doc_date'       => now()->toDateString(),
            'consignment_id' => $c->id,
            'lines'          => [[
                'fabric_type_id' => $c->fabric_type_id,
                'color_id'       => $c->color_id,
                'unit'           => 'كجم',
                'rolls_count'    => max(1, (int) $c->rolls_count),
                'qty'            => $kg,
            ]],
            'rejects'        => $rejects,
        ]);
    }

    /** الدورة كاملة لحد ما الرسالة تبقى متاحة للتشغيل */
    protected function releasedConsignment(float $kg = 1950, float $rejectKg = 0): Consignment
    {
        $po = $this->sourcedPo();
        $sa = $this->addStock($po, $kg);
        $c  = Consignment::findOrFail($sa->consignment_id);

        $this->inspect($c)->assertSessionHasNoErrors();
        $this->lab($c->refresh())->assertSessionHasNoErrors();

        $rejects = $rejectKg > 0
            ? [['kind' => 'rejected', 'qty' => $rejectKg, 'rolls_count' => 1, 'reason' => 'ثقوب']]
            : [];
        $this->postReceipt($c->refresh(), $kg, $rejects)->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertSame('released', $c->status);

        return $c;
    }

    // ── ⑤ أمر الشغل وما بعده ─────────────────────────────────────

    /**
     * طلب تحضير المخزن وزنه + ماركر معتمد — اللي أمر الشغل بيتبني عليهم (الميتينج 36).
     * الماركر: 3.10 م × 12 قطعة (موديل الاختبار، مقاس M) على عرض 165 سم (بيدخل في عرض التشغيل 167).
     */
    protected function readyRequest(Consignment $c, float $kg = 600): \App\Models\PreparationRequest
    {
        $marker = \App\Models\Marker::create([
            'code' => 'MK-T' . uniqid(), 'fabric_width_cm' => 165, 'spread_length_m' => 3.10, 'pieces_per_spread' => 12,
            'status' => 'approved', 'is_active' => true,
        ]);
        \App\Models\MarkerLine::create(['marker_id' => $marker->id, 'product_model_id' => $this->model->id, 'size_id' => $this->size->id, 'qty_per_spread' => 12]);
        $mr = \App\Models\MarkerRequest::create([
            'doc_no' => 'MR-T' . uniqid(), 'doc_date' => now()->toDateString(), 'consignment_id' => $c->id,
            'fabric_width_cm' => 165, 'status' => 'delivered', 'marker_id' => $marker->id,
        ]);
        $pr = \App\Models\PreparationRequest::create([
            'doc_no' => 'PQ-T' . uniqid(), 'doc_date' => now()->toDateString(), 'consignment_id' => $c->id,
            'requested_qty' => $kg, 'actual_qty' => $kg, 'status' => 'weighed', 'weighed_at' => now(),
            'marker_request_id' => $mr->id,
        ]);
        $mr->update(['preparation_request_id' => $pr->id]);
        $c->recalcRemaining();
        return $pr;
    }

    /** أمر شغل بالحسبة المرجعية: 3.10 × 1.72 (العرض الكامل) × 0.192 × 12 قطعة على 600 كجم */
    protected function createWorkOrder(Consignment $c, float $kg = 600, array $override = []): WorkOrder
    {
        $pr = $this->readyRequest($c, $kg);
        $this->as('mostafa')->post(route('work-orders.store'), [
            'wo_date'          => now()->toDateString(),
            'due_date'         => now()->addDays(15)->toDateString(),
            'factory_id'       => $this->factory->id,
            'fabrics'          => [array_merge([
                'preparation_request_id' => $pr->id,
                'marker_id'              => $pr->markerRequest->marker_id,
                'width_basis'            => 'full',
            ], $override)],
            'products'         => [[
                'product_model_id' => $this->model->id,
                'size_id'          => $this->size->id,
                'planned_qty'      => 0,
            ]],
        ])->assertSessionHasNoErrors();

        return WorkOrder::latest('id')->firstOrFail();
    }

    protected function submitWorkOrder(WorkOrder $wo): WorkOrder
    {
        $this->as('mostafa')->post(route('work-orders.submit', $wo))->assertSessionHasNoErrors();
        return $wo->refresh();
    }

    protected function issue(WorkOrder $wo, float $kg)
    {
        $f = $wo->fabrics()->firstOrFail();

        $this->as('hazem')->post(route('material-issues.store'), [
            'doc_date'     => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'factory_id'   => $this->factory->id,
            'issued_to'    => 'مصنع سيني',
            'lines'        => [[
                'work_order_id'        => $wo->id,
                'work_order_fabric_id' => $f->id,
                'consignment_id'       => $f->consignment_id,
                'unit'                 => 'كجم',
                'qty'                  => $kg,
            ]],
        ])->assertSessionHasNoErrors();

        $mi = MaterialIssue::latest('id')->firstOrFail();

        return $this->as('hazem')->post(route('material-issues.submit', $mi));
    }

    /** بيان قص — بيرجّع البيان بعد محاولة الإنهاء */
    protected function cut(WorkOrder $wo, int $pieces, ?string $reason = null): CutDeclaration
    {
        $this->as('karim')->post(route('cut-declarations.store'), [
            'doc_date'               => now()->toDateString(),
            'work_order_id'          => $wo->id,
            'factory_id'             => $this->factory->id,
            'actual_spread_length_m' => 3.10,
            'actual_plies'           => (int) ceil($pieces / 12),
            'variance_reason'        => $reason,
            'lines'                  => [[
                'product_model_id' => $this->model->id,
                'size_id'          => $this->size->id,
                'qty'              => $pieces,
            ]],
        ])->assertSessionHasNoErrors();

        $cd = CutDeclaration::latest('id')->firstOrFail();
        $this->as('karim')->post(route('cut-declarations.submit', $cd));

        return $cd->refresh();
    }

    protected function receiveProduction(WorkOrder $wo, int $good, int $rejected = 0): ProductionReceipt
    {
        $this->as('karim')->post(route('production-receipts.store'), [
            'doc_date'      => now()->toDateString(),
            'work_order_id' => $wo->id,
            'factory_id'    => $this->factory->id,
            'warehouse_id'  => $this->finished->id,
            'lines'         => [[
                'product_model_id' => $this->model->id,
                'size_id'          => $this->size->id,
                'color_id'         => $this->color->id,
                'qty'              => $good,
                'rejected_qty'     => $rejected,
            ]],
        ])->assertSessionHasNoErrors();

        $pr = ProductionReceipt::latest('id')->firstOrFail();
        $this->as('karim')->post(route('production-receipts.submit', $pr))->assertSessionHasNoErrors();

        return $pr->refresh();
    }
}
