@extends('layouts.app')

@section('title', 'Dashboard | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Dashboard</p>
        <h1>สวัสดี คุณ{{ $user->name }}</h1>
        <p>ภาพรวมงานสำคัญของวันนี้สำหรับ {{ $user->employee?->department?->name }}</p>
    </div>
    <div class="role-badge">{{ $user->role?->name }}</div>
</div>

<div class="metric-grid">
    <a class="metric-card" href="{{ route('announcements.index') }}">
        <span>ประกาศใหม่</span>
        <strong>{{ $newAnnouncements }}</strong>
        <small>รายการใน 7 วันล่าสุด</small>
    </a>
    <a class="metric-card" href="{{ route('tickets.index') }}">
        <span>Ticket ค้าง</span>
        <strong>{{ $pendingTickets }}</strong>
        <small>ยังไม่เสร็จสิ้น</small>
    </a>
    <a class="metric-card" href="{{ route('knowledge.index') }}">
        <span>วิดีโอใหม่</span>
        <strong>{{ $newVideos }}</strong>
        <small>อัปเดตใน 14 วันล่าสุด</small>
    </a>
</div>

<div class="quick-actions">
    <a class="btn btn-primary" href="{{ route('announcements.index') }}"><i class="bi bi-megaphone"></i> ดูประกาศ</a>
    <a class="btn btn-outline-primary" href="{{ route('tickets.index') }}"><i class="bi bi-life-preserver"></i> แจ้งปัญหา</a>
    <a class="btn btn-outline-primary" href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i> ร้องเรียน</a>
    <a class="btn btn-outline-primary" href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i> คู่มือการใช้งาน</a>
</div>

<div class="content-grid">
    <section>
        <div class="section-title">
            <h2>ประกาศปักหมุด</h2>
            <a href="{{ route('announcements.index') }}">ดูทั้งหมด</a>
        </div>
        <div class="item-list">
            @foreach($pinnedAnnouncements as $announcement)
                <article class="list-card">
                    <div>
                        <span class="tag">{{ $announcement->category }}</span>
                        @if($announcement->is_urgent)<span class="tag tag-danger">ด่วน</span>@endif
                    </div>
                    <h3>{{ $announcement->title }}</h3>
                    <p>{{ $announcement->body }}</p>
                    <small>{{ $announcement->department?->name ?? 'ทุกแผนก' }}</small>
                </article>
            @endforeach
        </div>
    </section>

    <section>
        <div class="section-title">
            <h2>Ticket ของฉัน</h2>
            <a href="{{ route('tickets.index') }}">เปิด Helpdesk</a>
        </div>
        <div class="item-list">
            @forelse($tickets as $ticket)
                <article class="list-card compact">
                    <h3>{{ $ticket->title }}</h3>
                    <p>{{ $ticket->assignee ? 'ผู้รับผิดชอบ: '.$ticket->assignee->name : 'ยังไม่มีผู้รับผิดชอบ' }}</p>
                    <span class="status-pill status-{{ $ticket->status }}">{{ $ticket->status }}</span>
                </article>
            @empty
                <div class="empty-state">ยังไม่มี Ticket ค้าง</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
