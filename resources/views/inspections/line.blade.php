@php
  $tpl = $tpl ?? false;
  $meter = $meter ?? false;   // الرسالة بالمتر؟ (الميتينج 37): الأمتار المكتوبة على التوب + العرض + الحالة — من غير وزن ولا بنشر
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
  <td><input name="rolls[{{ $i }}][roll_no]" value="{{ $l['roll_no'] ?? '' }}" placeholder="مثال: 001" aria-label="رقم التوب"></td>
  @if($meter)
    <td class="num"><input type="number" step="0.01" min="0" name="rolls[{{ $i }}][labeled_length_m]" class="roll-len" value="{{ $l['labeled_length_m'] ?? $l['length_m'] ?? '' }}" placeholder="مثال: 100" aria-label="الأمتار المكتوبة على التوب"></td>
  @else
    <td class="num"><input type="number" step="0.01" min="0" name="rolls[{{ $i }}][length_m]" class="roll-len" value="{{ $l['length_m'] ?? '' }}" placeholder="مثال: 50" aria-label="الطول بالمتر"></td>
  @endif
  <td class="num"><input type="number" step="0.01" min="0" name="rolls[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}" placeholder="مثال: 175" aria-label="العرض بالسنتيمتر"></td>
  <td><select name="rolls[{{ $i }}][status]" class="roll-status" onchange="LVI.rollStatus(this)" aria-label="حالة التوب">
      <option value="ok" @selected($st==='ok')>سليم</option>
      <option value="rejected" @selected($st==='rejected')>مرفوض</option>
    </select></td>
  @unless($meter)
  <td class="num"><input type="number" step="0.001" min="0" name="rolls[{{ $i }}][net_kg]" class="roll-kg" value="{{ $l['net_kg'] ?? '' }}"
             placeholder="{{ $st==='rejected' ? 'إجباري' : 'مثال: 19.5' }}" @required($st==='rejected') aria-label="وزن التوب بالكيلو"></td>
  <td class="num"><input type="number" step="0.01" min="0" name="rolls[{{ $i }}][gsm]" class="roll-gsm" value="{{ $l['gsm'] ?? '' }}" placeholder="مثال: 195" aria-label="البنشر جم/م²"></td>
  @endunless
  <td><select name="rolls[{{ $i }}][defect_type]" class="roll-dtype" onchange="LVI.defect(this)" aria-label="نوع العيب">
      <option value="">— سليم —</option>
      @foreach(\App\Models\InspectionRoll::DEFECT_TYPES as $k)<option value="{{ $k }}" @selected($dtype === $k)>{{ $k }}</option>@endforeach
    </select></td>
  <td class="roll-dwrap" @if(!$hasDefect) hidden @endif>
    <div class="d-flex gap-1">
      <input type="number" min="0" step="1" name="rolls[{{ $i }}][defects_count]" value="{{ $l['defects_count'] ?? '' }}" placeholder="عدد" title="عدد العيوب في التوب" aria-label="عدد العيوب">
      <input name="rolls[{{ $i }}][defect_desc]" value="{{ $dtext }}" placeholder="وصف العيب / مكانه" aria-label="وصف العيب">
    </div>
  </td>
  <td><input name="rolls[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}" placeholder="اختياري" aria-label="ملاحظات"></td>
  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
