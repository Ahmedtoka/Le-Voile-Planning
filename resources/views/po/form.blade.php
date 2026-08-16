{{--
  ⚠ الصفحة المجمعة اتشالت.
  الدورة اتفصلت لتلات صفحات مستقلة:
    • po/create.blade.php  — طلب الشراء (التخطيط)      → «اطلب»
    • po/source.blade.php  — التسعير (المشتريات)        → «احفظ» / «نزّل للحسابات»
    • po/finance.blade.php — العلم (الحسابات)           → «علمت»
    • po/show.blade.php    — عرض عام للقراءة بس
  الملف ده موجود بس عشان الاستضافة مانعة الحذف — محدش بيستخدمه.
--}}
@extends('layouts.app')
@section('content')
  <div class="alert alert-warning">الصفحة دي اتنقلت — <a href="{{ route('purchase-orders.index') }}">ارجع لطلبات الشراء</a>.</div>
@endsection
