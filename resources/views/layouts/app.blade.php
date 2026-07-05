<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WDC Portal')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-body">
@php
    $currentUser = auth()->user()?->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');
    $helpdeskTemplateId = (int) ($itHelpdeskTemplateId ?? 0);
    $isHelpdeskWorkflow = $helpdeskTemplateId > 0 && request()->routeIs('workflows.*') && (int) request('template') === $helpdeskTemplateId;
    $hideTopbarSearch = request()->routeIs('dashboard') || request()->routeIs('directory.*') || request()->routeIs('it.*') || request()->routeIs('assets.*') || request()->routeIs('hr.*') || request()->routeIs('admin.*') || request()->routeIs('approvals.*') || request()->routeIs('reports.*');
    $canShowItNav = $currentUser?->canAccessAny(['it.portal.view', 'tickets.manage', 'it.onboarding.manage']);
    $canShowInventoryNav = $currentUser?->canAccessItAssets();
    $canShowHrNav = $currentUser?->canAccessAny(['hr.portal.view', 'hr.onboarding.manage', 'hr.employees.manage', 'hr.announcements.manage', 'complaints.review']);
    $canShowAdminNav = $currentUser?->canAccessAny(['admin.users.manage', 'admin.roles.manage', 'admin.activity.view', 'admin.system.manage', 'iam.users.manage', 'iam.roles.manage', 'audit.logs.view']);
    $canShowApprovalNav = $currentUser?->canAccessAny(['workflows.manage', 'hr.onboarding.manage', 'hr.employees.manage', 'complaints.review', 'it.onboarding.manage', 'tickets.manage', 'admin.users.manage', 'admin.roles.manage', 'audit.logs.view']);
    $canShowReportsNav = $currentUser?->canAccessAny(['tickets.manage', 'hr.employees.manage', 'hr.onboarding.manage', 'complaints.review', 'assets.reports', 'audit.logs.view', 'admin.activity.view', 'admin.system.manage', 'workflows.manage']);
    $itQueueCount = $canShowItNav
        ? \App\Models\EmployeeOnboardingRequest::whereIn('status', ['pending_it', 'in_progress', 'cancel_requested'])->count()
            + \App\Models\EmployeeOffboardingRequest::whereIn('status', ['pending_it', 'in_progress'])->count()
        : 0;
    $hrQueueCount = $canShowHrNav
        ? \App\Models\EmployeeOnboardingRequest::where('status', 'it_completed')->count()
            + \App\Models\EmployeeOffboardingRequest::where('status', 'it_completed')->count()
            + \App\Models\ProfileChangeRequest::where('status', 'pending')->count()
            + \App\Models\Complaint::whereIn('status', ['submitted', 'in_review', 'pending'])->count()
        : 0;
    $adminQueueCount = $canShowAdminNav ? ($unreadNotificationCount ?? 0) : 0;
    $approvalQueueCount = $canShowApprovalNav
        ? \App\Models\EmployeeOnboardingRequest::whereIn('status', ['pending_it', 'in_progress', 'cancel_requested', 'it_completed'])->count()
            + \App\Models\EmployeeOffboardingRequest::whereIn('status', ['pending_it', 'in_progress', 'it_completed'])->count()
            + \App\Models\ProfileChangeRequest::where('status', 'pending')->count()
            + \App\Models\Complaint::whereIn('status', ['submitted', 'in_review', 'pending'])->count()
        : 0;
