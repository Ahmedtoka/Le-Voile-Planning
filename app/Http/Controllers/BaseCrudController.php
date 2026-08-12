<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * كنترولر CRUD عام للبيانات الأساسية.
 *
 * كل شاشة بيانات أساسية بتتعرّف بمصفوفة حقول بس — نفس الشكل ونفس
 * السلوك في كل الشاشات، ومفيش تكرار كود.
 */
abstract class BaseCrudController extends Controller
{
    protected string $modelClass;
    protected string $title;        // عنوان الشاشة
    protected string $singular;     // المفرد (زرار الإضافة)
    protected string $routeName;    // اسم الراوت
    protected string $permission;   // بادئة الصلاحية
    protected array $searchable = ['code', 'name'];
    protected array $with = [];
    protected string $orderBy = 'id';
    protected string $orderDir = 'desc';
    protected bool $canDelete = true;

    /** تعريف الحقول */
    abstract protected function fields(): array;

    /** بيانات إضافية للفورم (قوائم منسدلة) */
    protected function formData(): array { return []; }

    public function index(Request $request)
    {
        $q = $this->modelClass::query()->with($this->with);

        if ($term = trim((string) $request->get('q'))) {
            $q->where(function ($qq) use ($term) {
                foreach ($this->searchable as $col) {
                    $qq->orWhere($col, 'like', "%{$term}%");
                }
            });
        }

        foreach ($this->fields() as $f) {
            if (!empty($f['filter']) && $request->filled($f['name'])) {
                $q->where($f['name'], $request->get($f['name']));
            }
        }

        $rows = $q->orderBy($this->orderBy, $this->orderDir)->paginate(25)->withQueryString();

        return view('crud.index', $this->viewData([
            'rows' => $rows,
        ]));
    }

    public function create()
    {
        return view('crud.form', $this->viewData([
            'row'  => new $this->modelClass,
            'mode' => 'create',
        ] + $this->formData()));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), [], $this->attributeNames());
        $data = $this->normalize($data, $request);

        $row = $this->modelClass::create($data);
        ActivityLogger::log('created', $row, $this->singular . ' جديد: ' . ($row->name ?? $row->id));

        return redirect()->route($this->routeName . '.index')->with('success', 'تمت الإضافة بنجاح.');
    }

    public function edit($id)
    {
        $row = $this->modelClass::findOrFail($id);

        return view('crud.form', $this->viewData([
            'row'  => $row,
            'mode' => 'edit',
        ] + $this->formData()));
    }

    public function update(Request $request, $id)
    {
        $row = $this->modelClass::findOrFail($id);

        $data = $request->validate($this->rules($id), [], $this->attributeNames());
        $data = $this->normalize($data, $request);

        $row->update($data);
        ActivityLogger::log('updated', $row, 'تعديل ' . $this->singular . ': ' . ($row->name ?? $row->id));

        return redirect()->route($this->routeName . '.index')->with('success', 'تم التعديل بنجاح.');
    }

    public function destroy($id)
    {
        if (!$this->canDelete) {
            return back()->withErrors(['msg' => 'الحذف مش مسموح في الشاشة دي — استخدم الإيقاف بدل الحذف.']);
        }

        $row = $this->modelClass::findOrFail($id);

        try {
            $row->delete();
        } catch (\Throwable $e) {
            return back()->withErrors(['msg' => 'مينفعش تحذف السجل ده لأنه مستخدم في مستندات. أوقفه بدل ما تحذفه.']);
        }

        ActivityLogger::log('deleted', $row, 'حذف ' . $this->singular);
        return back()->with('success', 'تم الحذف.');
    }

    // ── داخلي ────────────────────────────────────────────────────

    protected function rules($id = null): array
    {
        $rules = [];
        foreach ($this->fields() as $f) {
            if (!empty($f['no_input'])) continue;
            $r = $f['rules'] ?? ['nullable'];
            if ($id) {
                $r = array_map(fn ($x) => is_string($x) && str_starts_with($x, 'unique:') ? $x . ',' . $id : $x, $r);
            }
            $rules[$f['name']] = $r;
        }
        return $rules;
    }

    protected function attributeNames(): array
    {
        $names = [];
        foreach ($this->fields() as $f) {
            $names[$f['name']] = $f['label'];
        }
        return $names;
    }

    /** الشيك بوكس مش بيتبعت لو مش متعلّم */
    protected function normalize(array $data, Request $request): array
    {
        foreach ($this->fields() as $f) {
            $type = $f['type'] ?? 'text';

            if ($type === 'checkbox') {
                $data[$f['name']] = $request->boolean($f['name']);
                continue;
            }

            // الأعمدة الرقمية NOT NULL بـ default — الفورم بيبعتها فاضية،
            // و ConvertEmptyStringsToNull بيحوّلها null ⇒ خطأ في الداتابيز.
            if ($type === 'number'
                && !empty($f['default0'])
                && ($data[$f['name']] ?? null) === null) {
                $data[$f['name']] = 0;
            }
        }
        return $data;
    }

    protected function viewData(array $extra = []): array
    {
        return array_merge([
            'title'      => $this->title,
            'singular'   => $this->singular,
            'routeName'  => $this->routeName,
            'fields'     => $this->fields(),
            'canDelete'  => $this->canDelete,
            'formExtra'  => $this->formData(),
        ], $this->formData(), $extra);
    }
}
