@extends('partials.doc_index')
@php
  $createRoute='inspections.create'; $createLabel='تقرير فحص';
  $editRoute='inspections.edit'; $printRoute='inspections.print';
  $cols=['رقم التقرير','المسلسل','التاريخ','الحوض','اللون','العيّنة','أقل عرض','متوسط العرض','نسبة العيوب','النتيجة','الحالة'];
  $rowRenderer=function($r){
    $alert = $r->width_alert ? ' <i class="bi bi-exclamation-triangle-fill text-danger" title="فرق العرض كبير"></i>' : '';
    $small = $r->sample_too_small ? ' <i class="bi bi-info-circle text-warning" title="عيّنة صغيرة"></i>' : '';
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td class="num">'.e($r->consignment?->consignment_no ?? '—').'</td>'
         . '<td>'.e($r->color?->code ?? '—').'</td>'
         . '<td class="num">'.(int)$r->sampled_rolls.'/'.(int)$r->total_rolls.$small.'</td>'
         . '<td class="num fw-bold">'.($r->min_width_cm ?: '—').$alert.'</td>'
         . '<td class="num">'.($r->avg_width_cm ?: '—').'</td>'
         . '<td class="num">'.($r->defect_pct ?: '—').'</td>'
         . '<td>'.e($r->result_name).'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
