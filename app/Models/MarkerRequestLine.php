<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** سطر في طلب الماركر: موديل + مقاس (من دروب داون مقاسات الموديل) */
class MarkerRequestLine extends Model
{
    protected $guarded = [];

    public function request()      { return $this->belongsTo(MarkerRequest::class, 'marker_request_id'); }
    public function productModel() { return $this->belongsTo(ProductModel::class); }
    public function size()         { return $this->belongsTo(Size::class); }
}
