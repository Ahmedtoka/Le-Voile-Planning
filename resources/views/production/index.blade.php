@extends('partials.doc_index')
@php
  $editRoute='production-receipts.edit';
  $cols=['رقم الاستلام','التاريخ','أمر الشغل','المصنع','المخزن','القطع','الحالة'];
  $rowRenderer=function($r){
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td class="num">'.e($r->workOrder?->wo_no ?? '—').'</td>'
         . '<td>'.e($r->factory?->name ?? '—').'</td>'
         . '<td>'.e($r->warehouse?->name ?? '—').'</td>'
         . '<td class="num">'.number_format((int)$r->total_pieces).'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
