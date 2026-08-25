@extends('partials.doc_index')
@php
  // طابور المعمل: اللي خلص فحص ومستني قراءات
  $topTable = [
    'title' => 'أحواض خلصت فحص ومستنية المعمل — دوس «سجّل قراءات»',
    'cols'  => ['رقم الرسالة','الصنف','اللون','الكمية','الأتواب','أقل عرض',''],
    'empty' => 'مفيش أحواض مستنية معمل دلوقتي.',
    'rows'  => ($awaiting ?? collect())->map(function ($c) {
        return '<td class="num fw-bold">'.e($c->consignment_no).'</td>'
             . '<td>'.e($c->fabricType?->name ?? '—').'</td>'
             . '<td>'.e($c->color?->code ?? '—').'</td>'
             . '<td class="num">'.rtrim(rtrim(number_format((float)$c->total_kg,2),'0'),'.').' كجم</td>'
             . '<td class="num">'.(int)$c->rolls_count.'</td>'
             . '<td class="num">'.($c->min_width_cm ?: '—').'</td>'
             . '<td><a href="'.route('lab-reports.create', ['consignment_id' => $c->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">سجّل قراءات</a></td>';
    })->all(),
  ];
  $flow='fabric'; $flowStep='lab';
  $sortable=['رقم التقرير'=>'doc_no','التاريخ'=>'doc_date','متوسط البنشر'=>'avg_gsm','الحالة'=>'status'];
  $intro = 'وزن البُنشر هو الوزن المعياري للقماش (جرام/م²) — يعني سُمكه. بيطلع وينزل جوه نفس التوب، '
         . 'فبناخد قراءات متعددة ونشتغل على المتوسط. الرقم ده هو اللي بتتحسب بيه أوزان الرِقّات واستهلاك القطعة.';
  $searchHint = 'رقم التقرير أو المسلسل أو الرسالة…';
  $emptyText  = 'مفيش تقارير معمل.';
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
