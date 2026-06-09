@extends('layouts.app')

@section('title', 'IT Helpdesk Portal | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT Helpdesk Portal</p>
        <h1>Dashboard IT</h1>
        <p>ติดตาม Ticket ใหม่ ค้าง และเสร็จสิ้น พร้อมกรองตามแผนก ผู้แจ้ง และวันที่</p>
    </div>
</div>

<div class="metric-grid">
    <div class="metric-card"><span>Ticket ใหม่</span><strong>{{ $newTickets }}</strong><small>เปิดงาน</small></div>
    <div class="metric-card"><span>Ticket ค้าง</span><strong>{{ $pendingTickets }}</strong><small>รับเรื่อง/กำลังดำเนินการ</small></div>
    <div class="metric-card"><span>Ticket เสร็จ</span><strong>{{ $doneTickets }}</strong><small>ปิดงานแล้ว</small></div>
</div>

<div class="item-list">
    @foreach($tickets as $ticket)
        <article class="list-card">
            <div class="meta-row">
                <span class="status-pill status-{{ $ticket->status }}">{{ $ticket->statusLabel() }}</span>
                <span>{{ $ticket->requestTypeLabel() }} · {{ $ticket->reporter->employee?->department?->name }}</span>
            </div>
            <h3>{{ $ticket->title }}</h3>
            <p>{{ $ticket->details }}</p>
            <div class="meta-row">
                <span>ผู้แจ้ง: {{ $ticket->reporter->name }}</span>
                <span>{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @if($ticket->legacy_document_ref)
                <div class="legacy-ref"><i class="bi bi-link-45deg"></i> เอกสารเดิม: {{ $ticket->legacy_document_ref }}</div>
            @endif
        </article>
    @endforeach
</div>

{{ $tickets->links() }}
@endsection
