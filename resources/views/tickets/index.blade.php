@extends('layouts.app')

@section('title', 'IT Helpdesk | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT Helpdesk</p>
        <h1>แจ้งปัญหา IT</h1>
        <p>งาน IT Helpdesk ถูกย้ายเข้า SmartFlow Workflow ใน WDC แล้ว เพื่อให้พนักงานและทีม IT ใช้คิวเดียวกัน</p>
    </div>
    <a class="btn btn-primary" href="{{ $itHelpdeskNavUrl ?? route('workflows.index') }}"><i class="bi bi-kanban"></i> เปิด IT Helpdesk Workflow</a>
</div>

<section class="panel">
    <div class="section-title">
        <h2>ศูนย์ Workflow</h2>
        <span class="status-pill">WDC-SF</span>
    </div>
    <p class="mb-0">ลิงก์เก่ายังใช้งานได้ และคำขอใหม่จะถูกส่งเข้าคิว IT Helpdesk กลางของ WDC โดยอัตโนมัติ</p>
</section>
@endsection
