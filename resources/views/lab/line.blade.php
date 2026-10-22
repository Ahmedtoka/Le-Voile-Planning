@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="readings[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}" placeholder="مثال: 001" aria-label="رقم التوب"></td>
  <td class="num"><input type="number" step="0.01" min="0" name="readings[{{ $i }}][gsm]" value="{{ $l['gsm'] ?? '' }}" placeholder="مثال: 195" aria-label="وزن البنشر جم/م²"></td>
  <td></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
