<?php

namespace App\Models;

use App\Support\HasDocumentIdentity;
use App\Support\HasComments;
use App\Support\HasDocumentStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasDocumentIdentity, HasDocumentStatus, HasComments;

    protected $guarded = [];
    protected $casts = [
        'po_date'       => 'date',
        'delivery_date' => 'date',
        'remainder_eta' => 'date',
        'requested_at'  => 'datetime',
        'sourced_at'    => 'datetime',
        'finance_at'    => 'datetime',
    ];

    public const DOC_TYPE = 'purchase_order';

    public const STATUSES = [
        'draft'              => 'مسودة',
        'pending'            => 'قديم — اتنقل',   // داتا قديمة قبل شيل الاعتمادات
        'approved'           => 'معتمد',
        'rejected'           => 'مرفوض',
        'partially_received' => 'مستلم جزئيًا',
        'received'           => 'مستلم بالكامل',
        'closed'             => 'مقفول',
        'cancelled'          => 'ملغي',
    ];

    /**
     * مراحل الطلب — نفس الورقة، بس بتمر على تلات أيادي.
     * كل مرحلة بتقفل اللي قبلها عن التعديل.
     */
    public const STAGES = [
        'planning'   => 'عند التخطيط',
        'purchasing' => 'عند المشتريات',
        'finance'    => 'عند الحسابات',       // للتوافق مع داتا قديمة
        'approval'   => 'قديم — اتنقل',      // للتوافق مع داتا قديمة
        'approved'   => 'اتسعّر — مستني الاستلام',
        'receiving'  => 'جاري الاستلام',
        'closed'     => 'مقفول',
        'cancelled'  => 'ملغي',
    ];

    /** طرق الدفع المتفق عليها — دروب داون مش نص حر */
    public const PAYMENT_METHODS = [
        'نقدي'       => 'نقدي',
        'آجل 30 يوم' => 'آجل 30 يوم',
        'آجل 45 يوم' => 'آجل 45 يوم',
        'آجل 60 يوم' => 'آجل 60 يوم',
        'آجل 90 يوم' => 'آجل 90 يوم',
        'دفعات'      => 'دفعات حسب الاتفاق',
        'شيك'        => 'شيك',
        'تحويل بنكي' => 'تحويل بنكي',
    ];

    public const STAGE_COLORS = [
        'planning'   => 'secondary',
        'purchasing' => 'info',
        'finance'    => 'warning',
        'approval'   => 'warning',
        'approved'   => 'success',
        'receiving'  => 'primary',
        'closed'     => 'dark',
        'cancelled'  => 'danger',
    ];

    public function getStageNameAttribute(): string
    {
        return self::STAGES[$this->stage] ?? (string) $this->stage;
    }

    public function getStageColorAttribute(): string
    {
        return self::STAGE_COLORS[$this->stage] ?? 'secondary';
    }

    /** المورد والأسعار والتوريد — المشتريات: طول ما فيه كمية لسه ما اتسكّنتش */
    public function purchasingEditable(): bool
    {
        return $this->stage === 'purchasing'
            || (in_array($this->stage, ['approved', 'receiving'], true) && $this->hasUnsourcedQty());
    }

    /** اتسعّر ونزل للحسابات ولسه ما سجّلتش علمها بيه */
    public function needsFinanceAck(): bool
    {
        return (bool) $this->sourced_at && ! $this->finance_at;
    }

    /** المستحق المتوقع للمورد من الطلب ده */
    public function getExpectedPayableAttribute(): float
    {
        return (float) $this->total;
    }

    public function supplier()  { return $this->belongsTo(Supplier::class); }
    /** @deprecated الطلب بقى لأكتر من موديل — استخدم models() */
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    /** الموديلات اللي الخامة دي ليها (الميتينج: 6-7 موديلات بيستخدموا نفس الخامة بيتطلبوا مرة واحدة) */
    public function models() { return $this->belongsToMany(ProductModel::class, 'purchase_order_models'); }

    public function getModelsLabelAttribute(): string
    {
        $names = $this->models->pluck('name')->all();
        if (!$names && $this->productModel) $names = [$this->productModel->name];
        return implode('، ', $names);
    }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function employee()  { return $this->belongsTo(User::class, 'employee_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function sourcer()   { return $this->belongsTo(User::class, 'sourced_by'); }
    public function financer()  { return $this->belongsTo(User::class, 'finance_by'); }
    public function stockAdditions() { return $this->hasMany(StockAddition::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function lines()     { return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no'); }
    /** التسكينات: الطلب ممكن يتسعّر على أكتر من مورد وأكتر من مرة (الميتينج 37) */
    public function sourcings() { return $this->hasMany(PoSourcing::class)->orderBy('seq'); }

    /** لسه فيه كمية ما اتسكّنتش مع مورد؟ */
    public function hasUnsourcedQty(): bool
    {
        $this->loadMissing('lines');
        return $this->lines->contains(fn ($l) => (float) $l->qty - (float) $l->sourced_qty > 0.0005);
    }

    /** الكمية المسكّنة إجمالي (بوحدة كل بند) */
    public function getSourcedQtyAttribute(): float
    {
        $this->loadMissing('lines');
        return (float) $this->lines->sum('sourced_qty');
    }
    public function receipts()  { return $this->hasMany(GoodsReceipt::class); }
    public function consignments() { return $this->hasMany(Consignment::class); }

    /**
     * تاريخ الاستلام لكل سطر: كل دفعة وصلت (التاريخ، الكمية، رقم الإذن، إذن المورد).
     * من سطور أذون الإضافة المعتمدة — بالربط بسطر الطلب، أو بالصنف واللون للأذون القديمة.
     *
     * @return array<int, array<int, array{date:string,qty:float,unit:string,doc_no:string,sa_id:int,supplier_doc_no:?string,rolls:int,eta:?string}>>
     */
    public function deliveryHistory(): array
    {
        $this->loadMissing('lines');
        $lines = StockAdditionLine::with('stockAddition')
            ->whereHas('stockAddition', fn ($q) => $q->where('purchase_order_id', $this->id)->where('status', 'approved'))
            ->whereNotNull('fabric_type_id')
            ->where(fn ($q) => $q->whereNull('color_action')->orWhere('color_action', '!=', 'new_po'))
            ->get()
            ->sortBy(fn ($l) => [$l->stockAddition->doc_date?->format('Y-m-d'), $l->stock_addition_id]);

        $out = [];
        foreach ($lines as $l) {
            $sa = $l->stockAddition;
            $matchColor = $l->color_action === 'substitute' && $l->po_color_id ? $l->po_color_id : $l->color_id;
            $poLine = $l->po_line_id
                ? $this->lines->firstWhere('id', $l->po_line_id)
                : $this->lines->first(fn ($x) => $x->fabric_type_id == $l->fabric_type_id && $x->color_id == $matchColor);
            if (!$poLine) continue;

            $out[$poLine->id][] = [
                'date'            => $sa->doc_date?->format('Y-m-d') ?? '',
                'qty'             => \App\Services\DocumentEffects::toUnit((float) ($l->gross_qty ?: $l->qty), $l->unit, $poLine->unit),
                'net'             => \App\Services\DocumentEffects::toUnit($l->qty, $l->unit, $poLine->unit),
                'unit'            => $poLine->unit,
                'doc_no'          => $sa->doc_no,
                'sa_id'           => $sa->id,
                'supplier_doc_no' => $sa->supplier_doc_no,
                'rolls'           => (int) $l->rolls_count,
                'eta'             => $l->remainder_eta?->format('Y-m-d'),
            ];
        }
        return $out;
    }

    public function recalcTotals(): void
    {
        $this->loadMissing('lines');

        /* التسكينات موجودة؟ الإجماليات مجموعها — كل تسكينة بخصمها وضريبتها (الميتينج 37) */
        $src = $this->sourcings()->get();
        if ($src->isNotEmpty()) {
            $sub = (float) $src->sum('subtotal'); $tot = (float) $src->sum('total');
            $disc = (float) $src->sum(fn ($s) => round((float) $s->subtotal * (float) $s->discount_pct / 100, 2));
            $this->forceFill([
                'subtotal' => $sub, 'discount_value' => $disc, 'tax_value' => $tot - $sub + $disc,
                'total' => $tot, 'total_qty' => (float) $this->lines->sum('qty'),
            ])->saveQuietly();
            return;
        }

        $subtotal = 0; $qty = 0;
        foreach ($this->lines as $l) {
            $subtotal += (float) $l->line_total;
            $qty      += (float) $l->qty;
        }
        $discount = round($subtotal * ((float) $this->discount_pct / 100), 2);
        $afterDisc = $subtotal - $discount;
        $tax = round($afterDisc * ((float) $this->tax_pct / 100), 2);

        $this->forceFill([
            'subtotal'       => $subtotal,
            'discount_value' => $discount,
            'tax_value'      => $tax,
            'total'          => $afterDisc + $tax,
            'total_qty'      => $qty,
        ])->saveQuietly();
    }
}
