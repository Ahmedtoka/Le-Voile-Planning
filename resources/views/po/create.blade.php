@extends('layouts.app')
@section('content')

<div class="note-box mb-3">
  <i class="bi bi-info-circle" aria-hidden="true"></i>
  اكتب الأصناف والكميات ونسبة الزيادة واضغط <b>«اطلب»</b> — الطلب ينزل للمشتريات
  تلقائيًا ويوصلهم إشعار. التاريخ واسمك بيتسجلوا لوحدهم.
</div>

<form method="post" action="{{ route('purchase-orders.store') }}">
  @csrf
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>طلب شراء جديد</span>
      <a href="{{ route('purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary py-0">رجوع</a>
    </div>
    <div class="card-body">
      <label class="form-label">سبب الطلب / ملاحظات التخطيط</label>
      <input name="planning_note" class="form-control form-control-sm" value="{{ old('planning_note') }}"
             placeholder="مثال: تغطية فوركاست الربع الأول — الأساسيات">

      <div class="table-responsive mt-3">
        <table class="table table-sm line-table mb-0">
          <thead><tr>
            <th style="width:35px">م</th><th style="width:170px">كود اللون</th><th>اسم الصنف</th>
            <th style="width:100px">الكمية</th><th style="width:80px">الوحدة</th>
            <th style="width:100px">نسبة الزيادة %</th><th>ملاحظات</th><th style="width:40px"></th>
          </tr></thead>
          <tbody id="lines">
            @foreach(old('lines', []) as $i => $l) @include('po.line', ['i'=>$i,'l'=>$l]) @endforeach
            @if(!count(old('lines', []))) @include('po.line', ['i'=>0,'l'=>[]]) @endif
          </tbody>
        </table>
      </div>
      <button type="button" class="btn btn-sm btn-outline-plum mt-2" onclick="LV.add('lineTpl','lines')">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> صنف
      </button>
    </div>
    <div class="card-footer bg-white d-flex gap-2 align-items-center">
      <button class="btn btn-plum btn-sm px-4"><i class="bi bi-send" aria-hidden="true"></i> اطلب</button>
      <span class="hint">هيرجعك على طلبات الشراء، والطلب يظهر عند المشتريات فورًا.</span>
    </div>
  </div>
</form>

<template id="lineTpl">@include('po.line', ['i'=>'__IDX__','l'=>[],'tpl'=>true])</template>
@include('partials.lines_js', ['startIndex' => max(count(old('lines', [])), 1)])
@endsection
