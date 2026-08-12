<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\Supplier;

class SupplierController extends BaseCrudController
{
    protected string $modelClass = Supplier::class;
    protected string $title      = 'الموردين';
    protected string $singular   = 'مورد';
    protected string $routeName  = 'suppliers';
    protected string $permission = 'suppliers';
    protected string $orderBy    = 'name';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',           'label' => 'كود المورد',     'type' => 'text',     'rules' => ['required','string','max:40','unique:suppliers,code'], 'list' => true],
            ['name' => 'name',           'label' => 'اسم المورد',      'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'contact_person', 'label' => 'الشخص المسؤول',  'type' => 'text',     'rules' => ['nullable','string','max:191'], 'list' => true],
            ['name' => 'phone',          'label' => 'رقم التليفون',    'type' => 'text',     'rules' => ['nullable','string','max:40'],  'list' => true],
            ['name' => 'address',        'label' => 'العنوان',         'type' => 'text',     'rules' => ['nullable','string','max:191']],
            ['name' => 'payment_terms',  'label' => 'طريقة الدفع',     'type' => 'text',     'rules' => ['nullable','string','max:191']],
            ['name' => 'is_active',      'label' => 'نشط',             'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
            ['name' => 'notes',          'label' => 'ملاحظات',         'type' => 'textarea', 'rules' => ['nullable','string']],
        ];
    }
}
