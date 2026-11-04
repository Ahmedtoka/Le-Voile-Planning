{{--
  سطر خامة في أمر الشغل = طلب تحضير اتوزن + ماركر معتمد (الميتينج 36: «مفيش أرقام تتكتب يدوي»).
  كل الأرقام ليبلات: الكمية = الوزن الفعلي من المخزن · الطول والقطع = الماركر · العرض والبنشر = الجودة.
  الاختيار الوحيد: أنهي ماركر (من المعتمدة اللي تنفع على العرض) وعرض الحسبة (تشغيل ولا كامل).
  الخانات المخفية f-* بتغذّي الحسبة اللايف بس — السيرفر بيعيد حسابها من الطلب والماركر.
--}}
@php
  $pr      = $l['_pr'] ?? null;
  $c       = $pr?->consignment;
  $markers = $l['_markers'] ?? collect();
  $mk      = $l['_marker'] ?? null;
  $legacy  = !empty($l['_legacy']);
  $meter   = (bool) $c?->is_meter;
  $safetyCm = $safetyCm ?? (float) lv_setting('safety_margin_cm', 5);
@endphp
<tr class="fab-row" data-w="{{ $c?->usable_width ?: $c?->min_width_cm }}" data-wf="{{ $c?->min_width_cm }}">
  <td class="text-center row-no">{{ is_numeric($i) ? $i+1 : '•' }}</td>

  @if($legacy || !$pr)
    <td colspan="7">
      <div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> خامة من غير طلب تحضير</div>
      <div class="hint">الأمر ده اتعمل قبل ما أمر الشغل يتربط بطلب التحضير والماركر. امسح السطر ده وضيف الخامة من «خامة تانية من طلب تحضير».</div>
    </td>
  @else
    <td>
      <input type="hidden" name="fabrics[{{ $i }}][preparation_request_id]" value="{{ $pr->id }}">
      {{-- للحسبة اللايف بس (السيرفر بيعيد حسابها من الطلب والماركر) --}}
      <input type="hidden" class="f-mode" value="{{ $l['calc_mode'] }}">
      <input type="hidden" class="f-qty"  value="{{ $l['planned_qty'] }}">
      <input type="hidden" class="f-sp"   value="{{ $l['spread_length_m'] }}">
      <input type="hidden" class="f-pps"  value="{{ $l['pieces_per_spread'] }}">
      <input type="hidden" class="f-wd"   value="{{ $l['fabric_width_m'] }}">
      <input type="hidden" class="f-gsm"  value="{{ $l['gsm_kg_m2'] }}">
      <input type="hidden" class="f-name" value="{{ $c->fabricType?->name }}">
      <div class="fw-bold num">{{ $c->consignment_no }}</div>
      <div class="hint">{{ $c->fabricType?->name }} · {{ $c->color?->code }}</div>
      <div class="hint">طلب تحضير <span class="num">{{ $pr->doc_no }}</span>
        @if($c->has_color_break)<span class="text-danger"> · <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> فواصل لون</span>@endif</div>
    </td>

    <td class="num">
      <div class="fw-bold text-success">{{ $meter ? lv_num($l['planned_qty'], 2) . ' م' : lv_kg($l['planned_qty']) }}</div>
      <div class="hint">{{ $pr->is_remainder ? 'الباقي كله' : 'وزن المخزن' }} {{ $pr->weighed_at?->format('m-d') }}</div>
    </td>

    <td>
      <select name="fabrics[{{ $i }}][marker_id]" class="f-mk" onchange="LVF.marker(this)" aria-label="الماركر" required>
        @foreach($markers as $m)
          <option value="{{ $m->id }}" data-sp="{{ $m->spread_length_m }}" data-pps="{{ $m->pieces_per_spread }}" @selected($mk && $mk->id === $m->id)>
            {{ $m->code }} — عرض {{ lv_num($m->fabric_width_cm, 1) }} سم{{ $pr->markerRequest?->marker_id === $m->id ? ' · ماركر الطلب' : '' }}
          </option>
        @endforeach
      </select>
      <div class="hint">{{ $markers->count() > 1 ? $markers->count() . ' ماركرات تنفع على العرض ده' : 'ماركر الطلب' }}</div>
    </td>

    <td class="num">
      <span class="f-sp-l fw-bold">{{ lv_num($l['spread_length_m'], 2) }}</span> م
      <div class="hint">+{{ lv_num($safetyCm, 0) }} سم أمان</div>
    </td>
    <td class="num"><span class="f-pps-l fw-bold">{{ (int) $l['pieces_per_spread'] }}</span> قطعة</td>

    <td class="num">
      @if(!$meter)
        <select name="fabrics[{{ $i }}][width_basis]" class="f-wb form-select-sm" onchange="LVF.basis(this)" aria-label="عرض الحسبة">
          <option value="usable" @selected(($l['width_basis'] ?? 'usable') === 'usable')>تشغيل {{ lv_num($c->usable_width ?: $c->min_width_cm, 1) }} سم</option>
          <option value="full" @selected(($l['width_basis'] ?? '') === 'full')>كامل {{ lv_num($c->min_width_cm, 1) }} سم</option>
        </select>
        <div class="hint">بنشر {{ lv_num($c->avg_gsm, 1) }} جم/م²</div>
      @else
        <span class="hint">بالطول — العرض مش داخل في الحسبة</span>
      @endif
    </td>

    <td class="f-out hint text-center">—</td>

  @endif

  <td class="text-end"><div class="row-actions">
    <button type="button" class="btn btn-sm btn-outline-danger btn-icon" aria-label="شيل الخامة" title="شيل الخامة"
            onclick="this.closest('tr').remove(); LV.renumber('fabrics'); LVF.calc()"><i class="bi bi-x" aria-hidden="true"></i></button>
  </div></td>
</tr>
