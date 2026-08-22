@extends('partials.doc_index')
@php
  // الطلبات اللي اتسعّرت ومستنية القماش يوصل — زرار «استلم» بيفتح إذن إضافة متملي
  $topTable = [
    'title' => 'طلبات شراء مستنية الاستلام — دوس «استلم» والإذن يتملى من الطلب',
    'cols'  => ['رقم الطلب','الحالة','المورد','توريد متوقع','مستلم',''],
    'empty' => 'مفيش طلبات مستنية استلام.',
    'rows'  => ($awaitingPos ?? collect())->map(function ($p) {
        $eta  = $p->delivery_date;
        $late = $eta && $eta->isPast();
        $tot  = (float) $p->lines->sum('qty');
        $rec  = (float) $p->lines->sum('received_qty');
        $pct  = $tot > 0 ? (int) round($rec / $tot * 100) : 0;
        $badge = $p->stage === 'receiving'
            ? '<span class="badge bg-info">جزئي — استلم '.$pct.'%</span>'
            : '<span class="badge bg-secondary">لسه موصلش</span>';
        return '<td class="num fw-bold">'.e($p->po_no).'</td>'
             . '<td>'.$badge.'</td>'
             . '<td>'.e($p->supplier?->name ?? '—').'</td>'
             . '<td class="num'.($late ? ' text-danger fw-bold' : '').'">'
                 .($eta ? $eta->format('Y-m-d').($late ? ' — متأخر' : '') : '—').'</td>'
             . '<td class="num">'.rtrim(rtrim(number_format($rec,2),'0'),'.').' / '
                 .rtrim(rtrim(number_format($tot,2),'0'),'.').'</td>'
             . '<td><a href="'.route('stock-additions.create', ['purchase_order_id' => $p->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">'.($p->stage === 'receiving' ? 'استلم الباقي' : 'استلم').'</a></td>';
    })->all(),
  ];
  $intro = 'أول مستند في دورة القماش. اعتماده بيولّد <b>الحوض (الرسالة)</b> ويدخّل الكمية المخزن '
         . '<b>محجوزة تحت الفحص</b> — ممنوع تشغيلها. الإفراج بيحصل بإذن الاستلام الخام بعد الفحص والمعمل.';
  $searchHint = 'رقم الإذن أو المسلسل أو الرسالة…';
  $emptyText  = 'مفيش أذون إضافة. ابدأ بواحد لما يوصل قماش.';
  $footNote   = 'حالة الحوض بتوريك الحوض واقف فين في الدورة.';
  $createRoute='stock-additions.create'; $createLabel='إذن إضافة';
  // استلام الحاويات: بدون دورة فحص — البضاعة بتدخل مُفرَج عنها فورًا
  $extraActions = '<a href="'.route('stock-additions.create', ['type' => 'container']).'"'
                .' class="btn btn-sm btn-outline-plum"><i class="bi bi-box-seam"></i> استلام حاويات</a>';
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
