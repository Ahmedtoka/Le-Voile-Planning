@php $tpl = $tpl ?? false; @endphp
@if(!$tpl)<tr class="fab-row">@endif
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : 1 }}</td>

  <td>
    @if(!empty($l['preparation_request_id']))
      <input type="hidden" name="fabrics[{{ $i }}][preparation_request_id]" value="{{ $l['preparation_request_id'] }}">
      <div class="hint text-success"><i class="bi bi-speedometer2" aria-hidden="true"></i> الكمية = الوزن الفعلي من المخزن (طلب تحضير)</div>
    @endif
    <select name="fabrics[{{ $i }}][consignment_id]" class="f-cn" required onchange="LVF.pick(this)" aria-label="رقم الرسالة">
      <option value="">— اختر رسالة —</option>
      @foreach($consignments as $c)
        <option value="{{ $c->id }}"
                data-w="{{ $c->usable_width }}" data-wf="{{ $c->min_width_cm }}" data-g="{{ $c->avg_gsm }}"
                data-pk="{{ (!$c->is_meter && (float) $c->gross_kg > 0) ? 'قائم ' . lv_num($c->gross_kg) . ' − كيس ' . lv_num($c->bag_g, 1) . ($c->fabric_form === 'open' ? ' + ماسورة ' . lv_num($c->tube_g, 1) : '') . ' جم × ' . (int) $c->rolls_count . ' توب = صافي ' . lv_num($c->total_kg) . ' كجم' : '' }}"
                data-rem="{{ $c->remaining_kg }}" data-name="{{ $c->fabricType?->name }}"
                data-break="{{ $c->has_color_break ? 1 : 0 }}" data-breaknote="{{ $c->color_break_note }}"
                data-search="{{ strtolower($c->consignment_no . ' ' . ($c->fabricType?->name ?? '') . ' ' . ($c->color?->code ?? '') . ' ' . ($c->color?->name ?? '')) }}"
                @selected(($l['consignment_id'] ?? null)==$c->id)>
          {{ $c->consignment_no }} — {{ $c->fabricType?->name }} · {{ $c->color?->code }}
          (متاح {{ rtrim(rtrim(number_format((float)$c->remaining_kg,1),'0'),'.') }} · {{ $c->arrival_date?->format('y-m-d') }})
        </option>
      @endforeach
    </select>
    <div class="f-break text-danger hint" hidden><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> فواصل / اختلاف لون في الرسالة دي</div>
    <div class="f-pk hint num"></div>
  </td>

  <td><select name="fabrics[{{ $i }}][calc_mode]" class="f-mode" onchange="LVF.calc()" aria-label="طريقة الحساب">
      <option value="weight" @selected(($l['calc_mode'] ?? 'weight')==='weight')>بالوزن</option>
      <option value="length" @selected(($l['calc_mode'] ?? '')==='length')>بالطول</option>
    </select></td>

  <td><select name="fabrics[{{ $i }}][unit]" class="f-unit" aria-label="الوحدة">
      @foreach(['كجم','متر'] as $u)<option value="{{ $u }}" @selected(($l['unit'] ?? 'كجم')===$u)>{{ $u }}</option>@endforeach
    </select></td>

  <td class="num">
    <input type="number" step="0.001" min="0" name="fabrics[{{ $i }}][planned_qty]" class="f-qty"
           value="{{ $l['planned_qty'] ?? '' }}" required oninput="LVF.calc()" placeholder="مثال: 152.9" aria-label="الكمية">
    {{-- التخطيط العكسي: اكتب القطع المستهدفة والسيستم يحسب الخامة المطلوبة --}}
    <input type="number" min="0" class="f-target form-control form-control-sm mt-1"
           placeholder="أو قطع مستهدفة…" title="اكتب عدد القطع المطلوب والسيستم يحسب كمية الخامة اللازمة"
           aria-label="قطع مستهدفة" oninput="LVF.target(this)">
  </td>

  <td class="num"><input type="number" step="0.001" min="0" name="fabrics[{{ $i }}][spread_length_m]" class="f-sp"
             value="{{ $l['spread_length_m'] ?? '' }}" required oninput="LVF.safety(this)" placeholder="مثال: 3.07" aria-label="طول الفرشة بالمتر"></td>

  <td class="num"><input type="number" step="0.001" min="0" name="fabrics[{{ $i }}][spread_length_safe_m]" class="f-sps"
             value="{{ $l['spread_length_safe_m'] ?? '' }}" oninput="this.dataset.auto='0'; LVF.calc()" placeholder="+{{ $safetyCm }} سم تلقائي" aria-label="طول الفرشة بالأمان"></td>

  <td class="num"><input type="number" step="0.001" min="0" name="fabrics[{{ $i }}][fabric_width_m]" class="f-wd"
             value="{{ $l['fabric_width_m'] ?? '' }}" oninput="LVF.calc()" placeholder="مثال: 1.72" aria-label="العرض بالمتر">
    <input type="hidden" name="fabrics[{{ $i }}][width_basis]" class="f-wb" value="{{ $l['width_basis'] ?? 'usable' }}"></td>

  <td class="num"><input type="number" step="0.0001" min="0" name="fabrics[{{ $i }}][gsm_kg_m2]" class="f-gsm"
             value="{{ $l['gsm_kg_m2'] ?? '' }}" oninput="LVF.calc()" placeholder="مثال: 0.192" aria-label="البنشر كجم لكل متر مربع"></td>

  <td class="num"><input type="number" min="1" name="fabrics[{{ $i }}][pieces_per_spread]" class="f-pps"
             value="{{ $l['pieces_per_spread'] ?? '' }}" required oninput="LVF.calc()" placeholder="مثال: 12" aria-label="قطع الفرشة"></td>

  <td class="f-out hint text-center">—</td>

  <td class="num"><input type="number" min="0" name="fabrics[{{ $i }}][plies]" class="f-plies"
             value="{{ $l['plies'] ?? '' }}" placeholder="تلقائي" aria-label="الرقات"></td>

  <td class="num"><input type="number" min="0" name="fabrics[{{ $i }}][expected_pieces]" class="f-exp"
             value="{{ $l['expected_pieces'] ?? '' }}" placeholder="تلقائي" aria-label="القص المعتمد"></td>

  <td><select name="fabrics[{{ $i }}][marker_id]" aria-label="الماركر">
      <option value="">— بدون —</option>
      @foreach($markers as $m)
        {{-- مكتبة الماركرات: نفس الموديل على كل العروض — اختار اللي عرضه مطابق لأقل عرض الرسالة --}}
        <option value="{{ $m->id }}" @selected(($l['marker_id'] ?? null)==$m->id)>
          {{ $m->code }} — عرض {{ rtrim(rtrim(number_format((float)$m->fabric_width_cm,1),'0'),'.') }} سم
          · {{ $m->pieces_per_spread }} قطعة{{ $m->efficiency_pct ? ' · كفاءة ' . rtrim(rtrim(number_format((float)$m->efficiency_pct,1),'0'),'.') . '%' : '' }}
        </option>
      @endforeach
    </select></td>

  <td class="text-end"><div class="row-actions">
    <button type="button" class="btn btn-sm btn-outline-danger btn-icon" aria-label="حذف الخامة" title="حذف الخامة"
            onclick="LV.remove(this,'fabrics'); LVF.calc()"><i class="bi bi-x" aria-hidden="true"></i></button>
  </div></td>
@if(!$tpl)</tr>@endif
