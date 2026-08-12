<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorMerge extends Model
{
    protected $guarded = [];
    public function fromColor() { return $this->belongsTo(Color::class, 'from_color_id'); }
    public function toColor()   { return $this->belongsTo(Color::class, 'to_color_id'); }
    public function user()      { return $this->belongsTo(User::class); }
}
