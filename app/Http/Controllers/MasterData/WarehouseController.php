<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\Warehouse;

class WarehouseController extends BaseCrudController
{
    protected string $modelClass = Warehouse::class;
    protected string $title      = 'المخازن';
    protected string $singular   = 'مخزن';
    protected string $routeName  = 'warehouses';
    protected string $permission = 'warehouses';
    protected string $orderBy    = 'code';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',                'label' => 'كود المخزن',  'type' => 'text',     'rules' => ['required','string','max:40','unique:warehouses,code'], 'list' => true],
            ['name' => 'name',                'label' => 'اسم المخزن',  'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'type',                'label' => 'النوع',       'type' => 'select',   'options' => Warehouse::TYPES, 'rules' => ['required','in:fabric,accessories,finished,other'], 'list' => true, 'filter' => true],
            ['name' => 'location',            'label' => 'الموقع',      'type' => 'text',     'rules' => ['nullable','string','max:191']],
            ['name' => 'last_stock_count_at', 'label' => 'آخر جرد',     'type' => 'date',     'rules' => ['nullable','date'], 'list' => true],
            ['name' => 'is_active',           'label' => 'نشط',         'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
        ];
    }
}
