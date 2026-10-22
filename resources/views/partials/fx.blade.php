{{--
  ⓘ المعادلة — زرار صغير جنب أي رقم محسوب، يفتح popover فيه الحسبة بالأرقام الفعلية.
  الاستخدام:
    @include('partials.fx', ['t' => 'وزن الرِقّة', 'f' => '3.07 م × 1.72 م × 0.192 كجم/م² = 1.014 كجم'])
    @include('partials.fx', ['t' => '...', 'f' => ['سطر 1', 'سطر 2'], 'why' => 'ليه كده؟ ...'])
  $t   : عنوان الحسبة (اختياري — الافتراضي «إزاي اتحسب؟»)
  $f   : المعادلة بالأرقام — نص أو مصفوفة سطور
  $why : سطر شرح صغير تحت المعادلة (اختياري)
--}}
@php
  $fxLines = array_values(array_filter(is_array($f ?? null) ? $f : [$f ?? '']));
  $fxTitle = $t ?? 'إزاي اتحسب؟';
  $fxBody  = '';
  foreach ($fxLines as $l) $fxBody .= '<span class="fx-f">' . e($l) . '</span>';
  if (!empty($why)) $fxBody .= '<span class="fx-why">' . e($why) . '</span>';
@endphp
@if($fxLines)
<button type="button" class="fx" data-bs-toggle="popover" data-bs-title="{{ $fxTitle }}" data-bs-content="{{ $fxBody }}"
        aria-label="{{ $fxTitle }}"><i class="bi bi-info-lg" aria-hidden="true"></i></button>
@endif
