@extends('layouts.app')

@section('title', 'โปรไฟล์พนักงาน | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ $user->name }}</h1>
        <p>{{ $user->employee?->position }} · {{ $user->employee?->department?->name }}</p>
    </div>
    <div class="role-badge">{{ $user->employee_code }}</div>
</div>

<div class="content-grid">
    <section class="panel">
        <div class="section-title">
            <h2>ข้อมูลส่วนตัว</h2>
            <div class="button-row">
                @if($user->canAccess('payroll.link'))
                    <a class="btn btn-outline-primary" href="{{ route('payroll') }}"><i class="bi bi-receipt"></i> สลิปเงินเดือน</a>
                @endif
                <a class="btn btn-outline-primary" href="{{ route('time-attendance') }}"><i class="bi bi-clock-history"></i> ลงเวลางาน</a>
            </div>
        </div>
        <dl class="detail-list">
            <dt>รหัสพนักงาน</dt><dd>{{ $user->employee_code }}</dd>
            <dt>ชื่อ</dt><dd>{{ $user->name }}</dd>
            <dt>ชื่ออังกฤษ</dt><dd>{{ $user->employee?->english_name ?? '-' }}</dd>
            <dt>ชื่อเล่นอังกฤษ</dt><dd>{{ $user->employee?->english_nickname ?? '-' }}</dd>
            <dt>ชื่อไทย</dt><dd>{{ $user->employee?->thai_name ?? '-' }}</dd>
            <dt>ชื่อเล่นไทย</dt><dd>{{ $user->employee?->thai_nickname ?? $user->employee?->nickname ?? '-' }}</dd>
            <dt>แผนก</dt><dd>{{ $user->employee?->department?->name }}</dd>
            <dt>ตำแหน่ง</dt><dd>{{ $user->employee?->position }}</dd>
            <dt>BU / ทีม</dt><dd>{{ collect([$user->employee?->business_unit, $user->employee?->team])->filter()->join(' · ') ?: '-' }}</dd>
            <dt>สาขา</dt><dd>{{ $user->employee?->location ?? '-' }}</dd>
            <dt>เบอร์โทร</dt><dd>{{ $user->employee?->phone ?? '-' }}</dd>
            <dt>เบอร์ต่อ</dt><dd>{{ $user->employee?->extension_number ?? '-' }}</dd>
            <dt>อีเมล</dt><dd>{{ $user->email ?? '-' }}</dd>
            <dt>วันเริ่มงาน</dt><dd>{{ $user->employee?->start_date?->format('d/m/Y') ?? '-' }}</dd>
        </dl>

        <form class="profile-contact-form" method="post" action="{{ route('profile.contact.update') }}">
            @csrf
            @method('PATCH')
            <label>
                <span>ขอแก้ไขเบอร์โทรส่วนตัว</span>
                <input class="form-control" name="phone" value="{{ old('phone', $pendingProfileChange?->requested_value ?? $user->employee?->phone) }}" placeholder="เช่น 081-234-5678" required>
            </label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งให้ HR อนุมัติ</button>
            @if($pendingProfileChange)
                <small class="muted">รอ HR อนุมัติ: {{ $pendingProfileChange->requested_value }}</small>
            @endif
        </form>
    </section>

    <section class="panel">
        <div class="section-title">
            <h2>ประกาศที่ต้องอ่าน</h2>
            <span class="status-pill">{{ $unreadAnnouncementCount }} ฉบับใหม่</span>
        </div>
        <div class="item-list">
            @forelse($profileAnnouncements as $announcement)
                <a class="document-row announcement-profile-row" href="{{ route('announcements.show', $announcement) }}">
                    <i class="bi bi-megaphone"></i>
                    <span>
                        {{ $announcement->title }}
                        <small>
                            {{ $announcement->announcement_no ?? 'ไม่ระบุเลขที่' }} · {{ $announcement->category }}
                            @if($announcement->is_urgent) · ด่วน @endif
                        </small>
                    </span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            @empty
                <div class="empty-state">ไม่มีประกาศใหม่ที่ต้องอ่าน</div>
            @endforelse
        </div>
    </section>
</div>

<div class="content-grid">
    <section class="panel">
        <h2>อุปกรณ์ IT ที่ใช้งาน</h2>
        <div class="item-list">
            @forelse($assets as $asset)
                <div class="result-row">
                    <strong>{{ $asset->code }} · {{ $asset->name }}</strong>
                    <small>{{ $asset->category?->name ?? '-' }} · {{ $asset->brand }} {{ $asset->model }} · {{ $asset->statusLabel() }}</small>
                </div>
            @empty
                <div class="empty-state">ยังไม่มีอุปกรณ์ IT ที่ผูกกับโปรไฟล์นี้</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <h2>เอกสารของฉัน</h2>
        <div class="item-list">
            @forelse($user->employee?->documents ?? [] as $document)
                <a class="document-row" href="{{ route('documents.download', $document) }}">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>{{ $document->title }}<small>{{ $document->category }}</small></span>
                    <i class="bi bi-download"></i>
                </a>
            @empty
                <div class="empty-state">ยังไม่มีเอกสารเฉพาะบุคคล</div>
            @endforelse
        </div>
    </section>
</div>

@endsection
