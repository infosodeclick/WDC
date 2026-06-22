@extends('layouts.app')

@section('title', 'โปรไฟล์พนักงาน | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">โปรไฟล์พนักงาน</p>
        <h1>{{ $user->name }}</h1>
        <p>{{ $user->employee?->position }} · {{ $user->employee?->department?->name }}</p>
    </div>
    <div class="role-badge">{{ $user->employee_code }}</div>
</div>

<div class="content-grid">
    <section class="panel">
        <h2>ข้อมูลส่วนตัว</h2>
        <dl class="detail-list">
            <dt>รหัสพนักงาน</dt><dd>{{ $user->employee_code }}</dd>
            <dt>ชื่อ</dt><dd>{{ $user->name }}</dd>
            <dt>ชื่ออังกฤษ</dt><dd>{{ $user->employee?->english_name ?? '-' }}</dd>
            <dt>ชื่อเล่น</dt><dd>{{ $user->employee?->nickname ?? '-' }}</dd>
            <dt>แผนก</dt><dd>{{ $user->employee?->department?->name }}</dd>
            <dt>ตำแหน่ง</dt><dd>{{ $user->employee?->position }}</dd>
            <dt>BU / ทีม</dt><dd>{{ collect([$user->employee?->business_unit, $user->employee?->team])->filter()->join(' · ') ?: '-' }}</dd>
            <dt>สาขา</dt><dd>{{ $user->employee?->location ?? '-' }}</dd>
            <dt>เบอร์โทร</dt><dd>{{ $user->employee?->phone ?? '-' }}</dd>
            <dt>เบอร์ต่อ</dt><dd>{{ $user->employee?->extension_number ?? '-' }}</dd>
            <dt>อีเมล</dt><dd>{{ $user->email ?? '-' }}</dd>
            <dt>วันเริ่มงาน</dt><dd>{{ $user->employee?->start_date?->format('d/m/Y') ?? '-' }}</dd>
        </dl>
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

@if($user->canAccess('payroll.link'))
    <section class="panel">
        <div class="section-title">
            <h2>สลิปเงินเดือน</h2>
            <a class="btn btn-outline-primary" href="{{ route('payroll') }}" target="_blank" rel="noopener"><i class="bi bi-receipt"></i> ดูสลิปเงินเดือน</a>
        </div>
    </section>
@endif

@endsection
