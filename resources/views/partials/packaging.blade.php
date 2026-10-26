{{--
  القائم ⇐ الكيس والماسورة ⇐ الصافي — بلوك موحد بيظهر في كل شاشة بعد إذن الإضافة (الميتينج 37).
  الاستخدام: @include('partials.packaging', ['c' => $consignment])            ⇐ بلوك كامل (sum-grid)
             @include('partials.packaging', ['c' => $consignment, 'inline' => true])  ⇐ سطر واحد صغير
  بيتخفي لوحده لو الرسالة بالمتر أو من غير وزن قائم.
--}}
@php
  $pkC = $c ?? null;
  $pkShow = $pkC && !$pkC->is_meter && (float) $pkC->gross_kg > 0;
  if ($pkShow) {
    $pkOpen = $pkC->fabric_form === 'open';
    $pkRolls = (int) $pkC->rolls_count;
    $pkFormula = '(' . lv_num($pkC->bag_g, 1) . ($pkOpen ? ' + ' . lv_num($pkC->tube_g, 1) : '') . ') جم × ' . $pkRolls . ' توب ÷ 1000 = ' . lv_num($pkC->packaging_kg) . ' كجم';
    $pkNetFormula = lv_num($pkC->gross_kg) . ' − ' . lv_num($pkC->packaging_kg) . ' = ' . lv_num($pkC->total_kg) . ' كجم';
  }
@endphp
@if($pkShow)
  @if(!empty($inline))
    <span class="hint num">قائم {{ lv_num($pkC->gross_kg) }} − كيس {{ lv_num($pkC->bag_g, 1) }}{{ $pkOpen ? ' + ماسورة ' . lv_num($pkC->tube_g, 1) : '' }} جم × {{ $pkRolls }} توب = <b class="text-success">صافي {{ lv_num($pkC->total_kg) }} كجم</b></span>
  @else
    <div class="pk-strip">
      <div class="k">الوزن القائم (على الميزان)</div><div class="v num">{{ lv_num($pkC->gross_kg) }} كجم</div>
      <div class="k">الشكل</div><div class="v">{{ $pkOpen ? 'مفتوح — كيس وماسورة' : ($pkC->fabric_form === 'closed' ? 'مقفول — كيس بس' : '—') }}</div>
      <div class="k">وزن الكيس الواحد</div><div class="v num">{{ lv_num($pkC->bag_g, 1) }} جم</div>
      @if($pkOpen)<div class="k">وزن الماسورة الواحدة</div><div class="v num">{{ lv_num($pkC->tube_g, 1) }} جم</div>@endif
      <div class="k">خصم الكيس والماسورة</div><div class="v num text-danger">− {{ lv_num($pkC->packaging_kg) }} كجم <span class="hint">{{ $pkFormula }}</span></div>
      <div class="k">الوزن الصافي (الرصيد)</div><div class="v num text-success fw-bold">{{ lv_kg($pkC->total_kg) }} <span class="hint">{{ $pkNetFormula }}</span></div>
    </div>
  @endif
@endif
@once
<style>
  .pk-strip{display:grid;grid-template-columns:max-content 1fr;gap:4px 14px;align-items:baseline;font-size:var(--fs-sm);
    background:var(--lv-surface-2);border:1px dashed var(--lv-line);border-radius:var(--r-sm);padding:8px 12px;margin-top:8px}
  .pk-strip .k{color:var(--lv-muted);font-weight:600}
  .pk-strip .v{color:var(--lv-ink)}
  .pk-strip .v .hint{font-weight:400;margin-inline-start:8px}
  @media (max-width:576px){.pk-strip{grid-template-columns:1fr}.pk-strip .k{margin-top:4px}}
</style>
@endonce
