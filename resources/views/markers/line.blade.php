@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><select name="lines[{{ $i }}][product_model_id]">
      <option value="">—</option>
      @foreach($models as $k=>$v)<option value="{{ $k }}" @selected(($l['product_model_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><select name="lines[{{ $i }}][size_id]"><option value="">كل المقاسات</option>
      @foreach($sizes as $k=>$v)<option value="{{ $k }}" @selected(($l['size_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td><input type="number" name="lines[{{ $i }}][qty_per_spread]" value="{{ $l['qty_per_spread'] ?? 1 }}"></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