@endphp
<div class="portal-shell">
    <aside class="portal-sidebar">
        <a class="brand-lockup" href="{{ route('dashboard') }}">
            <span class="brand-mark">WDC</span>
            <span>
                <strong>WDC Portal</strong>
                <small>ข่าวสาร HR IT และคู่มือ</small>
            </span>
        </a>

        <nav class="nav flex-column portal-nav">
            @if($currentUser?->canAccess('portal.dashboard.view'))
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i><span>หน้าแรก</span></a>
            @endif
            @if($currentUser?->canAccess('directory.view'))
                <a class="nav-link {{ request()->routeIs('directory.*') ? 'active' : '' }}" href="{{ route('directory.index') }}"><i class="bi bi-person-lines-fill"></i><span>รายชื่อพนักงาน</span></a>
            @endif
            @if($currentUser?->canAccess('meeting_rooms.view'))
                <a class="nav-link {{ request()->routeIs('meeting-rooms.*') ? 'active' : '' }}" href="{{ route('meeting-rooms.index') }}"><i class="bi bi-calendar2-week"></i><span>ห้องประชุม</span></a>
            @endif
            @if($currentUser?->canAccess('announcements.view'))
                <a class="nav-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}" href="{{ route('announcements.index') }}"><i class="bi bi-megaphone"></i><span>ประกาศ</span></a>
            @endif
            @if($currentUser?->canAccess('knowledge.view'))
                <a class="nav-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i><span>เทรนนิ่ง</span></a>
            @endif
            @if($currentUser?->canAccessAny(['tickets.create', 'tickets.manage']))
                <a class="nav-link {{ request()->routeIs('tickets.*') || $isHelpdeskWorkflow ? 'active' : '' }}" href="{{ $itHelpdeskNavUrl ?? route('tickets.index') }}"><i class="bi bi-life-preserver"></i><span>แจ้งปัญหา IT</span></a>
            @endif
            @if($currentUser?->canAccessAny(['workflows.create', 'workflows.manage']))
                <a class="nav-link {{ request()->routeIs('workflows.*') && ! $isHelpdeskWorkflow ? 'active' : '' }}" href="{{ route('workflows.index') }}"><i class="bi bi-kanban"></i><span>คำขอ/อนุมัติ</span></a>
            @endif
            @if($currentUser?->canAccessAny(['complaints.create', 'complaints.review']))
                <a class="nav-link {{ request()->routeIs('complaints.*') ? 'active' : '' }}" href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i><span>ร้องเรียน</span></a>
            @endif
            @if($currentUser?->canAccess('documents.view'))
                <a class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}" href="{{ route('documents.index') }}"><i class="bi bi-file-earmark-arrow-down"></i><span>แบบฟอร์ม</span></a>
            @endif
        </nav>

        <div class="portal-divider"></div>

        <nav class="nav flex-column portal-nav">
            @if($canShowItNav)
                <div class="portal-nav-group">
                    <a class="nav-link {{ request()->routeIs('it.*') ? 'active' : '' }}" href="{{ route('it.index') }}">
                        <i class="bi bi-tools"></i><span>IT</span>
                        @if($itQueueCount > 0)<span class="nav-badge">{{ $itQueueCount }}</span>@endif
                    </a>
                </div>
            @endif
            @if($canShowInventoryNav)
                <div class="portal-nav-group">
                    <a class="nav-link {{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}"><i class="bi bi-box-seam"></i><span>INVENTORY</span></a>
                </div>
            @endif
            @if($canShowHrNav)
                <div class="portal-nav-group">
                    <a class="nav-link {{ request()->routeIs('hr.*') ? 'active' : '' }}" href="{{ route('hr.index') }}">
                        <i class="bi bi-people"></i><span>HR</span>
                        @if($hrQueueCount > 0)<span class="nav-badge">{{ $hrQueueCount }}</span>@endif
                    </a>
                </div>
            @endif
            @if($canShowAdminNav)
                <div class="portal-nav-group">
                    <a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.index') }}">
                        <i class="bi bi-sliders"></i><span>Admin</span>
                        @if($adminQueueCount > 0)<span class="nav-badge">{{ $adminQueueCount }}</span>@endif
                    </a>
                </div>
            @endif
        </nav>

        @if($canShowApprovalNav || $canShowReportsNav)
            <nav class="nav flex-column portal-nav portal-utility-nav" aria-label="เมนูสรุปและอนุมัติ">
                <span class="portal-nav-label">สรุปและอนุมัติ</span>
                @if($canShowApprovalNav)
                    <div class="portal-nav-group">
                        <a class="nav-link {{ request()->routeIs('approvals.*') ? 'active' : '' }}" href="{{ route('approvals.index') }}">
                            <i class="bi bi-check2-square"></i><span>รายการรออนุมัติ</span>
                            @if($approvalQueueCount > 0)<span class="nav-badge">{{ $approvalQueueCount }}</span>@endif
                        </a>
                    </div>
                @endif
                @if($canShowReportsNav)
                    <div class="portal-nav-group">
                        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-graph-up-arrow"></i><span>รายงานภาพรวม</span></a>
                    </div>
                @endif
            </nav>
        @endif
    </aside>

    <main class="portal-main">
        <header class="topbar">
            <a class="mobile-topbar-brand" href="{{ route('dashboard') }}" aria-label="WDC Portal">
                <span class="brand-mark">WDC</span>
                <span>
                    <strong>WDC Portal</strong>
                    <small>ระบบภายในบริษัท</small>
                </span>
            </a>

            @unless($hideTopbarSearch)
                <form class="search-box" action="{{ route('search') }}" method="get">
                    <i class="bi bi-search"></i>
                    <input name="q" value="{{ request('q') }}" placeholder="ค้นหา พนักงาน ประกาศ เทรนนิ่ง" aria-label="ค้นหา">
                </form>
            @endunless

            <div class="topbar-actions">
                <form action="{{ route('notifications.read') }}" method="post">
                    @csrf
                    <button class="icon-button" type="submit" title="แจ้งเตือน" aria-label="แจ้งเตือน">
                        <i class="bi bi-bell"></i>
                        @if($unreadNotificationCount > 0)
                            <span class="notification-pill">{{ $unreadNotificationCount }}</span>
                        @endif
                    </button>
                </form>

                <a class="user-chip" href="{{ route('profile') }}" title="เปิดโปรไฟล์พนักงาน" aria-label="เปิดโปรไฟล์พนักงาน">
                    <span>{{ $currentUser?->name }}</span>
                    <small>{{ $currentUser?->employee?->department?->name ?? '-' }}</small>
                </a>

                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="icon-button" type="submit" title="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </header>

        <section class="content-wrap">
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <strong>ตรวจสอบข้อมูลอีกครั้ง</strong>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            @yield('content')
        </section>
    </main>
