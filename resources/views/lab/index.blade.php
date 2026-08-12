@extends('partials.doc_index')
@php
  $createRoute='lab-reports.create'; $createLabel='تقرير معمل';
  $editRoute='lab-reports.edit'; $printRoute='lab-reports.print';
  $cols=['رقم التقرير','المسلسل','التاريخ','الحوض','اللون','متوسط البنشر','انكماش طول %','انكماش عرض %','مطابقة اللون','الحالة'];
  $rowRenderer=function($r){
    $cm = $r->color_match_ok === null ? '—' : ($r->color_match_ok ? '<span class="badge bg-success">مطابق</span>' : '<span class="badge bg-danger">غير مطابق</span>');
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td class="num">'.e($r->consignment?->consignment_no ?? '—').'</td>'
         . '<td>'.e($r->color?->code ?? '—').'</td>'
         . '<td class="num fw-bold">'.($r->avg_gsm ?: '—').'</td>'
         . '<td class="num">'.($r->avg_shrink_len_pct ?? '—').'</td>'
         . '<td class="num">'.($r->avg_shrink_width_pct ?? '—').'</td>'
         . '<td>'.$cm.'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
