@php $tpl = $tpl ?? false; $st = $l['status'] ?? 'ok'; @endphp
@if(!$tpl)<tr class="{{ $st === 'rejected' ? 'table-danger' : '' }}">@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="rolls[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][length_m]" value="{{ $l['length_m'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}"></td>
  <td><select name="rolls[{{ $i }}][status]" class="roll-status" onchange="LVI.rollStatus(this)">
      <option value="ok" @selected($st==='ok')>سليم</option>
      <option value="rejected" @selected($st==='rejected')>مرفوض</option>
    </select></td>
  <td><input type="number" step="0.001" name="rolls[{{ $i }}][net_kg]" class="roll-kg" value="{{ $l['net_kg'] ?? '' }}"
             placeholder="{{ $st==='rejected' ? 'إجباري' : 'اختياري' }}" @required($st==='rejected')></td>
  <td><input type="number" name="rolls[{{ $i }}][defects_count]" value="{{ $l['defects_count'] ?? 0 }}"></td>
  <td><input name="rolls[{{ $i }}][defect_desc]" value="{{ $l['defect_desc'] ?? '' }}"></td>
  <td><input name="rolls[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
