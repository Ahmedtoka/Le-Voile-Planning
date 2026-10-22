@php
  $tpl    = $tpl ?? false;
  $fromPo = !empty($l['po_line_id']);
@endphp
@if(!$tpl)<tr @if($fromPo) class="po-row" data-ordered="{{ $l['po_ordered'] ?? 0 }}"
                data-min="{{ $l['po_min'] ?? ($l['po_ordered'] ?? 0) }}"
                data-received="{{ $l['po_received'] ?? 0 }}" data-unit="{{ $l['unit'] ?? '' }}" @endif>@endif

@if($fromPo)
  {{-- ═══ سطر جاي من طلب الشراء: بياناته ليبلات ثابتة — مفيش تعديل على الطلب ═══ --}}
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>

  <td>
    <div class="fw-bold">{{ $l['fabric_name'] ?? '—' }}</div>
    <div class="hint">{{ $l['item_name'] ?? '' }}</div>
    <input type="hidden" name="lines[{{ $i }}][po_line_id]"     value="{{ $l['po_line_id'] }}">
    <input type="hidden" name="lines[{{ $i }}][fabric_type_id]" value="{{ $l['fabric_type_id'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][item_name]"      value="{{ $l['item_name'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][item_code]"      value="{{ $l['item_code'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][unit]"           value="{{ $l['unit'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][po_color_id]"    value="{{ $l['po_color_id'] ?? '' }}">
    {{-- بيانات العرض بتترحّل مع الفورم عشان الشاشة ترجع كاملة بعد أي خطأ تحقق --}}
    <input type="hidden" name="lines[{{ $i }}][fabric_name]"    value="{{ $l['fabric_name'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][po_color_label]" value="{{ $l['po_color_label'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][po_ordered]"     value="{{ $l['po_ordered'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][po_received]"    value="{{ $l['po_received'] ?? '' }}">
  </td>

  <td>
    <span class="pill pill-muted">{{ $l['po_color_label'] ?? '—' }}</span>
  </td>

  <td>
    <select name="lines[{{ $i }}][color_id]" class="c-actual" aria-label="اللون الواصل"
            data-requested="{{ $l['po_color_id'] ?? '' }}"
            data-requested-label="{{ $l['po_color_label'] ?? '' }}">
      <option value="">—</option>
      @foreach($colors as $k=>$v)
        <option value="{{ $k }}" @selected(($l['color_id'] ?? $l['po_color_id'] ?? null)==$k)>{{ $v }}</option>
      @endforeach
    </select>
    <input type="hidden" name="lines[{{ $i }}][color_action]" class="c-action" value="{{ $l['color_action'] ?? '' }}">
    <div class="c-status mt-1">
      @if(($l['color_action'] ?? '') === 'substitute')
        <span class="pill pill-warn">تسكين — الطلب هيتحدث للون ده</span>
      @elseif(($l['color_action'] ?? '') === 'new_po')
        <span class="pill pill-info">سطر جديد — والأصلي يفضل مطلوب</span>
      @elseif(($l['color_id'] ?? $l['po_color_id']) == ($l['po_color_id'] ?? null))
        <span class="pill pill-ok"><i class="bi bi-check2" aria-hidden="true"></i> مطابق</span>
      @endif
    </div>
  </td>

  <td class="num"><input type="number" min="0" step="1" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? '' }}" placeholder="مثال: 12" aria-label="عدد الأتواب"></td>

  <td class="text-center">
    <div class="num fw-bold">{{ lv_qty($l['po_ordered'] ?? 0, $l['unit'] ?? '') }}</div>
    @if((float)($l['po_received'] ?? 0) > 0)
      <div class="hint">استلم قبل كده <span class="num">{{ lv_num($l['po_received'], 3) }}</span></div>
    @endif
  </td>

  <td class="num">
    <input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" class="q-recv"
           value="{{ $l['qty'] ?? '' }}" placeholder="مثال: 2.5" aria-label="المستلم بالـ{{ $l['unit'] ?? '' }}">
  </td>

  <td class="text-center">
    <span class="q-left hint">—</span>
  </td>

  {{-- الباقي بتاع السطر ده هيوصل إمتى؟ بيظهر بس لما يبقى فيه باقي فعلًا --}}
  <td>
    <div class="r-eta" style="{{ ($l['remainder_eta'] ?? null) ? '' : 'display:none' }}">
      <input type="date" name="lines[{{ $i }}][remainder_eta]" class="form-control form-control-sm mb-1"
             value="{{ $l['remainder_eta'] ?? '' }}" aria-label="الباقي هيوصل إمتى">
      <input name="lines[{{ $i }}][remainder_note]" class="form-control form-control-sm"
             value="{{ $l['remainder_note'] ?? '' }}" placeholder="ملاحظة (اختياري)" aria-label="ملاحظة على الباقي">
    </div>
  </td>

@else
  {{-- ═══ سطر حر (إذن من غير طلب) ═══ --}}
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="مثال: F-120" aria-label="كود الصنف"></td>
  <td><input name="lines[{{ $i }}][item_name]" value="{{ $l['item_name'] ?? '' }}" placeholder="اسم الصنف" aria-label="اسم الصنف"></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]" aria-label="الخامة"><option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td>
    <select name="lines[{{ $i }}][color_id]" aria-label="اللون"><option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select>
  </td>
  <td class="num"><input type="number" min="0" step="1" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? '' }}" placeholder="مثال: 12" aria-label="عدد الأتواب"></td>
  <td class="num"><input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" placeholder="مثال: 2300" aria-label="الكمية"></td>
  <td><select name="lines[{{ $i }}][unit]" aria-label="الوحدة">
      @foreach(['كجم','قطعة','متر','كرتونة'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select></td>
@endif

  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
