<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\FabricType;

class FabricTypeController extends BaseCrudController
{
    protected string $modelClass = FabricType::class;
    protected string $title      = 'الخامات';
    protected string $singular   = 'خامة';
    protected string $routeName  = 'fabric-types';
    protected string $permission = 'fabric_types';
    protected string $orderBy    = 'name';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',                 'label' => 'الكود',                    'type' => 'text',     'rules' => ['required','string','max:40','unique:fabric_types,code'], 'list' => true],
            ['name' => 'name',                 'label' => 'اسم الخامة',               'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'composition',          'label' => 'التركيب',                  'type' => 'text',     'rules' => ['nullable','string','max:191']],
            ['name' => 'spec_width_cm',        'label' => 'العرض المعياري (سم)',      'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0'], 'list' => true],
            ['name' => 'spec_width_min_cm',    'label' => 'أقل عرض مقبول (سم)',       'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0']],
            ['name' => 'spec_gsm',             'label' => 'البنشر المعياري (جم/م²)',  'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0'], 'list' => true],
            ['name' => 'spec_gsm_min',         'label' => 'أقل بنشر مقبول',           'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0'], 'list' => true],
            ['name' => 'spec_gsm_max',         'label' => 'أعلى بنشر مقبول',          'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0'], 'list' => true],
            ['name' => 'max_shrink_len_pct',   'label' => 'أقصى انكماش طول %',        'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0']],
            ['name' => 'max_shrink_width_pct', 'label' => 'أقصى انكماش عرض %',        'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0']],
            ['name' => 'max_defect_pct',       'label' => 'أقصى نسبة عيوب %',         'type' => 'number',   'step' => '0.01', 'rules' => ['nullable','numeric','min:0']],
            ['name' => 'is_active',            'label' => 'نشط',                      'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
        ];
    }
}
