<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\BaseCrudController;
use App\Models\FabricType;
use App\Models\ProductModel;
use App\Models\Size;
use Illuminate\Http\Request;

class ProductModelController extends BaseCrudController
{
    protected string $modelClass = ProductModel::class;
    protected string $title      = 'الموديلات';
    protected string $singular   = 'موديل';
    protected string $routeName  = 'product-models';
    protected string $permission = 'product_models';
    protected string $orderBy    = 'name';
    protected string $orderDir   = 'asc';
    protected array $with        = ['fabricType'];

    protected function fields(): array
    {
        return [
            ['name' => 'code',              'label' => 'الكود',            'type' => 'text',     'rules' => ['required','string','max:40','unique:product_models,code'], 'list' => true],
            ['name' => 'name',              'label' => 'اسم الموديل',      'type' => 'text',     'rules' => ['required','string','max:191'], 'list' => true],
            ['name' => 'category',          'label' => 'الفئة',            'type' => 'text',     'rules' => ['nullable','string','max:191'], 'list' => true, 'filter' => false],
            ['name' => 'fabric_type_id',    'label' => 'الخامة',           'type' => 'select',   'options_from' => 'fabricTypes', 'rules' => ['nullable','exists:fabric_types,id'], 'list' => true, 'relation' => 'fabricType'],
            ['name' => 'pcs_per_dozen',     'label' => 'قطع الدستة',       'type' => 'number',   'rules' => ['required','integer','min:1'], 'list' => true],
            ['name' => 'std_consumption_kg','label' => 'استهلاك معياري (كجم/قطعة)', 'type' => 'number', 'step' => '0.0001', 'rules' => ['nullable','numeric','min:0']],
            ['name' => 'is_active',         'label' => 'نشط',              'type' => 'checkbox', 'rules' => ['boolean'], 'list' => true],
            ['name' => 'notes',             'label' => 'ملاحظات',          'type' => 'textarea', 'rules' => ['nullable','string']],
        ];
    }

    protected function formData(): array
    {
        return [
            'fabricTypes' => FabricType::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /** شاشة مقاسات الموديل + قائمة الإكسسوارات (BOM) */
    public function sizes($id)
    {
        $model = ProductModel::with(['sizes', 'boms.accessory', 'boms.size'])->findOrFail($id);

        return view('crud.model_sizes', [
            'title'       => 'مقاسات وإكسسوارات: ' . $model->name,
            'model'       => $model,
            'allSizes'    => Size::ordered()->where('is_active', true)->get(),
            'accessories' => \App\Models\Accessory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function saveSizes(Request $request, $id)
    {
        $model = ProductModel::findOrFail($id);
        $model->sizes()->sync($request->input('sizes', []));
        return back()->with('success', 'تم حفظ المقاسات.');
    }

    public function addBom(Request $request, $id)
    {
        $data = $request->validate([
            'accessory_id'  => ['required', 'exists:accessories,id'],
            'size_id'       => ['nullable', 'exists:sizes,id'],
            'qty_per_piece' => ['required', 'numeric', 'min:0.0001'],
            'notes'         => ['nullable', 'string'],
        ], [], [
            'accessory_id'  => 'الإكسسوار',
            'qty_per_piece' => 'الكمية لكل قطعة',
        ]);

        $data['product_model_id'] = $id;
        \App\Models\ModelBom::updateOrCreate(
            ['product_model_id' => $id, 'accessory_id' => $data['accessory_id'], 'size_id' => $data['size_id'] ?? null],
            $data
        );

        return back()->with('success', 'تمت إضافة الإكسسوار للموديل.');
    }

    public function deleteBom($id, $bomId)
    {
        \App\Models\ModelBom::where('product_model_id', $id)->findOrFail($bomId)->delete();
        return back()->with('success', 'تم الحذف.');
    }
}
