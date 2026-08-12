@extends('partials.doc_index')
@php
  $createRoute = 'goods-receipts.create'; $createLabel = 'إذن استلام';
  $editRoute = 'goods-receipts.edit'; $printRoute = 'goods-receipts.print';
  $cols = ['رقم الإذن','المسلسل الورقي','التاريخ','المورد','المخزن','الرسالة','الكمية','الأتواب','الحالة'];
  $rowRenderer = function ($r) {
      return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
           . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
           . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
           . '<td>'.e($r->supplier?->name ?? '—').'</td>'
           . '<td>'.e($r->warehouse?->name ?? '—').'</td>'
           . '<td class="num">'.e($r->consignment?->consignment_no ?? '—').'</td>'
           . '<td class="num">'.number_format((float)$r->total_qty, 2).'</td>'
           . '<td class="num">'.(int)$r->total_rolls.'</td>'
           . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
