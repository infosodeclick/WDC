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
    <a class="metric-card" href="{{ $itHelpdeskUrl }}">
        <span>งาน IT ค้าง</span>
        <strong>{{ $pendingTickets }}</strong>
        <small>จาก SmartFlow Workflow เดียว</small>
    </a>
    <a class="metric-card" href="{{ route('knowledge.index') }}">
        <span>วิดีโอใหม่</span>
        <strong>{{ $newVideos }}</strong>
        <small>อัปเดตใน 14 วันล่าสุด</small>
    </a>
    <a class="metric-card" href="{{ route('directory.index') }}">
        <span>ข้อมูลติดต่อ</span>
        <strong>{{ $directoryCount }}</strong>
        <small>นำเข้าจาก Directory เดิม</small>
    </a>
    <a class="metric-card" href="{{ route('workflows.index') }}">
        <span>คำขอของฉัน</span>
        <strong>{{ $workflowPending }}</strong>
        <small>ยังไม่ปิดงาน</small>
    </a>
</div>

<div class="quick-actions">
    <a class="btn btn-primary" href="{{ route('announcements.index') }}"><i class="bi bi-megaphone"></i> ดูประกาศ</a>
    <a class="btn btn-outline-primary" href="{{ route('directory.index') }}"><i class="bi bi-person-lines-fill"></i> ค้นหาพนักงาน</a>
    <a class="btn btn-outline-primary" href="{{ $itHelpdeskUrl }}"><i class="bi bi-life-preserver"></i> แจ้งปัญหา IT</a>
    <a class="btn btn-outline-primary" href="{{ route('workflows.index') }}"><i class="bi bi-kanban"></i> ส่งคำขออนุมัติ</a>
    <a class="btn btn-outline-primary" href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i> ร้องเรียน</a>
    <a class="btn btn-outline-primary" href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i> คู่มือการใช้งาน</a>
    <a class="btn btn-outline-primary" href="{{ route('systems.index') }}"><i class="bi bi-diagram-3"></i> เข้าระบบเดิม</a>
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
            <h2>งาน IT ของฉัน</h2>
            <a href="{{ $itHelpdeskUrl }}">เปิด Helpdesk</a>
        </div>
        <div class="item-list">
            @forelse($itRequests as $requestItem)
                <article class="list-card compact">
                    <h3>{{ $requestItem->title }}</h3>
                    <p>{{ $requestItem->currentStep?->name ?? ($requestItem->assignee ? 'ผู้รับผิดชอบ: '.$requestItem->assignee->name : 'รอทีม IT รับเรื่อง') }}</p>
                    <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                </article>
            @empty
                <div class="empty-state">ยังไม่มีงาน IT ค้าง</div>
            @endforelse
        </div>
    </section>
</div>

<section class="panel">
    <div class="section-title">
        <h2>คำขอ/อนุมัติของฉัน</h2>
        <a href="{{ route('workflows.index') }}">เปิดศูนย์คำขอ</a>
    </div>
    <div class="item-list">
        @forelse($workflowRequests as $workflowRequest)
            <article class="list-card compact">
                <div class="meta-row">
                    <span class="tag">{{ $workflowRequest->template->name }}</span>
                    <span class="status-pill status-{{ $workflowRequest->status }}">{{ $workflowRequest->statusLabel() }}</span>
                </div>
                <h3>{{ $workflowRequest->title }}</h3>
                <p>{{ $workflowRequest->currentStep?->name ?? 'ไม่มีขั้นตอนค้าง' }}</p>
            </article>
        @empty
            <div class="empty-state">ยังไม่มีคำขออนุมัติค้าง</div>
        @endforelse
    </div>
</section>

<section class="panel">
    <div class="section-title">
        <h2>ระบบที่ใช้งานบ่อย</h2>
        <a href="{{ route('systems.index') }}">ดูศูนย์รวมระบบ</a>
    </div>
    <div class="system-mini-grid">
        @foreach($featuredSystems as $system)
            <a class="system-mini-card" href="{{ str_starts_with($system->url, '/') ? url($system->url) : $system->url }}" target="{{ str_starts_with($system->url, '/') ? '_self' : '_blank' }}" rel="noopener">
                <span>{{ $system->category }}</span>
                <strong>{{ $system->name }}</strong>
                <small>{{ $system->login_method }}</small>
            </a>
        @endforeach
    </div>
</section>
@endsection
