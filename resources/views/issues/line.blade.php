@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr>@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td>
    <select name="lines[{{ $i }}][work_order_id]" class="wo-pick" onchange="LVI.onWo(this)">
      <option value="">— بدون —</option>
      @foreach($workOrders as $w)
        <option value="{{ $w->id }}" @selected(($l['work_order_id'] ?? null)==$w->id)>
          {{ $w->wo_no }} — {{ Str::limit($w->product_title, 26) }}
        </option>
      @endforeach
    </select>
    <input type="hidden" name="lines[{{ $i }}][work_order_fabric_id]" value="{{ $l['work_order_fabric_id'] ?? '' }}">
  </td>
  <td><input name="lines[{{ $i }}][item_code]" value="{{ $l['item_code'] ?? '' }}" placeholder="14810091"></td>
  <td>
    <select name="lines[{{ $i }}][consignment_id]" required>
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
    <select name="lines[{{ $i }}][unit]">
      @foreach(['كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select>
  </td>
  <td><input type="number" step="0.01" name="lines[{{ $i }}][width_cm]" value="{{ $l['width_cm'] ?? '' }}"></td>
  <td><input type="number" name="lines[{{ $i }}][rolls_count]" value="{{ $l['rolls_count'] ?? '' }}"></td>
  <td><input type="number" step="0.001" name="lines[{{ $i }}][qty]" value="{{ $l['qty'] ?? '' }}" required></td>
  <td><input name="lines[{{ $i }}][notes]" value="{{ $l['notes'] ?? '' }}"></td>
  <td class="text-center">
    <button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف السطر"
            onclick="LV.remove(this,'lines')"><i class="bi bi-x" aria-hidden="true"></i></button>
  </td>
@if(!$tpl)</tr>@endif
