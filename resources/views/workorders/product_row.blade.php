@php $tpl = $tpl ?? false; $mid = $l['product_model_id'] ?? null; @endphp
@if(!$tpl)<tr class="prod-row" data-model="{{ $mid }}">@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>
  <td>
    <input type="hidden" name="products[{{ $i }}][product_model_id]" class="p-model" value="{{ $mid }}">
    <span class="p-label fw-bold">{{ $mid ? ($models[$mid] ?? $mid) : '' }}</span>
  </td>
  <td><select name="products[{{ $i }}][size_id]" aria-label="المقاس">
      <option value="">كل المقاسات</option>
      @foreach($sizes as $k=>$v)<option value="{{ $k }}" @selected(($l['size_id'] ?? null)==$k)>{{ $v }}</option>@endforeach
    </select></td>
  <td class="num"><input type="number" name="products[{{ $i }}][qty_per_spread]" min="0"
             value="{{ ($l['qty_per_spread'] ?? 1) == 1 && !isset($l['id']) ? '' : ($l['qty_per_spread'] ?? '') }}"
             placeholder="مثال: 6" aria-label="قطعه في الفرشة"></td>
  <td class="num"><input type="number" min="0" name="products[{{ $i }}][planned_qty]" value="{{ $l['planned_qty'] ?? '' }}"
             placeholder="فاضي = من الرِقّات" aria-label="الكمية"></td>
  <td class="text-end"><div class="row-actions">
    <button type="button" class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف السطر" title="حذف السطر"
            onclick="LVW.removeRow(this)"><i class="bi bi-x" aria-hidden="true"></i></button>
  </div></td>
@if(!$tpl)</tr>@endif
