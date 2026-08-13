@extends('partials.doc_index')
@php
  $createRoute='stock-additions.create'; $createLabel='إذن إضافة';
  $editRoute='stock-additions.edit'; $printRoute='stock-additions.print';
  $cols=['رقم الإذن','المسلسل الورقي','التاريخ','المورد','المخزن','رقم الرسالة','الأتواب','الكمية','حالة الحوض','الحالة'];
  $rowRenderer=function($r){
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td>'.e($r->supplier?->name ?? '—').'</td>'
         . '<td>'.e($r->warehouse?->name ?? '—').'</td>'
         . '<td class="num">'.e($r->consignment_no ?: '—').'</td>'
         . '<td class="num">'.(int)$r->total_rolls.'</td>'
         . '<td class="num">'.number_format((float)$r->total_qty,2).'</td>'
         . '<td>'.($r->consignment
              ? '<span class="badge bg-'.$r->consignment->status_color.'">'.e($r->consignment->status_name).'</span>'
              : '<span class="hint">—</span>').'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
