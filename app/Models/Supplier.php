<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    /** مورد مصري / مستورد من برّه / الاتنين — بيحدد شكل الاستلام المتوقع */
    public const TYPES = [
        'local'    => 'مصري',
        'importer' => 'مستورد',
        'both'     => 'مصري ومستورد',
    ];

    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->supplier_type] ?? 'مصري';
    }

    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
    public function consignments()   { return $this->hasMany(Consignment::class); }

    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
