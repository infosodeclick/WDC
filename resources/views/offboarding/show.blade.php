@extends('layouts.app')

@section('title', 'คำขอพนักงานลาออก | WDC Portal')

@section('content')
@php
    $claimedByMe = $offboarding->claimed_by_id === auth()->id();
    $claimedByOther = $offboarding->claimed_by_id && ! $claimedByMe;
    $canEditChecklist = $canManageItOffboarding && $claimedByMe && ! in_array($offboarding->status, ['it_completed', 'hr_approved'], true);
@endphp

<div class="page-heading">
    <div>
        <p class="eyebrow">Employee Offboarding</p>
        <h1>คำขอพนักงานลาออก</h1>
        <p>{{ $offboarding->displayName() }}</p>
    </div>
    <div class="button-row">
        @if(auth()->user()->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']))
            <a class="btn btn-outline-primary" href="{{ route('it.index') }}"><i class="bi bi-tools"></i> กลับหน้า IT</a>
        @endif
        @if(auth()->user()->canAccessAny(['hr.employees.manage', 'hr.portal.view']))
            <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'offboarding']) }}"><i class="bi bi-people"></i> กลับหน้า HR</a>
        @endif
    </div>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>ข้อมูลพนักงาน</h2>
            <p>ข้อมูลนี้ใช้สำหรับปิดระบบและรับคืนทรัพย์สิน</p>
        </div>
        <span class="status-pill">{{ $offboarding->statusLabel() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table detail-table">
            <tbody>
                <tr><th>รหัสพนักงาน</th><td>{{ $offboarding->employee_code }}</td><th>วันที่ลาออก</th><td>{{ optional($offboarding->resignation_date)->format('d/m/Y') ?: '-' }}</td></tr>
                <tr><th>ชื่ออังกฤษ</th><td>{{ $offboarding->employee_name ?: '-' }}</td><th>ชื่อไทย</th><td>{{ $offboarding->thai_name ?: '-' }}</td></tr>
                <tr><th>ตำแหน่ง</th><td>{{ $offboarding->position ?: '-' }}</td><th>แผนก/BU</th><td>{{ $offboarding->department ?: '-' }}</td></tr>
                <tr><th>อีเมล</th><td>{{ $offboarding->email ?: '-' }}</td><th>ผู้ส่งคำขอ</th><td>{{ $offboarding->requester?->name ?? '-' }}</td></tr>
                <tr><th>หมายเหตุ HR</th><td colspan="3">{{ $offboarding->hr_note ?: '-' }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>รายการปิดระบบโดย IT</h2>
            <p>รับงานก่อนแก้ checklist เพื่อให้ทีมเห็นว่าใครกำลังดำเนินการอยู่</p>
        </div>
        <div class="button-row">
            @if($offboarding->claimedBy)
                <span class="status-pill status-soft">รับงานโดย {{ $offboarding->claimedBy->name }}{{ $offboarding->claimed_at ? ' · '.$offboarding->claimed_at->format('d/m/Y H:i') : '' }}</span>
            @else
                <span class="status-pill status-warning">ยังไม่มีคนรับงาน</span>
            @endif
            @if($canManageItOffboarding && ! $offboarding->claimed_by_id && ! in_array($offboarding->status, ['it_completed', 'hr_approved'], true))
                <form method="post" action="{{ route('it.offboarding.claim', $offboarding) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i> รับงาน</button>
                </form>
            @elseif($canManageItOffboarding && $claimedByMe && ! in_array($offboarding->status, ['it_completed', 'hr_approved'], true))
                <form method="post" action="{{ route('it.offboarding.release', $offboarding) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> ปล่อยงาน</button>
                </form>
            @endif
        </div>
    </div>

    @if($claimedByOther)
        <div class="alert-panel compact-alert">รายการนี้มี {{ $offboarding->claimedBy?->name ?? 'ทีม IT คนอื่น' }} กำลังดำเนินการอยู่ จึงเปิดดูได้แต่ยังแก้ checklist ไม่ได้</div>
    @endif

    @if($canManageItOffboarding)
        <form method="post" action="{{ route('it.offboarding.update', $offboarding) }}">
            @csrf
            @method('PATCH')
            <div class="table-responsive">
                <table class="table align-middle onboarding-checklist-table">
                    <thead>
                        <tr>
                            <th>รายการ</th>
                            <th>สถานะ</th>
                            <th>User / Email / Asset</th>
                            <th>ผู้ดำเนินการ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($offboarding->systems as $system)
                            <tr>
                                <td><strong>{{ $system->system_name }}</strong></td>
                                <td>
                                    <select class="form-select form-select-sm" name="systems[{{ $system->id }}][status]" @disabled(! $canEditChecklist)>
                                        <option value="pending" @selected($system->status === 'pending')>รอดำเนินการ</option>
                                        <option value="completed" @selected($system->status === 'completed')>ดำเนินการแล้ว</option>
                                        <option value="skipped" @selected($system->status === 'skipped')>ไม่เกี่ยวข้อง</option>
                                    </select>
                                </td>
                                <td>
                                    @if($system->asset)
                                        <strong>{{ $system->asset->code }}</strong><small class="d-block muted">{{ $system->asset->name }}</small>
                                    @else
                                        <span>{{ $system->username ?: '-' }}</span><small class="d-block muted">{{ $system->email ?: '' }}</small>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $system->completer?->name ?? '-' }}</strong>
                                    <small class="d-block muted">{{ $system->completed_at?->format('d/m/Y H:i') ?: '' }}</small>
                                </td>
                                <td><input class="form-control form-control-sm" name="systems[{{ $system->id }}][notes]" value="{{ $system->notes }}" placeholder="หมายเหตุ" @disabled(! $canEditChecklist)></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <label class="form-label">หมายเหตุ IT</label>
            <textarea class="form-control mb-2" name="it_note" rows="3" @disabled(! $canEditChecklist)>{{ $offboarding->it_note }}</textarea>
            <div class="button-row">
                <button class="btn btn-outline-primary" type="submit" @disabled(! $canEditChecklist)><i class="bi bi-save"></i> บันทึก checklist</button>
            </div>
        </form>

        @if($canEditChecklist)
            <form method="post" action="{{ route('it.offboarding.complete', $offboarding) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> ปิดระบบเรียบร้อย แจ้ง HR</button>
            </form>
        @endif
    @endif

    @if($canManageHrOffboarding && $offboarding->status === 'it_completed')
        <form method="post" action="{{ route('hr.offboarding.approve', $offboarding) }}" class="mt-3">
            @csrf
            @method('PATCH')
            <button class="btn btn-primary" type="submit"><i class="bi bi-person-x"></i> ปิดบัญชีและย้ายเป็นลาออก</button>
        </form>
    @endif
</section>
@endsection
