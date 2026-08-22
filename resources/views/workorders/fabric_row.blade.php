@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr class="fab-row">@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>

  <td><select name="fabrics[{{ $i }}][consignment_id]" class="f-cn" required onchange="LVF.pick(this)">
      <option value="">— اختر رسالة —</option>
      @foreach($consignments as $c)
        <option value="{{ $c->id }}"
                data-w="{{ $c->min_width_cm }}" data-g="{{ $c->avg_gsm }}"
                data-rem="{{ $c->remaining_kg }}" data-name="{{ $c->fabricType?->name }}"
                @selected(($l['consignment_id'] ?? null)==$c->id)>
          {{ $c->consignment_no }} — {{ $c->fabricType?->name }} · {{ $c->color?->code }}
          (متاح {{ rtrim(rtrim(number_format((float)$c->remaining_kg,1),'0'),'.') }})
        </option>
      @endforeach
    </select></td>

  <td><select name="fabrics[{{ $i }}][calc_mode]" class="f-mode" onchange="LVF.calc()">
      <option value="weight" @selected(($l['calc_mode'] ?? 'weight')==='weight')>بالوزن</option>
      <option value="length" @selected(($l['calc_mode'] ?? '')==='length')>بالطول</option>
    </select></td>

  <td><select name="fabrics[{{ $i }}][unit]" class="f-unit">
      @foreach(['كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select></td>

  <td>
    <input type="number" step="0.001" name="fabrics[{{ $i }}][planned_qty]" class="f-qty"
           value="{{ $l['planned_qty'] ?? '' }}" required oninput="LVF.calc()">
    {{-- التخطيط العكسي: اكتب القطع المستهدفة والسيستم يحسب الخامة المطلوبة --}}
    <input type="number" class="f-target form-control form-control-sm mt-1"
           placeholder="أو قطع مستهدفة…" title="اكتب عدد القطع المطلوب والسيستم يحسب كمية الخامة اللازمة"
           oninput="LVF.target(this)">
  </td>

  <td><input type="number" step="0.001" name="fabrics[{{ $i }}][spread_length_m]" class="f-sp"
             value="{{ $l['spread_length_m'] ?? '' }}" required oninput="LVF.calc()"></td>

  <td><input type="number" step="0.001" name="fabrics[{{ $i }}][spread_length_safe_m]" class="f-sps"
             value="{{ $l['spread_length_safe_m'] ?? '' }}" oninput="LVF.calc()" placeholder="اختياري"></td>

  <td><input type="number" step="0.001" name="fabrics[{{ $i }}][fabric_width_m]" class="f-wd"
             value="{{ $l['fabric_width_m'] ?? '' }}" oninput="LVF.calc()"></td>

  <td><input type="number" step="0.0001" name="fabrics[{{ $i }}][gsm_kg_m2]" class="f-gsm"
             value="{{ $l['gsm_kg_m2'] ?? '' }}" oninput="LVF.calc()" placeholder="0.245"></td>

  <td><input type="number" name="fabrics[{{ $i }}][pieces_per_spread]" class="f-pps"
             value="{{ $l['pieces_per_spread'] ?? '' }}" required oninput="LVF.calc()"></td>

  <td class="f-out hint text-center">—</td>

  <td><input type="number" name="fabrics[{{ $i }}][plies]" class="f-plies"
             value="{{ $l['plies'] ?? '' }}" placeholder="تلقائي"></td>

  <td><input type="number" name="fabrics[{{ $i }}][expected_pieces]" class="f-exp"
             value="{{ $l['expected_pieces'] ?? '' }}" placeholder="تلقائي"></td>

  <td><select name="fabrics[{{ $i }}][marker_id]">
      <option value="">— بدون —</option>
      @foreach($markers as $m)
        {{-- مكتبة الماركرات: نفس الموديل على كل العروض — اختار اللي عرضه مطابق لأقل عرض الرسالة --}}
        <option value="{{ $m->id }}" @selected(($l['marker_id'] ?? null)==$m->id)>
          {{ $m->code }} — عرض {{ rtrim(rtrim(number_format((float)$m->fabric_width_cm,1),'0'),'.') }} سم
          · {{ $m->pieces_per_spread }} قطعة{{ $m->efficiency_pct ? ' · كفاءة ' . rtrim(rtrim(number_format((float)$m->efficiency_pct,1),'0'),'.') . '%' : '' }}
        </option>
      @endforeach
    </select></td>

  <td class="text-center">
    <button type="button" class="btn btn-sm btn-outline-danger py-0" aria-label="حذف الخامة"
            onclick="LV.remove(this,'fabrics'); LVF.calc()"><i class="bi bi-x" aria-hidden="true"></i></button>
  </td>
@if(!$tpl)</tr>@endif
