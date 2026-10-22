@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td>
    <select name="lines[{{ $i }}][work_order_id]" class="wo-pick" onchange="LVI.onWo(this)" aria-label="أمر الشغل">
      <option value="">— بدون —</option>
      @foreach($workOrders as $w)
        <option value="{{ $w->id }}" @selected(($l['work_order_id'] ?? null)==$w->id)>
          {{ $w->wo_no }} — {{ Str::limit($w->product_title, 26) }}
        </option>
      @endforeach
    </select>
    <input type="hidden" name="lines[{{ $i }}][work_order_fabric_id]" value="{{ $l['work_order_fabric_id'] ?? '' }}">
  </td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="مثال: 14810091" aria-label="الكود"></td>
  <td>
    <select name="lines[{{ $i }}][consignment_id]" required aria-label="رقم الرسالة">
      <option value="">— اختر رسالة —</option>
      @foreach($consignments as $c)
        <option value="{{ $c->id }}" @selected(($l['consignment_id'] ?? null)==$c->id)>
          {{ $c->consignment_no }} — {{ $c->fabricType?->name }} · {{ $c->color?->code }}
          (متاح {{ rtrim(rtrim(number_format((float)$c->remaining_kg,2),'0'),'.') }})
        </option>
      @endforeach
    </select>
  </td>
  <td>
    <select name="lines[{{ $i }}][unit]" aria-label="الوحدة">
      @foreach(['كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select>
  </td>
  <td class="num"><input type="number" step="0.01" min="0" name="lines[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}" placeholder="مثال: 172" aria-label="العرض بالسنتيمتر"></td>
  <td class="num"><input type="number" min="0" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? '' }}" placeholder="مثال: 4" aria-label="عدد الأتواب"></td>
  <td class="num"><input type="number" step="0.001" min="0" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" required placeholder="مثال: 152.9" aria-label="الكمية"></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}" placeholder="اختياري" aria-label="ملاحظات"></td>
  <td class="text-end"><div class="row-actions">
    <button type="button" class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف السطر" title="حذف السطر"
            onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button>
  </div></td>
@if(!$tpl)</tr>@endif
