@php
  $tpl = $tpl ?? false;
  $st  = $l['status'] ?? 'ok';
  // نوع العيب محفوظ في أول وصف العيب: «بوجرسو: تفاصيل»
  $dd = (string) ($l['defect_desc'] ?? '');
  $dtype = ''; $dtext = $dd;
  foreach (\App\Models\InspectionRoll::DEFECT_TYPES as $k) {
    if ($dd === $k || str_starts_with($dd, $k . ':')) { $dtype = $k; $dtext = trim(substr($dd, strlen($k) + 1)); break; }
  }
  if ($dd !== '' && $dtype === '') { $dtype = 'أخرى'; }
  $hasDefect = $dtype !== '' || (int) ($l['defects_count'] ?? 0) > 0;
@endphp
@if(!$tpl)<tr class="{{ $st === 'rejected' ? 'table-danger' : '' }}">@endif
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="rolls[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][length_m]" value="{{ $l['length_m'] ?? '' }}" placeholder="بالمتر — 50"></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}" placeholder="بالسم — 175"></td>
  <td><select name="rolls[{{ $i }}][status]" class="roll-status" onchange="LVI.rollStatus(this)">
      <option value="ok" @selected($st==='ok')>سليم</option>
      <option value="rejected" @selected($st==='rejected')>مرفوض</option>
    </select></td>
  <td><input type="number" step="0.001" name="rolls[{{ $i }}][net_kg]" class="roll-kg" value="{{ $l['net_kg'] ?? '' }}"
             placeholder="{{ $st==='rejected' ? 'إجباري' : 'بالكيلو — 190' }}" @required($st==='rejected')></td>
  <td><input type="number" step="0.01" name="rolls[{{ $i }}][gsm]" class="roll-gsm" value="{{ $l['gsm'] ?? '' }}" placeholder="جم/م²"></td>
  <td><select name="rolls[{{ $i }}][defect_type]" class="roll-dtype" onchange="LVI.defect(this)">
      <option value="">— سليم —</option>
      @foreach(\App\Models\InspectionRoll::DEFECT_TYPES as $k)<option value="{{ $k }}" @selected($dtype === $k)>{{ $k }}</option>@endforeach
    </select></td>
  <td class="roll-dwrap" @if(!$hasDefect) hidden @endif>
    <div class="d-flex gap-1">
      <input type="number" name="rolls[{{ $i }}][defects_count]" value="{{ $l['defects_count'] ?? '' }}" style="width:70px" placeholder="عدد" title="عدد العيوب في التوب">
      <input name="rolls[{{ $i }}][defect_desc]" value="{{ $dtext }}" placeholder="وصف العيب / مكانه">
    </div>
  </td>
  <td><input name="rolls[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
