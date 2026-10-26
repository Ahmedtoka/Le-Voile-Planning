@php
  $tpl    = $tpl ?? false;
  $fromPo = !empty($l['po_line_id']);
  $unit   = $l['unit'] ?? 'كجم';
  $meter  = lv_is_meter($unit);
  $form   = $l['fabric_form'] ?? '';
@endphp
@if(!$tpl)<tr class="{{ $fromPo ? 'po-row' : 'free-row' }}" data-unit="{{ $unit }}" data-meter="{{ $meter ? 1 : 0 }}"
        @if($fromPo) data-ordered="{{ $l['po_ordered'] ?? 0 }}"
                data-min="{{ $l['po_min'] ?? ($l['po_ordered'] ?? 0) }}"
                data-received="{{ $l['po_received'] ?? 0 }}" @endif>@endif

@if($fromPo)
  {{-- ═══ سطر جاي من طلب الشراء: بياناته ليبلات ثابتة — مفيش تعديل على الطلب ═══ --}}
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>

  <td>
    <div class="fw-bold">{{ $l['fabric_name'] ?? '—' }}</div>
    <div class="hint">{{ $l['item_name'] ?? '' }} <span class="pill pill-muted">{{ $meter ? 'بالمتر' : 'بالكيلو' }}</span></div>
    <input type="hidden" name="lines[{{ $i }}][po_line_id]"     value="{{ $l['po_line_id'] }}">
    <input type="hidden" name="lines[{{ $i }}][po_sourcing_line_id]" value="{{ $l['po_sourcing_line_id'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][fabric_type_id]" value="{{ $l['fabric_type_id'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][item_name]"      value="{{ $l['item_name'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][item_code]"      value="{{ $l['item_code'] ?? '' }}">
    <input type="hidden" name="lines[{{ $i }}][unit]"           value="{{ $unit }}">
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

  <td class="text-center">
    <div class="num fw-bold">{{ lv_qty($l['po_ordered'] ?? 0, $unit) }}</div>
    @if((float)($l['po_received'] ?? 0) > 0)
      <div class="hint">استلم قبل كده <span class="num">{{ lv_num($l['po_received'], 3) }}</span></div>
    @endif
    @if(!empty($l['src_qty']))
      <div class="hint">على التسكينة دي <span class="num">{{ lv_num($l['src_qty'], 3) }}</span></div>
    @endif
  </td>

  <td class="num"><input type="number" min="0" step="1" name="lines[{{ $i }}][rolls_count]" class="q-rolls" value="{{ $l['rolls_count'] ?? '' }}" placeholder="مثال: 12" aria-label="عدد الأتواب" oninput="LVA.net(this)"></td>

@else
  {{-- ═══ سطر حر (إذن من غير طلب) ═══ --}}
  <td class="text-center row-no">{{ is_numeric($i)?$i+1:1 }}</td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="مثال: F-120" aria-label="كود الصنف">
      <input name="lines[{{ $i }}][item_name]" value="{{ $l['item_name'] ?? '' }}" placeholder="اسم الصنف" aria-label="اسم الصنف" class="mt-1"></td>
  <td><select name="lines[{{ $i }}][fabric_type_id]" aria-label="الخامة"><option value="">—</option>
      @foreach($fabricTypes as $k=>$v)<option value="{{ $k }}" @selected(($l['fabric_type_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td>
    <select name="lines[{{ $i }}][color_id]" aria-label="اللون"><option value="">—</option>
      @foreach($colors as $k=>$v)<option value="{{ $k }}" @selected(($l['color_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select>
  </td>
  <td><select name="lines[{{ $i }}][unit]" class="q-unit" aria-label="الوحدة" onchange="LVA.unit(this)">
      @foreach(['كجم','متر','قطعة','كرتونة'] as $u)<option value="{{ $u }}" @selected($unit===$u)>{{ $u }}</option>@endforeach
    </select></td>
  <td class="num"><input type="number" min="0" step="1" name="lines[{{ $i }}][rolls_count]" class="q-rolls" value="{{ $l['rolls_count'] ?? '' }}" placeholder="مثال: 12" aria-label="عدد الأتواب" oninput="LVA.net(this)"></td>
@endif

  {{-- ═══ الصافي والقائم — للكيلو بس (الميتينج 37): الشكل ⇐ الكيس والماسورة ⇐ القائم ⇐ الصافي يتحسب ═══ --}}
  <td class="pk-cell" @if($meter) hidden @endif>
    <select name="lines[{{ $i }}][fabric_form]" class="q-form" aria-label="مفتوح ولا مقفول" onchange="LVA.form(this)">
      <option value="" @selected($form==='')>— الشكل —</option>
      <option value="open" @selected($form==='open')>مفتوح (ماسورة)</option>
      <option value="closed" @selected($form==='closed')>مقفول (كيس بس)</option>
    </select>
    <div class="d-flex gap-1 mt-1 pk-g" @if($form==='') hidden @endif>
      <input type="number" step="0.1" min="0" name="lines[{{ $i }}][bag_g]" class="q-bag" value="{{ $l['bag_g'] ?? '' }}" placeholder="كيس جم" title="وزن الكيس الواحد بالجرام" aria-label="وزن الكيس الواحد بالجرام" oninput="LVA.net(this)">
      <input type="number" step="0.1" min="0" name="lines[{{ $i }}][tube_g]" class="q-tube" value="{{ $l['tube_g'] ?? '' }}" placeholder="ماسورة جم" title="وزن الماسورة الواحدة بالجرام" aria-label="وزن الماسورة الواحدة بالجرام" oninput="LVA.net(this)" @if($form==='closed') hidden @endif>
    </div>
  </td>
  <td class="num pk-cell" @if($meter) hidden @endif>
    <input type="number" step="0.001" min="0" name="lines[{{ $i }}][gross_qty]" class="q-gross" value="{{ $l['gross_qty'] ?? '' }}" placeholder="مثال: 600" aria-label="الوزن القائم بالكيلو" oninput="LVA.net(this)">
  </td>

  <td class="num">
    @if($meter)
      <input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" class="q-recv" value="{{ $l['qty'] ?? '' }}" placeholder="مثال: 950" aria-label="المستلم بالمتر">
    @else
      <input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" class="q-recv q-net" value="{{ $l['qty'] ?? '' }}" placeholder="يتحسب" aria-label="الصافي بالكيلو" readonly tabindex="-1">
      <div class="hint q-pk"></div>
    @endif
  </td>

@if($fromPo)
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
@endif

  <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-icon" title="حذف السطر" aria-label="حذف السطر" onclick="LV.remove(this,'lines')"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>
@if(!$tpl)</tr>@endif
