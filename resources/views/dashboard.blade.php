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
    @if($user->canAccess('announcements.view'))
        <a class="metric-card" href="{{ route('announcements.index') }}">
            <span>ประกาศใหม่</span>
            <strong>{{ $newAnnouncements }}</strong>
            <small>รายการใน 7 วันล่าสุด</small>
        </a>
    @endif
    @if($user->canAccessAny(['tickets.create', 'tickets.manage', 'workflows.create', 'workflows.manage']))
        <a class="metric-card" href="{{ $itHelpdeskUrl }}">
            <span>งาน IT ค้าง</span>
            <strong>{{ $pendingTickets }}</strong>
            <small>จาก SmartFlow Workflow เดียว</small>
        </a>
    @endif
    @if($user->canAccess('knowledge.view'))
        <a class="metric-card" href="{{ route('knowledge.index') }}">
            <span>วิดีโอเทรนนิ่งใหม่</span>
            <strong>{{ $newVideos }}</strong>
            <small>อัปเดตใน 14 วันล่าสุด</small>
        </a>
    @endif
    @if($user->canAccess('directory.view'))
        <a class="metric-card" href="{{ route('directory.index') }}">
            <span>ข้อมูลติดต่อ</span>
            <strong>{{ $directoryCount }}</strong>
            <small>นำเข้าจาก Directory เดิม</small>
        </a>
    @endif
    @if($user->canAccessAny(['workflows.create', 'workflows.manage']))
        <a class="metric-card" href="{{ route('workflows.index') }}">
            <span>คำขอของฉัน</span>
            <strong>{{ $workflowPending }}</strong>
            <small>ยังไม่ปิดงาน</small>
        </a>
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
    @if($user->canAccessAny(['workflows.create', 'workflows.manage']))
        <a class="btn btn-outline-primary" href="{{ route('workflows.index') }}"><i class="bi bi-kanban"></i> ส่งคำขออนุมัติ</a>
    @endif
    @if($user->canAccessAny(['complaints.create', 'complaints.review']))
        <a class="btn btn-outline-primary" href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i> ร้องเรียน</a>
    @endif
    @if($user->canAccess('knowledge.view'))
        <a class="btn btn-outline-primary" href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i> เทรนนิ่ง</a>
    @endif
    <a class="btn btn-outline-primary" href="#meeting-room"><i class="bi bi-calendar2-week"></i> ห้องประชุม</a>
    @if($user->canAccessItAssets())
        <a class="btn btn-outline-primary" href="{{ route('assets.index') }}"><i class="bi bi-pc-display"></i> ทรัพย์สิน IT</a>
    @endif
    @if($user->canAccess('systems.view'))
        <a class="btn btn-outline-primary" href="{{ route('systems.index') }}"><i class="bi bi-diagram-3"></i> เข้าระบบเดิม</a>
    @endif
</div>

@if($user->canAccess('profile.view'))
    <section class="panel profile-summary-panel" id="employee-profile">
        <div class="section-title">
            <div>
                <p class="eyebrow">Employee Profile</p>
                <h2>โปรไฟล์พนักงาน</h2>
            </div>
            <span class="role-badge">{{ $user->employee_code }}</span>
        </div>

        <div class="profile-summary-grid">
            <div>
                <h3>{{ $user->name }}</h3>
                <p>{{ $user->employee?->position ?? '-' }} · {{ $user->employee?->department?->name ?? '-' }}</p>
                <dl class="detail-list compact-detail-list">
                    <dt>รหัสพนักงาน</dt><dd>{{ $user->employee_code }}</dd>
                    <dt>ชื่ออังกฤษ</dt><dd>{{ $user->employee?->english_name ?? '-' }}</dd>
                    <dt>ชื่อเล่น</dt><dd>{{ $user->employee?->nickname ?? '-' }}</dd>
                    <dt>BU / ทีม</dt><dd>{{ collect([$user->employee?->business_unit, $user->employee?->team])->filter()->join(' · ') ?: '-' }}</dd>
                    <dt>สาขา</dt><dd>{{ $user->employee?->location ?? '-' }}</dd>
                    <dt>เบอร์โทร</dt><dd>{{ $user->employee?->phone ?? '-' }}</dd>
                    <dt>เบอร์ต่อ</dt><dd>{{ $user->employee?->extension_number ?? '-' }}</dd>
                    <dt>อีเมล</dt><dd>{{ $user->email ?? '-' }}</dd>
                    <dt>วันเริ่มงาน</dt><dd>{{ $user->employee?->start_date?->format('d/m/Y') ?? '-' }}</dd>
                </dl>
            </div>

            <div class="profile-side-stack">
                @if($user->canAccess('payroll.link'))
                    <a class="profile-action-card" href="{{ route('payroll') }}" target="_blank" rel="noopener">
                        <i class="bi bi-receipt"></i>
                        <span>
                            <strong>สลิปเงินเดือน</strong>
                            <small>เปิดระบบ Payroll เดิม</small>
                        </span>
                    </a>
                @endif

                <div class="profile-mini-card">
                    <strong>เอกสารของฉัน</strong>
                    <div class="item-list">
                        @forelse($user->employee?->documents ?? [] as $document)
                            <a class="document-row compact-document-row" href="{{ route('documents.download', $document) }}">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>{{ $document->title }}<small>{{ $document->category }}</small></span>
                                <i class="bi bi-download"></i>
                            </a>
                        @empty
                            <div class="empty-state">ยังไม่มีเอกสารเฉพาะบุคคล</div>
                        @endforelse
                    </div>
                </div>

                <div class="profile-mini-card">
                    <strong>บัญชีระบบที่เกี่ยวข้อง</strong>
                    <div class="account-table compact-account-table">
                        @forelse($user->externalAccounts as $account)
                            <div>
                                <strong>{{ $account->legacySystem->name }}</strong>
                                <span>{{ $account->login_identifier ?? 'ยังไม่ได้ระบุบัญชี' }}</span>
                                <small>{{ $account->credential_note ?? $account->legacySystem->login_method }}</small>
                            </div>
                        @empty
                            <div class="empty-state">ยังไม่ได้ผูกบัญชีระบบเดิม</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

<section class="panel meeting-room-panel" id="meeting-room">
    <div class="section-title">
        <h2>ห้องประชุม</h2>
        <span class="tag">Google Sheet</span>
    </div>
    <div class="meeting-room-grid">
        <div>
            <strong>ตารางจองห้องประชุม</strong>
            <p>เตรียมเชื่อมข้อมูลจาก Google Sheet</p>
        </div>
        <button class="btn btn-outline-primary" type="button" disabled><i class="bi bi-calendar-check"></i> เปิดตารางจอง</button>
    </div>
</section>

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

@if($user->canAccessAny(['workflows.create', 'workflows.manage']))
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
@endif

@if($user->canAccess('systems.view'))
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
@endif
@endsection
