<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialIssueLine extends Model
{
    protected $guarded = [];

    public function materialIssue()    { return $this->belongsTo(MaterialIssue::class); }
    public function workOrder()        { return $this->belongsTo(WorkOrder::class); }
    public function workOrderFabric()  { return $this->belongsTo(WorkOrderFabric::class); }
    public function consignment()      { return $this->belongsTo(Consignment::class); }
    public function fabricType()       { return $this->belongsTo(FabricType::class); }
    public function color()            { return $this->belongsTo(Color::class); }
}
