@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td><select name="lines[{{ $i }}][color_id]" required aria-label="كود اللون">
      <option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]" required aria-label="اسم الصنف">
      <option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td class="num"><input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" required placeholder="مثال: 2.5" aria-label="الكمية"></td>
  <td><select name="lines[{{ $i }}][unit]" aria-label="الوحدة">
      @foreach(['طن','كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'طن')===$u)>{{ $u }}</option>@endforeach
    </select></td>
  <td class="num"><input type="number" step="0.01" min="0" max="100" name="lines[{{ $i }}][tolerance_pct]" value="{{ $l['tolerance_pct'] ?? $defaultTolerance }}" placeholder="مثال: 5" aria-label="نسبة الزيادة %"></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}" placeholder="مثال: وزن المقطع من 190 إلى 210 جرام" aria-label="ملاحظات"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
