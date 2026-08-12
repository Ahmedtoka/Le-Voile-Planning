<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\Accessory;

class AccessoryController extends BaseCrudController
{
    protected string $modelClass = Accessory::class;
    protected string $title      = 'الإكسسوارات';
    protected string $singular   = 'إكسسوار';
    protected string $routeName  = 'accessories';
    protected string $permission = 'accessories';
    protected string $orderBy    = 'name';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',          'label' => 'الكود',          'type' => 'text',     'rules' => ['required','string','max:40','unique:accessories,code'], 'list' => true],
            ['name' => 'name',          'label' => 'الاسم',          'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'type',          'label' => 'النوع',          'type' => 'select',   'options' => Accessory::TYPES, 'rules' => ['required'], 'list' => true, 'filter' => true],
            ['name' => 'unit',          'label' => 'الوحدة',         'type' => 'text',     'rules' => ['required','string','max:20'], 'list' => true],
            ['name' => 'stock_qty',     'label' => 'الرصيد',         'type' => 'number',   'step' => '0.001', 'rules' => ['nullable','numeric'], 'list' => true, 'default0' => true],
            ['name' => 'reorder_point', 'label' => 'حد إعادة الطلب', 'type' => 'number',   'step' => '0.001', 'rules' => ['nullable','numeric'], 'list' => true, 'default0' => true],
            ['name' => 'is_shared',     'label' => 'مشترك بين موديلات', 'type' => 'checkbox', 'rules' => ['boolean']],
            ['name' => 'is_active',     'label' => 'نشط',            'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
        ];
    }
}
