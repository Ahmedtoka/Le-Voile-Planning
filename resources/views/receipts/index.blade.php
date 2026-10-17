@extends('partials.doc_index')
@php
  // طابور الاستلام: فحص + معمل خلّصوا ⇒ الرسالة جاهزة تدخل المخزن
  $topTable = [
    'title' => 'رسايل خلّصت فحص ومعمل — دوس «استلم» وشوف السامري قبل ما تدخل المخزن',
    'cols'  => ['رقم الرسالة','الصنف','اللون','الكمية','الأتواب','أقل عرض','البنشر',''],
    'empty' => 'مفيش رسايل جاهزة للاستلام دلوقتي.',
    'rows'  => ($awaiting ?? collect())->map(function ($c) {
        return '<td class="num fw-bold">'.e($c->consignment_no).'</td>'
             . '<td>'.e($c->fabricType?->name ?? '—').'</td>'
             . '<td>'.e($c->color?->code ?? '—').'</td>'
             . '<td class="num">'.rtrim(rtrim(number_format((float)$c->total_kg,2),'0'),'.').' كجم</td>'
             . '<td class="num">'.(int)$c->rolls_count.'</td>'
             . '<td class="num">'.($c->min_width_cm ?: '—').'</td>'
             . '<td class="num">'.($c->avg_gsm ?: '—').'</td>'
             . '<td><a href="'.route('goods-receipts.create', ['consignment_id' => $c->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">استلم</a></td>';
    })->all(),
  ];
  $flow='fabric'; $flowStep='receipt';
  $sortable=['رقم الإذن'=>'doc_no','المسلسل الورقي'=>'paper_serial','التاريخ'=>'doc_date','الكمية'=>'total_qty','الأتواب'=>'total_rolls','الحالة'=>'status'];
  $intro = 'آخر خطوة في دورة القماش. الرسايل اللي خلّصت فحص ومعمل بتظهر فوق — '
         . 'دوس «استلم» تشوف <b>سامري كامل</b> (المورد، طلب الشراء، إذن الإضافة، الفحص، المعمل، الأصناف) '
         . 'من غير ما تكتب حاجة. الحفظ بيدخّل القماش المخزن ويحدّث الكمية في طلب الشراء.';
  $searchHint = 'رقم الإذن أو المسلسل…';
  $emptyText  = 'مفيش أذون استلام. لازم تكون فيه رسالة خلّصت فحص ومعمل الأول.';
  $createRoute = null;
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
