@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="readings[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="readings[{{ $i }}][gsm]" value="{{ $l['gsm'] ?? '' }}"></td>
  <td></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="LV.remove(this,'lines')"><i class="bi bi-x"></i></button></td>
@if(!$tpl)</tr>@endif
