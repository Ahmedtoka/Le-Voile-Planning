<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\Factory;

class FactoryController extends BaseCrudController
{
    protected string $modelClass = Factory::class;
    protected string $title      = 'المصانع';
    protected string $singular   = 'مصنع';
    protected string $routeName  = 'factories';
    protected string $permission = 'factories';
    protected string $orderBy    = 'name';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',              'label' => 'الكود',                 'type' => 'text',     'rules' => ['required','string','max:40','unique:factories,code'], 'list' => true],
            ['name' => 'name',              'label' => 'اسم المصنع',            'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'contact_person',    'label' => 'الشخص المسؤول',        'type' => 'text',     'rules' => ['nullable','string','max:191'], 'list' => true],
            ['name' => 'phone',             'label' => 'التليفون',              'type' => 'text',     'rules' => ['nullable','string','max:40'],  'list' => true],
            ['name' => 'address',           'label' => 'العنوان',               'type' => 'text',     'rules' => ['nullable','string','max:191']],
            ['name' => 'daily_capacity_pcs','label' => 'الطاقة اليومية (قطعة)', 'type' => 'number',   'rules' => ['nullable','integer','min:0'], 'list' => true],
            ['name' => 'avg_cycle_days',    'label' => 'متوسط دورة التشغيل (يوم)','type' => 'number', 'rules' => ['nullable','integer','min:0'], 'list' => true],
            ['name' => 'is_active',         'label' => 'نشط',                   'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
            ['name' => 'notes',             'label' => 'ملاحظات',               'type' => 'textarea', 'rules' => ['nullable','string']],
        ];
    }
}
