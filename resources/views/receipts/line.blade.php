@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="مثال: F-120" aria-label="الكود"></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]" aria-label="الصنف">
      <option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][color_id]" aria-label="اللون">
      <option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][unit]" aria-label="الوحدة">
      @foreach(['كجم','طن','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select></td>
  <td class="num"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}" placeholder="مثال: 175" aria-label="العرض بالسنتيمتر"></td>
  <td class="num"><input type="number" min="0" step="1" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? 1 }}" placeholder="مثال: 12" aria-label="عدد الأتواب"></td>
  <td class="num"><input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" placeholder="مثال: 2300" aria-label="الكمية"></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}" placeholder="اختياري" aria-label="ملاحظات"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