</div>

<nav class="mobile-bottom-nav" aria-label="เมนูหลักบนมือถือ">
    @if($currentUser?->canAccess('portal.dashboard.view'))
        <a class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-house-door"></i>
            <span>หน้าแรก</span>
        </a>
    @endif
    @if($currentUser?->canAccessAny(['tickets.create', 'tickets.manage']))
        <a class="mobile-nav-item {{ request()->routeIs('tickets.*') || $isHelpdeskWorkflow ? 'active' : '' }}" href="{{ $itHelpdeskNavUrl ?? route('tickets.index') }}">
            <i class="bi bi-life-preserver"></i>
            <span>แจ้ง IT</span>
        </a>
    @endif
    @if($currentUser?->canAccessAny(['workflows.create', 'workflows.manage']))
        <a class="mobile-nav-item {{ request()->routeIs('workflows.*') && ! $isHelpdeskWorkflow ? 'active' : '' }}" href="{{ route('workflows.index') }}">
            <i class="bi bi-kanban"></i>
            <span>คำขอ</span>
        </a>
    @endif
    <button class="mobile-nav-item mobile-nav-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMoreMenu" aria-controls="mobileMoreMenu">
        <i class="bi bi-three-dots"></i>
        <span>เพิ่มเติม</span>
    </button>
</nav>

<div class="offcanvas offcanvas-bottom mobile-more-menu" tabindex="-1" id="mobileMoreMenu" aria-labelledby="mobileMoreMenuLabel">
    <div class="offcanvas-header">
        <div>
            <p class="eyebrow mb-1">WDC Portal</p>
            <h2 class="offcanvas-title" id="mobileMoreMenuLabel">เมนูทั้งหมด</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="ปิด"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mobile-more-section">
            <h3>งานประจำวัน</h3>
            <div class="mobile-more-grid">
                @if($currentUser?->canAccess('profile.view'))
                    <a href="{{ route('profile') }}"><i class="bi bi-person-badge"></i><span>โปรไฟล์พนักงาน</span></a>
                @endif
                @if($currentUser?->canAccess('directory.view'))
                    <a href="{{ route('directory.index') }}"><i class="bi bi-person-lines-fill"></i><span>รายชื่อพนักงาน</span></a>
                @endif
                @if($currentUser?->canAccess('meeting_rooms.view'))
                    <a href="{{ route('meeting-rooms.index') }}"><i class="bi bi-calendar2-week"></i><span>ห้องประชุม</span></a>
                @endif
                @if($currentUser?->canAccess('announcements.view'))
                    <a href="{{ route('announcements.index') }}"><i class="bi bi-megaphone"></i><span>ประกาศ</span></a>
                @endif
                @if($currentUser?->canAccess('knowledge.view'))
                    <a href="{{ route('knowledge.index') }}"><i class="bi bi-journal-richtext"></i><span>เทรนนิ่ง</span></a>
                @endif
                @if($currentUser?->canAccessAny(['complaints.create', 'complaints.review']))
                    <a href="{{ route('complaints.index') }}"><i class="bi bi-shield-check"></i><span>ร้องเรียน</span></a>
                @endif
                @if($currentUser?->canAccess('documents.view'))
                    <a href="{{ route('documents.index') }}"><i class="bi bi-file-earmark-arrow-down"></i><span>แบบฟอร์ม</span></a>
                @endif
            </div>
        </div>

        <div class="mobile-more-section">
            <h3>หลังบ้าน</h3>
            <div class="mobile-more-grid">
                @if($canShowItNav)
                    <a href="{{ route('it.index') }}"><i class="bi bi-tools"></i><span>IT</span>@if($itQueueCount > 0)<strong>{{ $itQueueCount }}</strong>@endif</a>
                @endif
                @if($canShowInventoryNav)
                    <a href="{{ route('assets.index') }}"><i class="bi bi-box-seam"></i><span>INVENTORY</span></a>
                @endif
                @if($canShowHrNav)
                    <a href="{{ route('hr.index') }}"><i class="bi bi-people"></i><span>HR</span>@if($hrQueueCount > 0)<strong>{{ $hrQueueCount }}</strong>@endif</a>
                @endif
                @if($canShowAdminNav)
                    <a href="{{ route('admin.index') }}"><i class="bi bi-sliders"></i><span>Admin</span>@if($adminQueueCount > 0)<strong>{{ $adminQueueCount }}</strong>@endif</a>
                @endif
            </div>
        </div>

        @if($canShowApprovalNav || $canShowReportsNav)
            <div class="mobile-more-section">
                <h3>สรุปและอนุมัติ</h3>
                <div class="mobile-more-grid">
                    @if($canShowApprovalNav)
                        <a href="{{ route('approvals.index') }}"><i class="bi bi-check2-square"></i><span>รายการรออนุมัติ</span>@if($approvalQueueCount > 0)<strong>{{ $approvalQueueCount }}</strong>@endif</a>
                    @endif
                    @if($canShowReportsNav)
                        <a href="{{ route('reports.index') }}"><i class="bi bi-graph-up-arrow"></i><span>รายงานภาพรวม</span></a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
