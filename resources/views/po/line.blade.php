@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td><select name="lines[{{ $i }}][color_id]">
      <option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]">
      <option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><input type="number" step="0.001" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}"></td>
  <td><select name="lines[{{ $i }}][unit]">
      @foreach(['طن','كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'طن')===$u)>{{ $u }}</option>@endforeach
    </select></td>
  <td><input type="number" step="0.01" name="lines[{{ $i }}][unit_price]" value="{{ $l['unit_price'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="lines[{{ $i }}][tolerance_pct]" value="{{ $l['tolerance_pct'] ?? $defaultTolerance }}"></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="LV.remove(this,'lines')"><i class="bi bi-x"></i></button></td>
@if(!$tpl)</tr>@endif
