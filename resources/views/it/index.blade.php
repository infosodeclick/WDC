@extends('layouts.app')

@section('title', 'IT Helpdesk Portal | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT Helpdesk Portal</p>
        <h1>Dashboard IT</h1>
        <p>ติดตามคำขอ IT Helpdesk จาก SmartFlow Workflow เดียว ลดการเปิดงานซ้ำระหว่าง Ticket และคำขออนุมัติ</p>
    </div>
    <a class="btn btn-primary" href="{{ $itHelpdeskUrl }}"><i class="bi bi-plus-circle"></i> เปิดคำขอ IT</a>
</div>

<div class="metric-grid">
    <div class="metric-card"><span>งานใหม่</span><strong>{{ $newTickets }}</strong><small>ส่งคำขอแล้ว</small></div>
    <div class="metric-card"><span>งานค้าง</span><strong>{{ $pendingTickets }}</strong><small>ตรวจสอบ/รับเรื่อง/ดำเนินการ</small></div>
    <div class="metric-card"><span>งานเสร็จ</span><strong>{{ $doneTickets }}</strong><small>อนุมัติหรือปิดงานแล้ว</small></div>
</div>

<div class="item-list">
    @forelse($requests as $requestItem)
        <article class="list-card">
            <div class="meta-row">
                <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                <span>{{ $requestItem->template->name }} · {{ $requestItem->requester->employee?->department?->name }}</span>
            </div>
            <h3>{{ $requestItem->title }}</h3>
            <p>{{ $requestItem->details }}</p>
            <div class="meta-row">
                <span>ผู้แจ้ง: {{ $requestItem->requester->name }}</span>
                <span>{{ $requestItem->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @if($requestItem->legacy_reference)
                <div class="legacy-ref"><i class="bi bi-link-45deg"></i> เอกสารเดิม: {{ $requestItem->legacy_reference }}</div>
            @endif
            <a class="text-link" href="{{ route('workflows.index', ['template' => $requestItem->workflow_template_id, 'status' => $requestItem->status]) }}">เปิดใน Workflow</a>
        </article>
    @empty
        <div class="empty-state">ยังไม่มีงาน IT Helpdesk ค้างใน Workflow</div>
    @endforelse
</div>

{{ $requests->links() }}
@endsection
