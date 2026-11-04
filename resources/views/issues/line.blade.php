{{--
  سطر صرف = خامة في أمر شغل معتمد (مش رسالة حرة): الرسالة واللون والوحدة والعرض ثابتين من الأمر.
  المخزن بيكتب عدد الأتواب والقائم على الميزان بس — السيستم يخصم الكيس والماسورة بأوزان الرسالة ويطلّع الصافي (الميتينج 37).
  بالمتر: الأمتار على طول.
--}}
@php
  $tpl = $tpl ?? false;
  $fid = $l['work_order_fabric_id'] ?? null;
@endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td>
    <select name="lines[{{ $i }}][work_order_fabric_id]" class="i-wf" required onchange="LVI.pick(this)" aria-label="أمر الشغل والخامة">
      <option value="">— اختر أمر الشغل والخامة —</option>
      @foreach($openFabrics as $f)
        @php $c = $f->consignment; $left = max(0, (float) $f->planned_qty - (float) $f->issued_qty); @endphp
        <option value="{{ $f->id }}" data-factory="{{ $f->workOrder?->factory_id }}" data-factory-name="{{ $f->workOrder?->factory?->name }}"
                data-meter="{{ $c?->is_meter ? 1 : 0 }}" data-bag="{{ $c?->bag_g ?? 0 }}" data-tube="{{ $c?->fabric_form === 'open' ? ($c?->tube_g ?? 0) : 0 }}"
                data-left="{{ $left }}" data-unit="{{ $c?->unit_label }}"
                data-info="{{ $c?->consignment_no }} · {{ $f->fabricType?->name }} · {{ $f->color?->code }}{{ $c?->min_width_cm ? ' · عرض ' . lv_num($c->min_width_cm, 1) . ' سم' : '' }}"
                data-pk="{{ ($c && !$c->is_meter && (float) $c->gross_kg > 0) ? 'كيس ' . lv_num($c->bag_g, 1) . ($c->fabric_form === 'open' ? ' + ماسورة ' . lv_num($c->tube_g, 1) : '') . ' جم للتوب' : '' }}"
                @selected((string) $fid === (string) $f->id)>
          {{ $f->workOrder?->wo_no }} — {{ $f->fabricType?->name }} {{ $f->color?->code }} · باقي {{ lv_num($left) }} {{ $c?->unit_label }}
        </option>
      @endforeach
    </select>
    <div class="hint i-info"></div>
    @if(!$fid && !empty($l['consignment_no']))
      {{-- إذن قديم اتعمل قبل ما الصرف يبقى على خامة أمر شغل --}}
      <div class="hint text-warning">سطر قديم على الرسالة {{ $l['consignment_no'] }} من غير أمر شغل</div>
    @endif
  </td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="14810091" aria-label="الكود على الورق"></td>
  <td class="num"><input type="number" min="0" name="lines[{{ $i }}][rolls_count]" class="i-rolls" value="{{ $l['rolls_count'] ?? '' }}" placeholder="4" aria-label="عدد الأتواب" oninput="LVI.net(this)"></td>
  <td class="num"><input type="number" step="0.001" min="0" name="lines[{{ $i }}][gross_qty]" class="i-gross" value="{{ $l['gross_qty'] ?? '' }}" placeholder="على الميزان" aria-label="الوزن القائم على الميزان" oninput="LVI.net(this)">
    <div class="hint i-pk"></div></td>
  <td class="num">
    <input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" class="i-net" value="{{ $l['qty'] ?? '' }}" required placeholder="يتحسب" aria-label="الصافي" readonly tabindex="-1">
    <div class="hint i-left"></div>
  </td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}" placeholder="اختياري" aria-label="ملاحظات"></td>
  <td class="text-end"><div class="row-actions">
    <button type="button" class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف السطر" title="حذف السطر"
            onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button>
  </div></td>
@if(!$tpl)</tr>@endif
