@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="rolls[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][length_m]" value="{{ $l['length_m'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][gsm]" value="{{ $l['gsm'] ?? '' }}"></td>
  <td><input type="number" name="rolls[{{ $i }}][defects_count]" value="{{ $l['defects_count'] ?? 0 }}"></td>
  <td><input name="rolls[{{ $i }}][defect_desc]" value="{{ $l['defect_desc'] ?? '' }}"></td>
  <td><input name="rolls[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
