<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $guarded = [];

    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function color()        { return $this->belongsTo(Color::class); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }

    public function recalcAchievement(): void
    {
        $f = (float) $this->forecast_qty;
        $this->forceFill([
            'achievement_pct' => $f > 0 ? round(((float) $this->actual_qty / $f) * 100, 2) : null,
        ])->saveQuietly();
    }

    public function getMonthNameAttribute(): string
    {
        $names = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',
                  7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
        return $names[$this->month] ?? (string) $this->month;
    }
}
