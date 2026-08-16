@extends('partials.doc_index')
@php
  $intro = 'الحلقة بين أمر الشغل والمصنع. الورقة الواحدة بتصرف خامات <b>لأكتر من أمر شغل</b> '
         . 'وكل سطر بيقول الخامة من أي رسالة وبأي لون وكام توب. '
         . 'اعتماد الإذن هو اللي بيخصم فعليًا من رصيد الحوض.';
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
