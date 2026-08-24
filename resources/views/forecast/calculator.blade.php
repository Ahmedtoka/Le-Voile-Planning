@extends('layouts.app')
@section('content')
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-calculator" aria-hidden="true"></i> مدخلات الحسبة</div>
      <form method="get" class="card-body">
        <div class="row g-2">
          <div class="col-6"><label class="form-label req">عرض القماش (سم)</label>
            <input type="number" step="0.01" name="width_cm" class="form-control form-control-sm" value="{{ $input['width_cm'] ?? 185 }}" required>
            <div class="hint">استخدم <b>أقل</b> عرض في الحوض</div></div>
          <div class="col-6"><label class="form-label req">وزن البنشر (جم/م²)</label>
            <input type="number" step="0.01" name="gsm" class="form-control form-control-sm" value="{{ $input['gsm'] ?? 195 }}" required></div>
          <div class="col-6"><label class="form-label req">طول الفرشة (م)</label>
            <input type="number" step="0.001" name="spread_length_m" class="form-control form-control-sm" value="{{ $input['spread_length_m'] ?? 3.07 }}" required></div>
          <div class="col-6"><label class="form-label req">عدد القطع في الفرشة</label>
            <input type="number" name="pieces_per_spread" class="form-control form-control-sm" value="{{ $input['pieces_per_spread'] ?? 10 }}" required></div>
          <div class="col-6"><label class="form-label">طول التوب (م)</label>
            <input type="number" step="0.01" name="roll_length_m" class="form-control form-control-sm" value="{{ $input['roll_length_m'] ?? 50 }}"></div>
          <div class="col-6"><label class="form-label">الكمية المتاحة (كجم)</label>
            <input type="number" step="0.001" name="available_kg" class="form-control form-control-sm" value="{{ $input['available_kg'] ?? 800 }}"></div>
          <div class="col-12"><hr class="my-2"></div>
          <div class="col-6"><label class="form-label">طول الفرشة الفعلي (م)</label>
            <input type="number" step="0.001" name="actual_spread_length_m" class="form-control form-control-sm" value="{{ $input['actual_spread_length_m'] ?? '' }}">
            <div class="hint">لو المصنع فرش على طول مختلف</div></div>
        </div>
        <button class="btn btn-plum btn-sm mt-3 w-100">احسب</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    @if($result)
      @if(!$result['ok'])
        <div class="alert alert-danger">{!! implode('<br>', $result['errors']) !!}</div>
      @else
        <div class="calc-box mb-3">
          <div class="fw-bold mb-2">النتيجة</div>
          <div class="kv"><span>وزن الرِقّة الواحدة</span><b>{{ $result['ply_weight_kg'] }} كجم</b></div>
          <div class="kv"><span>استهلاك القطعة</span><b>{{ $result['g_per_piece'] }} جم ({{ $result['kg_per_piece'] }} كجم)</b></div>
          <div class="kv"><span>عدد الرِقّات من التوب</span><b>{{ $result['plies_per_roll'] ?? '—' }}</b></div>
          <div class="kv"><span>الفاقد في آخر التوب</span><b>{{ $result['waste_per_roll_m'] ?? '—' }} م</b></div>
          <div class="kv"><span>إجمالي الفرشات المتوقعة</span><b>{{ number_format($result['expected_plies']) }}</b></div>
          <div class="kv"><span>القطع المتوقعة</span><b class="text-success">{{ number_format($result['expected_pieces']) }}</b></div>
        </div>

        <div class="card mb-3">
          <div class="card-header">المعادلات المستخدمة</div>
          <div class="card-body small" style="direction:ltr;text-align:left;font-family:monospace">
            plies&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= FLOOR({{ $result['inputs']['roll_length_m'] ?? '—' }} / {{ $result['inputs']['spread_length_m'] }}) = {{ $result['plies_per_roll'] ?? '—' }}<br>
            ply_weight&nbsp;= {{ $result['inputs']['spread_length_m'] }} × ({{ $result['inputs']['width_cm'] }}/100) × {{ $result['inputs']['gsm'] }} / 1000 = {{ $result['ply_weight_kg'] }} kg<br>
            kg_per_pc&nbsp;&nbsp;= {{ $result['ply_weight_kg'] }} / {{ $result['inputs']['pieces_per_spread'] }} = {{ $result['kg_per_piece'] }} kg<br>
            pieces&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;= {{ $result['inputs']['available_kg'] }} / {{ $result['kg_per_piece'] }} = {{ $result['expected_pieces'] }}
          </div>
        </div>
      @endif
    @endif

    @if($impact && ($impact['ok'] ?? false))
      <div class="card">
        <div class="card-header text-warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> تأثير فرق طول الفرشة</div>
        <table class="table table-sm mb-0">
          <tr><th>الرِقّات المخططة من التوب</th><td class="num">{{ $impact['planned_plies'] }}</td></tr>
          <tr><th>الرِقّات الفعلية</th><td class="num">{{ $impact['actual_plies'] }}</td></tr>
          <tr class="{{ $impact['lost_plies'] > 0 ? 'table-danger' : '' }}">
            <th>الرِقّات المفقودة من كل توب</th><td class="num fw-bold">{{ $impact['lost_plies'] }}</td></tr>
          <tr><th>القطع المفقودة من كل توب</th><td class="num fw-bold">{{ number_format($impact['lost_pieces']) }}</td></tr>
          <tr><th>الفرق في الطول</th><td class="num">{{ $impact['deviation_cm'] }} سم</td></tr>
          <tr><th>نسبة الفاقد</th><td class="num">{{ $impact['loss_pct'] }}%</td></tr>
        </table>
      </div>
    @endif

    @if(!$result)
      <div class="card"><div class="card-body text-muted text-center py-5">
        دخّل الأرقام على الشمال واضغط "احسب".
      </div></div>
    @endif
  </div>
</div>
@endsection
