@extends('layouts.app')

@section('title', 'แจ้งปัญหา IT | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT Helpdesk</p>
        <h1>แจ้งปัญหา IT</h1>
        <p>เปิด Ticket ติดตามสถานะ และตอบกลับกับทีม IT</p>
    </div>
</div>

<section class="panel">
    <div class="section-title">
        <h2>เปิด Ticket ใหม่</h2>
        @if($smartflowHelpdesk)
            <a href="{{ $smartflowHelpdesk->url }}" target="_blank" rel="noopener">เปิด SmartFlow เดิม</a>
        @endif
    </div>
    <form method="post" action="{{ route('tickets.store') }}" class="form-grid">
        @csrf
        <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="title" value="{{ old('title') }}" required></label>
        <label><span>ประเภทคำขอ</span>
            <select class="form-select" name="request_type">
                @foreach($requestTypes as $key => $label)
                    <option value="{{ $key }}" @selected(old('request_type', 'general') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label><span>ระดับความเร่งด่วน</span>
            <select class="form-select" name="urgency">
                <option value="low" @selected(old('urgency') === 'low')>ต่ำ</option>
                <option value="normal" @selected(old('urgency', 'normal') === 'normal')>ปกติ</option>
                <option value="high" @selected(old('urgency') === 'high')>สูง</option>
                <option value="critical" @selected(old('urgency') === 'critical')>วิกฤต</option>
            </select>
        </label>
        <label><span>เลขอ้างอิงเอกสารเดิม</span><input class="form-control" name="legacy_document_ref" value="{{ old('legacy_document_ref') }}" placeholder="เช่น REF: #2606815"></label>
        <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="details" rows="3" required>{{ old('details') }}</textarea></label>
        <p class="form-help span-3">ประเภทคำขอนี้อ้างอิงจาก SmartFlow IT Helpdesk เดิม: VPN, SAP B1, AI-CRM, Remote Access และยกเลิกเอกสาร เพื่อให้ย้าย workflow เข้าระบบใหม่ได้ทีละส่วน</p>
        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i> เปิด Ticket</button>
    </form>
</section>

<div class="filter-row">
    @foreach(['' => 'ทั้งหมด', 'open' => 'เปิดงาน', 'accepted' => 'รับเรื่องแล้ว', 'in_progress' => 'กำลังดำเนินการ', 'done' => 'เสร็จสิ้น'] as $key => $label)
        <a class="filter-chip {{ $status === $key ? 'active' : '' }}" href="{{ route('tickets.index', $key === '' ? [] : ['status' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="item-list">
    @foreach($tickets as $ticket)
        <article class="list-card">
            <div class="meta-row">
                <span class="status-pill status-{{ $ticket->status }}">{{ $ticket->status }}</span>
                <span>{{ $requestTypes[$ticket->request_type] ?? $ticket->request_type }} · {{ $ticket->urgency }}</span>
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

            @if($canManage)
                <form class="inline-form" method="post" action="{{ route('tickets.status', $ticket) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                        @foreach(['open' => 'เปิดงาน', 'accepted' => 'รับเรื่องแล้ว', 'in_progress' => 'กำลังดำเนินการ', 'done' => 'เสร็จสิ้น'] as $key => $label)
                            <option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">อัปเดต</button>
                </form>
            @endif

            <div class="comments">
                @foreach($ticket->comments as $comment)
                    <div><strong>{{ $comment->user->name }}</strong> {{ $comment->body }}</div>
                @endforeach
            </div>
            <form class="inline-form" method="post" action="{{ route('tickets.comments.store', $ticket) }}">
                @csrf
                <input class="form-control form-control-sm" name="body" placeholder="ตอบกลับ Ticket" required>
                <button class="btn btn-sm btn-outline-secondary">ส่ง</button>
            </form>
        </article>
    @endforeach
</div>

{{ $tickets->links() }}
@endsection
