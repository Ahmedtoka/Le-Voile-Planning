@extends('layouts.app')
@section('content')

@php
  $in = $result['inputs'] ?? [];
  $n  = fn ($v, $d = 3) => lv_num($v, $d);
@endphp

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><span><i class="bi bi-calculator" aria-hidden="true"></i> مدخلات الحسبة</span></div>
      <form method="get" class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label req" for="c-width">عرض القماش</label>
            <div class="input-group">
              <input id="c-width" type="number" step="0.01" min="0" name="width_cm" class="form-control" value="{{ $input['width_cm'] ?? 185 }}" placeholder="مثال: 185" required>
              <span class="input-group-text">سم</span>
            </div>
            <div class="hint">استخدم <b>أقل</b> عرض في الحوض</div>
          </div>
          <div class="col-6">
            <label class="form-label req" for="c-gsm">وزن البنشر</label>
            <div class="input-group">
              <input id="c-gsm" type="number" step="0.01" min="0" name="gsm" class="form-control" value="{{ $input['gsm'] ?? 195 }}" placeholder="مثال: 195" required>
              <span class="input-group-text">جم/م²</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label req" for="c-spread">طول الفرشة</label>
            <div class="input-group">
              <input id="c-spread" type="number" step="0.001" min="0" name="spread_length_m" class="form-control" value="{{ $input['spread_length_m'] ?? 3.07 }}" placeholder="مثال: 3.07" required>
              <span class="input-group-text">م</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label req" for="c-pcs">عدد القطع في الفرشة</label>
            <div class="input-group">
              <input id="c-pcs" type="number" min="1" name="pieces_per_spread" class="form-control" value="{{ $input['pieces_per_spread'] ?? 10 }}" placeholder="مثال: 10" required>
              <span class="input-group-text">قطعة</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label" for="c-roll">طول التوب</label>
            <div class="input-group">
              <input id="c-roll" type="number" step="0.01" min="0" name="roll_length_m" class="form-control" value="{{ $input['roll_length_m'] ?? 50 }}" placeholder="مثال: 50">
              <span class="input-group-text">م</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label" for="c-kg">الكمية المتاحة</label>
            <div class="input-group">
              <input id="c-kg" type="number" step="0.001" min="0" name="available_kg" class="form-control" value="{{ $input['available_kg'] ?? 800 }}" placeholder="مثال: 800">
              <span class="input-group-text">كجم</span>
            </div>
          </div>
          <div class="col-12"><hr class="my-1"></div>
          <div class="col-6">
            <label class="form-label" for="c-actual">طول الفرشة الفعلي</label>
            <div class="input-group">
              <input id="c-actual" type="number" step="0.001" min="0" name="actual_spread_length_m" class="form-control" value="{{ $input['actual_spread_length_m'] ?? '' }}" placeholder="مثال: 3.10">
              <span class="input-group-text">م</span>
            </div>
            <div class="hint">لو المصنع فرش على طول مختلف</div>
          </div>
        </div>
        <div class="form-actions mt-3">
          <button class="btn btn-plum"><i class="bi bi-calculator" aria-hidden="true"></i> احسب</button>
          <span class="hint">الحاسبة ما بتحفظش حاجة — جرّب براحتك</span>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    @if($result)
      @if(!$result['ok'])
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> {!! implode('<br>', $result['errors']) !!}</div>
      @else
        @php
          $widthM = $in['width_cm'] / 100;
        @endphp
        <div class="calc-box mb-3">
          <div class="fw-bold mb-2">النتيجة</div>
          <div class="kv">
            <span>وزن الرِقّة الواحدة
              @include('partials.fx', ['t' => 'وزن الرِقّة',
                'f' => 'طول الفرشة ' . $n($in['spread_length_m']) . ' م × العرض ' . $n($widthM) . ' م × البنشر ' . $n($in['gsm'], 2) . ' جم/م² ÷ 1000 = ' . $n($result['ply_weight_kg'], 4) . ' كجم',
                'why' => 'المساحة × وزن المتر المربع'])
            </span>
            <b>{{ $n($result['ply_weight_kg'], 4) }} كجم</b>
          </div>
          <div class="kv">
            <span>استهلاك القطعة
              @include('partials.fx', ['t' => 'استهلاك القطعة',
                'f' => ['وزن الرِقّة ' . $n($result['ply_weight_kg'], 4) . ' كجم ÷ قطع الفرشة ' . $in['pieces_per_spread'] . ' = ' . $n($result['kg_per_piece'], 5) . ' كجم',
                        $n($result['kg_per_piece'], 5) . ' كجم × 1000 = ' . $n($result['g_per_piece'], 1) . ' جم'],
                'why' => 'كل قطعة بتاخد نصيبها من وزن الرِقّة'])
            </span>
            <b>{{ $n($result['g_per_piece'], 1) }} جم ({{ $n($result['kg_per_piece'], 5) }} كجم)</b>
          </div>
          <div class="kv">
            <span>عدد الرِقّات من التوب
              @if($result['plies_per_roll'] !== null)
                @include('partials.fx', ['t' => 'الرِقّات من التوب',
                  'f' => 'طول التوب ' . $n($in['roll_length_m']) . ' م ÷ طول الفرشة ' . $n($in['spread_length_m']) . ' م = ' . $n($in['roll_length_m'] / $in['spread_length_m'], 2) . ' ⇐ يتقطع لأقل صحيح = ' . $result['plies_per_roll'],
                  'why' => 'المصنع ما بيقصّش نص رِقّة'])
              @endif
            </span>
            <b>{{ $result['plies_per_roll'] ?? '—' }}</b>
          </div>
          <div class="kv">
            <span>الفاقد في آخر التوب
              @if($result['waste_per_roll_m'] !== null)
                @include('partials.fx', ['t' => 'الفاقد في آخر التوب',
                  'f' => 'طول التوب ' . $n($in['roll_length_m']) . ' م − (' . $result['plies_per_roll'] . ' رِقّة × ' . $n($in['spread_length_m']) . ' م) = ' . $n($result['waste_per_roll_m'], 2) . ' م',
                  'why' => 'اللي بيفضل بعد آخر رِقّة كاملة'])
              @endif
            </span>
            <b>{{ $result['waste_per_roll_m'] !== null ? $n($result['waste_per_roll_m'], 2) . ' م' : '—' }}</b>
          </div>
          <div class="kv">
            <span>إجمالي الفرشات المتوقعة
              @include('partials.fx', ['t' => 'الرِقّات المتوقعة',
                'f' => 'الكمية ' . $n($in['available_kg']) . ' كجم ÷ وزن الرِقّة ' . $n($result['ply_weight_kg'], 4) . ' كجم = ' . ($result['ply_weight_kg'] > 0 ? $n($in['available_kg'] / $result['ply_weight_kg'], 2) : '—') . ' ⇐ يتقطع لأقل صحيح = ' . number_format($result['expected_plies']),
                'why' => 'رِقّات كاملة بس'])
            </span>
            <b>{{ number_format($result['expected_plies']) }}</b>
          </div>
          <div class="kv">
            <span>القطع المتوقعة
              @include('partials.fx', ['t' => 'القطع المتوقعة',
                'f' => 'الرِقّات ' . number_format($result['expected_plies']) . ' × قطع الفرشة ' . $in['pieces_per_spread'] . ' = ' . number_format($result['expected_pieces']) . ' قطعة',
                'why' => 'نفس حسبة أمر الشغل بالظبط'])
            </span>
            <b class="text-success">{{ number_format($result['expected_pieces']) }}</b>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><span><i class="bi bi-journal-text" aria-hidden="true"></i> المعادلات المستخدمة</span></div>
          <div class="card-body">
            <span class="fx-f">وزن الرِقّة <span class="op">=</span> طول الفرشة {{ $n($in['spread_length_m']) }} م <span class="op">×</span> العرض {{ $n($widthM) }} م <span class="op">×</span> البنشر {{ $n($in['gsm'], 2) }} جم/م² <span class="op">÷</span> 1000 <span class="op">=</span> {{ $n($result['ply_weight_kg'], 4) }} كجم</span>
            <span class="fx-f">استهلاك القطعة <span class="op">=</span> {{ $n($result['ply_weight_kg'], 4) }} كجم <span class="op">÷</span> {{ $in['pieces_per_spread'] }} قطعة <span class="op">=</span> {{ $n($result['kg_per_piece'], 5) }} كجم</span>
            @if($result['plies_per_roll'] !== null)
              <span class="fx-f">الرِقّات من التوب <span class="op">=</span> أقل صحيح من ({{ $n($in['roll_length_m']) }} م <span class="op">÷</span> {{ $n($in['spread_length_m']) }} م) <span class="op">=</span> {{ $result['plies_per_roll'] }} رِقّة</span>
            @endif
            <span class="fx-f">الرِقّات المتوقعة <span class="op">=</span> أقل صحيح من ({{ $n($in['available_kg']) }} كجم <span class="op">÷</span> {{ $n($result['ply_weight_kg'], 4) }} كجم) <span class="op">=</span> {{ number_format($result['expected_plies']) }} رِقّة</span>
            <span class="fx-f">القطع المتوقعة <span class="op">=</span> {{ number_format($result['expected_plies']) }} رِقّة <span class="op">×</span> {{ $in['pieces_per_spread'] }} قطعة <span class="op">=</span> {{ number_format($result['expected_pieces']) }} قطعة</span>
            <div class="hint mt-2">العرض بالمتر = {{ $n($in['width_cm'], 2) }} سم ÷ 100 · الرِقّات بتتقطع لأقل عدد صحيح لأن المصنع ما بيقصّش نص رِقّة.</div>
          </div>
        </div>
      @endif
    @endif

    @if($impact && ($impact['ok'] ?? false))
      @php
        $rollM = (float) ($input['roll_length_m'] ?? 0);
        $planM = (float) ($input['spread_length_m'] ?? 0);
        $actM  = (float) ($input['actual_spread_length_m'] ?? 0);
        $pps   = (int) ($input['pieces_per_spread'] ?? 0);
      @endphp
      <div class="card">
        <div class="card-header"><span class="text-warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> تأثير فرق طول الفرشة</span></div>
        <div class="table-responsive">
          <table class="table table-sm kv mb-0 no-min">
            <tbody>
              <tr><th>الرِقّات المخططة من التوب</th>
                <td class="num">{{ $impact['planned_plies'] }}
                  @include('partials.fx', ['t' => 'الرِقّات المخططة', 'f' => 'أقل صحيح من (' . $n($rollM) . ' م ÷ ' . $n($planM) . ' م) = ' . $impact['planned_plies']])</td></tr>
              <tr><th>الرِقّات الفعلية</th>
                <td class="num">{{ $impact['actual_plies'] }}
                  @include('partials.fx', ['t' => 'الرِقّات الفعلية', 'f' => 'أقل صحيح من (' . $n($rollM) . ' م ÷ ' . $n($actM) . ' م) = ' . $impact['actual_plies']])</td></tr>
              <tr class="{{ $impact['lost_plies'] > 0 ? 'table-danger' : '' }}">
                <th>الرِقّات المفقودة من كل توب</th>
                <td class="num fw-bold">{{ $impact['lost_plies'] }}
                  @include('partials.fx', ['t' => 'الرِقّات المفقودة', 'f' => $impact['planned_plies'] . ' − ' . $impact['actual_plies'] . ' = ' . $impact['lost_plies']])</td></tr>
              <tr><th>القطع المفقودة من كل توب</th>
                <td class="num fw-bold">{{ number_format($impact['lost_pieces']) }}
                  @include('partials.fx', ['t' => 'القطع المفقودة', 'f' => $impact['lost_plies'] . ' رِقّة × ' . $pps . ' قطعة = ' . number_format($impact['lost_pieces']) . ' قطعة'])</td></tr>
              <tr><th>الفرق في الطول</th>
                <td class="num">{{ $n($impact['deviation_cm'], 2) }} سم
                  @include('partials.fx', ['t' => 'الفرق في الطول', 'f' => '(' . $n($actM) . ' م − ' . $n($planM) . ' م) × 100 = ' . $n($impact['deviation_cm'], 2) . ' سم'])</td></tr>
              <tr><th>نسبة الفاقد</th>
                <td class="num">{{ $n($impact['loss_pct'], 2) }}%
                  @include('partials.fx', ['t' => 'نسبة الفاقد', 'f' => $impact['lost_plies'] . ' ÷ ' . $impact['planned_plies'] . ' × 100 = ' . $n($impact['loss_pct'], 2) . '%'])</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    @endif

    @if(!$result)
      <div class="card"><div class="card-body">
        <div class="empty-state">
          <i class="bi bi-calculator ico" aria-hidden="true"></i>
          <div class="t">دخّل الأرقام واضغط «احسب».</div>
          <div class="hint">هتطلع لك وزن الرِقّة واستهلاك القطعة والقطع المتوقعة — وجنب كل رقم ⓘ بحسبته.</div>
        </div>
      </div></div>
    @endif
  </div>
</div>
@endsection
