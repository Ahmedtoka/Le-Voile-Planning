@extends('partials.doc_index')
@php
  // طابور الصرف: أوامر شغل معتمدة/مرسلة وفيها خامات لسه ما اتصرفتش بالكامل
  $topTable = [
    'title' => 'أوامر شغل مستنية صرف خام — دوس «اصرف» والإذن يتملى من الأمر',
    'cols'  => ['رقم الأمر','المنتج','المصنع','المخطط','المنصرف','الباقي',''],
    'empty' => 'مفيش أوامر مستنية صرف دلوقتي.',
    'rows'  => ($awaitingWos ?? collect())->map(function ($w) {
        $planned = (float) $w->fabrics->sum('planned_qty');
        // العمود المخزّن — نفس اللي الطابور بيتحسب بيه، فالرقم اللي فوق = اللي تحت
        $issued  = (float) $w->fabrics->sum('issued_qty');
        $left    = max(0, $planned - $issued);
        return '<td class="num fw-bold">'.e($w->wo_no).'</td>'
             . '<td>'.e(\Illuminate\Support\Str::limit($w->product_title, 30)).'</td>'
             . '<td>'.e($w->factory?->name ?? '—').'</td>'
             . '<td class="num">'.rtrim(rtrim(number_format($planned,2),'0'),'.').'</td>'
             . '<td class="num">'.rtrim(rtrim(number_format($issued,2),'0'),'.').'</td>'
             . '<td class="num fw-bold text-danger">'.rtrim(rtrim(number_format($left,2),'0'),'.').'</td>'
             . '<td><a href="'.route('material-issues.create', ['work_order_id' => $w->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">اصرف</a></td>';
    })->all(),
  ];
  $flow='prod'; $flowStep='issue';
  $sortable=['رقم الإذن'=>'doc_no','المسلسل'=>'paper_serial','التاريخ'=>'doc_date','الكمية'=>'total_qty','الحالة'=>'status'];
  $intro = 'الحلقة بين أمر الشغل والمصنع. الورقة الواحدة بتصرف خامات <b>لأكتر من أمر شغل</b> '
         . 'وكل سطر بيقول الخامة من أي رسالة وبأي لون وكام توب. '
         . 'حفظ الإذن هو اللي بيخصم فعليًا من رصيد الحوض — مفيش اعتماد بعده.';
  $searchHint = 'رقم الإذن أو المسلسل أو المنصرف إليه…';
  $emptyText  = 'مفيش أذون صرف. ابدأ من أمر شغل معتمد.';
  $createRoute='material-issues.create'; $createLabel='إذن صرف';
  $editRoute='material-issues.edit'; $printRoute='material-issues.print';
  $cols=['رقم الإذن','المسلسل','التاريخ','منصرف إلى','المخزن','أوامر الشغل','الأتواب','الكمية','الحالة'];
  $rowRenderer=function($r){
    $wos = $r->lines->pluck('workOrder.wo_no')->filter()->unique()->implode('، ');
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td>'.e($r->issued_to ?: ($r->factory?->name ?? '—')).'</td>'
         . '<td>'.e($r->warehouse?->name ?? '—').'</td>'
         . '<td class="num hint">'.e($wos ?: '—').'</td>'
         . '<td class="num">'.(int)$r->total_rolls.'</td>'
         . '<td class="num">'.number_format((float)$r->total_qty,2).'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
