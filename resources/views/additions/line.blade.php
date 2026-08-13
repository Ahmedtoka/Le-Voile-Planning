@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}"></td>
  <td><input name="lines[{{ $i }}][item_name]" value="{{ $l['item_name'] ?? '' }}"></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]"><option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][color_id]"><option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][accessory_id]"><option value="">—</option>
      @foreach($accessories as $k=>$v)<option value="{{ $k }}" @selected(($l['accessory_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><input type="number" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? '' }}" placeholder="0"></td>
  <td><input type="number" step="0.001" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}"></td>
  <td><select name="lines[{{ $i }}][unit]">
      @foreach(['كجم','قطعة','متر','كرتونة'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
