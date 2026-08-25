@extends('partials.doc_index')
@php
  // طابور الاستلام: مقصوص أكتر من المستلم ⇒ فيه قطع على المصنع
  $topTable = [
    'title' => 'أوامر فيها قطع على المصنع — استلم الإنتاج',
    'cols'  => ['رقم الأمر','المنتج','المصنع','مقصوص','مستلم','لسه عنده',''],
    'empty' => 'مفيش قطع مستنية استلام دلوقتي.',
    'rows'  => ($awaitingWos ?? collect())->map(function ($w) {
        return '<td class="num fw-bold">'.e($w->wo_no).'</td>'
             . '<td>'.e(\Illuminate\Support\Str::limit($w->product_title, 30)).'</td>'
             . '<td>'.e($w->factory?->name ?? '—').'</td>'
             . '<td class="num">'.number_format((int)$w->cut_pieces).'</td>'
             . '<td class="num">'.number_format((int)$w->received_pieces).'</td>'
             . '<td class="num fw-bold text-danger">'.number_format($w->outstanding_pieces).'</td>'
             . '<td><a href="'.route('production-receipts.create', ['work_order_id' => $w->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">استلم</a></td>';
    })->all(),
  ];
  $flow='prod'; $flowStep='receive';
  $sortable=['رقم الاستلام'=>'doc_no','التاريخ'=>'doc_date','القطع'=>'total_pieces','الحالة'=>'status'];
  $intro = 'استلام المنتج التام من المصنع — جزئي ومتكرر. كل استلام بيتخصم من المقصوص، '
         . 'ولما المتبقي يوصل صفر أمر الشغل بيتقفل تلقائيًا.';
  $searchHint = 'رقم الاستلام أو أمر الشغل…';
  $emptyText  = 'مفيش استلامات إنتاج.';
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
