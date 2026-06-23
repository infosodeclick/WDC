@extends('layouts.app')

@section('title', 'หน้าแรก | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <h1>สวัสดี คุณ{{ $user->name }}</h1>
    </div>
    @if($user->canAccess('profile.view'))
        <a class="btn btn-primary page-profile-button" href="{{ route('profile') }}"><i class="bi bi-person-badge"></i> โปรไฟล์พนักงาน</a>
    @endif
</div>

<div class="quick-actions">
    @if($user->canAccess('announcements.view'))
        <a class="btn btn-primary" href="{{ route('announcements.index') }}"><i class="bi bi-megaphone"></i> ดูประกาศ</a>
    @endif
    @if($user->canAccess('directory.view'))
        <a class="btn btn-outline-primary" href="{{ route('directory.index') }}"><i class="bi bi-person-lines-fill"></i> ค้นหาพนักงาน</a>
    @endif
    @if($user->canAccessAny(['tickets.create', 'tickets.manage', 'workflows.create', 'workflows.manage']))
        <a class="btn btn-outline-primary" href="{{ $itHelpdeskUrl }}"><i class="bi bi-life-preserver"></i> แจ้งปัญหา IT</a>
    @endif
    @if($user->canAccessAny(['complaints.create', 'complaints.review']))
        <a class="btn btn-outline-primary" href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i> ร้องเรียน</a>
    @endif
    @if($user->canAccess('knowledge.view'))
        <a class="btn btn-outline-primary" href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i> เทรนนิ่ง</a>
    @endif
    @if($user->canAccessItAssets())
        <a class="btn btn-outline-primary" href="{{ route('assets.index') }}"><i class="bi bi-pc-display"></i> ทรัพย์สิน IT</a>
    @endif
</div>

<div class="content-grid">
    @if($user->canAccess('announcements.view'))
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
    @endif

    @if($user->canAccessAny(['tickets.create', 'tickets.manage', 'workflows.create', 'workflows.manage']))
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
    @endif
</div>

@endsection
