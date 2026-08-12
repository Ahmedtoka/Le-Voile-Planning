<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FabricType extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function consignments() { return $this->hasMany(Consignment::class); }
    public function models()       { return $this->hasMany(ProductModel::class); }

    public function getLabelAttribute(): string
    {
        return $this->name;
    }

    /** هل البنشر داخل المواصفة؟ */
    public function gsmInSpec(?float $gsm): ?bool
    {
        if ($gsm === null) return null;
        if ($this->spec_gsm_min !== null && $gsm < (float) $this->spec_gsm_min) return false;
        if ($this->spec_gsm_max !== null && $gsm > (float) $this->spec_gsm_max) return false;
        return true;
    }

    public function widthInSpec(?float $width): ?bool
    {
        if ($width === null) return null;
        if ($this->spec_width_min_cm !== null && $width < (float) $this->spec_width_min_cm) return false;
        return true;
    }
}
