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
                        <small>{{ $announcement->announcement_no ?? 'ไม่ระบุเลขที่' }}</small>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('announcements.show', $announcement) }}">ดูประกาศ</a>
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

@if($showAnnouncementPopup)
    <div class="modal fade" id="announcementEntryModal" tabindex="-1" aria-labelledby="announcementEntryModalLabel" aria-hidden="true" data-auto-open-announcement-modal>
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content announcement-entry-modal">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">ประกาศและกิจกรรม</p>
                        <h2 class="modal-title fs-4" id="announcementEntryModalLabel">ข่าวสำคัญล่าสุด</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-announcement-popup-close aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div id="announcementEntryCarousel" class="carousel slide" data-bs-ride="false">
                        <div class="carousel-inner">
                            @foreach($popupAnnouncements as $announcement)
                                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                    <article class="announcement-popup-slide">
                                        <div class="meta-row">
                                            <span class="tag">{{ $announcement->category }}</span>
                                            @if($announcement->is_urgent)<span class="tag tag-danger">ด่วน</span>@endif
                                            @if($announcement->is_pinned)<span class="tag">ปักหมุด</span>@endif
                                        </div>
                                        <h3>{{ $announcement->title }}</h3>
                                        <p>{{ $announcement->body }}</p>
                                        <a class="btn btn-primary" href="{{ route('announcements.show', $announcement) }}">อ่านประกาศ</a>
                                    </article>
                                </div>
                            @endforeach
                        </div>
                        <button class="carousel-control-prev announcement-carousel-control announcement-carousel-control-prev" type="button" data-bs-target="#announcementEntryCarousel" data-bs-slide="prev" data-announcement-popup-prev aria-label="ก่อนหน้า">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next announcement-carousel-control announcement-carousel-control-next" type="button" data-bs-target="#announcementEntryCarousel" data-bs-slide="next" data-announcement-popup-next aria-label="ถัดไป">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        </button>
                        <div class="carousel-indicators announcement-carousel-indicators" aria-label="จำนวนประกาศ {{ $popupAnnouncements->count() }} รายการ">
                            @foreach($popupAnnouncements as $announcement)
                                <button
                                    type="button"
                                    data-bs-target="#announcementEntryCarousel"
                                    data-bs-slide-to="{{ $loop->index }}"
                                    data-announcement-popup-dot="{{ $loop->index }}"
                                    class="{{ $loop->first ? 'active' : '' }}"
                                    @if($loop->first) aria-current="true" @endif
                                    aria-label="ประกาศที่ {{ $loop->iteration }} จาก {{ $popupAnnouncements->count() }}"
                                ></button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection
