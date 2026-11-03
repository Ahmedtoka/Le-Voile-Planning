{{--
  القائم ⇐ الكيس والماسورة ⇐ الصافي — بلوك موحد بيظهر في كل شاشة بعد إذن الإضافة (الميتينج 37).
  الاستخدام: @include('partials.packaging', ['c' => $consignment])            ⇐ شريط خانات جنب بعض
             @include('partials.packaging', ['c' => $consignment, 'inline' => true])  ⇐ سطر واحد صغير
  بيتخفي لوحده لو الرسالة بالمتر أو من غير وزن قائم.
  الشريط بيقرا من اليمين للشمال زي المعادلة: القائم − الكيس والماسورة = الصافي.
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
    <div class="pk-strip" role="group" aria-label="القائم والكيس والماسورة والصافي">
      <div class="pk-cell">
        <div class="k">القائم (على الميزان)</div>
        <div class="v num">{{ lv_num($pkC->gross_kg) }} <span class="u">كجم</span></div>
        <div class="f">{{ $pkOpen ? 'مفتوح — كيس وماسورة' : ($pkC->fabric_form === 'closed' ? 'مقفول — كيس بس' : '—') }}</div>
      </div>
      <div class="pk-op" aria-hidden="true">−</div>
      <div class="pk-cell">
        <div class="k">الكيس والماسورة</div>
        <div class="v num text-danger">{{ lv_num($pkC->packaging_kg) }} <span class="u">كجم</span></div>
        <div class="f num">كيس {{ lv_num($pkC->bag_g, 1) }}@if($pkOpen) + ماسورة {{ lv_num($pkC->tube_g, 1) }}@endif جم × {{ $pkRolls }} توب</div>
      </div>
      <div class="pk-op" aria-hidden="true">=</div>
      <div class="pk-cell pk-net">
        <div class="k">الصافي (الرصيد)</div>
        <div class="v num text-success">{{ lv_kg($pkC->total_kg) }}</div>
        <div class="f num">{{ $pkNetFormula }}</div>
      </div>
    </div>
  @endif
@endif
@once
<style>
  /* شريط المعادلة: ثلاث خانات جنب بعض وبينهم − و= — بياخد عرض المكان كله، وعلى الموبايل بيتراصّوا */
  .pk-strip{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;gap:6px 10px;text-align:center;
    background:var(--lv-white);border:1px solid var(--lv-line);border-radius:var(--r-sm);padding:10px 12px;margin-top:10px}
  .pk-strip .k{font-size:var(--fs-xs);color:var(--lv-muted);font-weight:600;line-height:1.3}
  .pk-strip .v{font-weight:700;font-size:var(--fs-md);color:var(--lv-ink);line-height:1.35}
  .pk-strip .v .u{font-size:var(--fs-xs);font-weight:500;color:var(--lv-muted)}
  .pk-strip .f{font-size:var(--fs-xs);color:var(--lv-muted);line-height:1.35}
  .pk-strip .pk-op{font-size:var(--fs-lg);font-weight:700;color:var(--lv-muted)}
  .pk-strip .pk-net{background:var(--lv-ok-bg, #EEF7F1);border-radius:8px;padding:4px 6px}
  @media (max-width:576px){
    .pk-strip{grid-template-columns:1fr}
    .pk-strip .pk-op{line-height:1;font-size:var(--fs-md)}
  }
</style>
@endonce
