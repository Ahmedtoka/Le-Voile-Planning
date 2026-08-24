@extends('partials.doc_index')
@php
  $flow='fabric'; $flowStep='inspection';
  $sortable=['رقم التقرير'=>'doc_no','المسلسل'=>'paper_serial','التاريخ'=>'doc_date','أقل عرض'=>'min_width_cm','نسبة العيوب'=>'defect_pct','النتيجة'=>'result','الحالة'=>'status'];
  // طابور الفحص: القماش اللي وصل ولسه ما اتفحصش — بزرار «افحص» بيفتح ورقة الفحص متملية
  $topTable = [
    'title' => 'قماش وصل ومستني الفحص — دوس «افحص» وورقة الفحص تتملى بأتوابه',
    'cols'  => ['رقم الرسالة','الصنف','اللون','المورد','وصل','الأتواب','الباقي على الطلب','وصل إمتى',''],
    'empty' => 'مفيش قماش مستني فحص دلوقتي.',
    'rows'  => ($awaiting ?? collect())->map(function ($c) {
        $po   = $c->purchaseOrder;
        $rem  = 0; $unit = '';
        if ($po) {
          foreach ($po->lines as $l) {
            $left = max(0, (float) $l->min_allowed_qty - (float) $l->received_qty);
            if ($left > 0.0001) { $rem += $left; $unit = $unit ?: $l->unit; }
          }
        }
        $eta = $po?->remainder_eta;
        $remCell = $rem > 0
            ? '<span class="text-danger fw-bold">باقي '.rtrim(rtrim(number_format($rem,3),'0'),'.').' '.e($unit).'</span>'
              .'<div class="hint">'.($eta ? 'متوقع '.$eta->format('Y-m-d') : 'الموعد مش محدد').'</div>'
            : '<span class="text-success">الطلب اكتمل</span>';

        return '<td class="num fw-bold">'.e($c->consignment_no).'</td>'
             . '<td>'.e($c->fabricType?->name ?? '—').'</td>'
             . '<td>'.e($c->color?->code ?? '—').'</td>'
             . '<td>'.e($c->supplier?->name ?? '—').'</td>'
             . '<td class="num fw-bold">'.rtrim(rtrim(number_format((float)$c->total_kg,2),'0'),'.').'</td>'
             . '<td class="num">'.(int)$c->rolls_count.'</td>'
             . '<td>'.$remCell.'</td>'
             . '<td class="num">'.e($c->arrival_date?->format('Y-m-d')).'</td>'
             . '<td><a href="'.route('inspections.create', ['consignment_id' => $c->id]).'"'
                 .' class="btn btn-sm btn-plum py-0">افحص</a></td>';
    })->all(),
  ];
  $intro = 'التقرير بيعمل حاجتين: <b>الجرد</b> (كام توب موجود فعلًا مقابل اللي المورد قال عليه) '
         . 'و<b>القياس</b> (طول وعرض وعيوب كل توب مفحوص). المخرج المهم هو <b>أقل عرض</b> — عليه بيتبني الماركر.';
  $searchHint = 'رقم التقرير أو المسلسل أو الرسالة…';
  $emptyText  = 'مفيش تقارير فحص.';
  $footNote   = 'علامة التحذير جنب الجرد معناها فرق بين المعدود والمصرّح.';
  $createRoute='inspections.create'; $createLabel='تقرير فحص';
  $editRoute='inspections.edit'; $printRoute='inspections.print';
  $cols=['رقم التقرير','المسلسل','التاريخ','الحوض','اللون','الجرد','العيّنة','أقل عرض','نسبة العيوب','النتيجة','الحالة'];
  $rowRenderer=function($r){
    $alert = $r->width_alert
        ? ' <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i>'
          . '<span class="visually-hidden">فرق العرض كبير</span>'
        : '';
    $small = $r->sample_too_small
        ? ' <i class="bi bi-info-circle text-warning" aria-hidden="true"></i>'
          . '<span class="visually-hidden">عيّنة صغيرة</span>'
        : '';
    return '<td class="num fw-bold">'.e($r->doc_no).'</td>'
         . '<td class="num">'.e($r->paper_serial ?: '—').'</td>'
         . '<td class="num">'.e($r->doc_date?->format('Y-m-d')).'</td>'
         . '<td class="num">'.e($r->consignment?->consignment_no ?? '—').'</td>'
         . '<td>'.e($r->color?->code ?? '—').'</td>'
         . '<td class="num'.($r->rolls_variance != 0 ? ' text-danger fw-bold' : '').'">'
              .(int)$r->counted_rolls.'/'.(int)$r->declared_rolls
              .($r->rolls_variance != 0
                  ? ' <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>'
                    . '<span class="visually-hidden">فرق في الجرد</span>'
                  : '').'</td>'
         . '<td class="num">'.(int)$r->sampled_rolls.'/'.(int)$r->total_rolls.$small.'</td>'
         . '<td class="num fw-bold">'.($r->min_width_cm ?: '—').$alert.'</td>'
         . '<td class="num">'.($r->defect_pct ?: '—').'</td>'
         . '<td>'.e($r->result_name).'</td>'
         . '<td><span class="badge bg-'.$r->status_color.'">'.e($r->status_label).'</span></td>';
  };
@endphp
