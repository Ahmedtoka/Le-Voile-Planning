@extends('partials.doc_index')
@php
  // طابور القص: أوامر مرسلة للمصنع أو تحت القص — سجّل الفعلي
  $topTable = [
    'title' => 'أوامر عند المصنع — سجّل بيان القص الفعلي',
    'cols'  => ['رقم الأمر','المنتج','المصنع','متوقع','مقصوص','الحالة',''],
    'empty' => 'مفيش أوامر مستنية بيان قص دلوقتي.',
    'rows'  => ($awaitingWos ?? collect())->map(function ($w) {
        return '<td class="num fw-bold">'.e($w->wo_no).'</td>'
             . '<td>'.e(\Illuminate\Support\Str::limit($w->product_title, 30)).'</td>'
             . '<td>'.e($w->factory?->name ?? '—').'</td>'
             . '<td class="num">'.number_format((int)$w->target_qty).'</td>'
             . '<td class="num">'.number_format((int)$w->cut_pieces).'</td>'
             . '<td><span class="badge bg-'.$w->status_color.'">'.e($w->status_name).'</span></td>'
             . '<td><a href="'.route('cut-declarations.create', ['work_order_id' => $w->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">سجّل قص</a></td>';
    })->all(),
  ];
  $flow='prod'; $flowStep='cut';
  $sortable=['رقم البيان'=>'doc_no','التاريخ'=>'doc_date','القطع'=>'total_pieces','الانحراف'=>'variance_pct','الحالة'=>'status'];
  $intro = 'أول رقم فعلي بييجي من المصنع. أهم حقل فيه <b>طول الفرشة الفعلي</b> — '
         . 'لو المصنع فرش على أكتر من المتفق، بياكل رِقّة من كل توب وعدد القطع بينزل.';
  $searchHint = 'رقم البيان أو أمر الشغل…';
  $emptyText  = 'مفيش بيانات قص.';
  $footNote   = 'الانحراف المقبول 2% أخضر · لحد 4% أصفر · فوقها أحمر ولازم سبب مكتوب.';
  $editRoute='cut-declarations.edit';
  $cols=['رقم البيان','التاريخ','أمر الشغل','المصنع','طول الفرشة الفعلي','الرِقّات','القطع','الانحراف','الحالة'];
  $rowRenderer=function($r){
    $vb = $r->variance_flag ? '<span class="badge bg-'.(['ok'=>'success','warn'=>'warning','danger'=>'danger'][$r->variance_flag] ?? 'secondary').'">'.$r->variance_pct.'%</span>' : '—';
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td class="num">'.e($r->workOrder?->wo_no ?? '—').'</td>'
         . '<td>'.e($r->factory?->name ?? '—').'</td>'
         . '<td class="num">'.($r->actual_spread_length_m ?: '—').'</td>'
         . '<td class="num">'.($r->actual_plies ?: '—').'</td>'
         . '<td class="num">'.number_format((int)$r->total_pieces).'</td>'
         . '<td class="num">'.$vb.'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
