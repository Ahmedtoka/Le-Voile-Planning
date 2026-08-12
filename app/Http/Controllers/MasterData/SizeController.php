<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\Size;

class SizeController extends BaseCrudController
{
    protected string $modelClass = Size::class;
    protected string $title      = 'المقاسات';
    protected string $singular   = 'مقاس';
    protected string $routeName  = 'sizes';
    protected string $permission = 'sizes';
    protected string $orderBy    = 'sort_order';
    protected string $orderDir   = 'asc';

    protected function fields(): array
    {
        return [
            ['name' => 'code',       'label' => 'الكود',   'type' => 'text',     'rules' => ['required','string','max:20','unique:sizes,code'], 'list' => true],
            ['name' => 'name',       'label' => 'المقاس',  'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'sort_order', 'label' => 'الترتيب', 'type' => 'number',   'rules' => ['nullable','integer','min:0'], 'list' => true, 'default0' => true],
            ['name' => 'is_active',  'label' => 'نشط',     'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
        ];
    }
}
