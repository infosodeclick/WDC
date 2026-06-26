@extends('layouts.app')

@section('title', 'คำขอพนักงานใหม่ | WDC Portal')

@section('content')
@php
    $claimedByMe = $onboarding->claimed_by_id === auth()->id();
    $claimedByOther = $onboarding->claimed_by_id && ! $claimedByMe;
    $canEditChecklist = $canManageItOnboarding && $claimedByMe && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true);
@endphp

<div class="page-heading">
    <div>
        <p class="eyebrow">New Employee Request</p>
        <h1>คำขอพนักงานใหม่</h1>
        <p>{{ $onboarding->employee_code }} · {{ $onboarding->displayName() }}</p>
    </div>
    <div class="button-row">
        @if(auth()->user()->canAccessAny(['admin.users.manage', 'admin.roles.manage', 'admin.system.manage', 'iam.users.manage', 'iam.roles.manage']))
            <a class="btn btn-outline-primary" href="{{ route('admin.index', ['section' => 'notifications']) }}"><i class="bi bi-bell"></i> แจ้งเตือนแอดมิน</a>
        @endif
        @if(auth()->user()->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']))
            <a class="btn btn-outline-primary" href="{{ route('it.index') }}"><i class="bi bi-tools"></i> กลับหน้า IT</a>
        @endif
        @if(auth()->user()->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']))
            <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'onboarding']) }}"><i class="bi bi-people"></i> กลับหน้า HR</a>
        @endif
    </div>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>ข้อมูลที่ HR ส่งมา</h2>
            <p>ข้อมูลนี้ใช้เปิดระบบ อีเมล และสิทธิ์ที่เกี่ยวข้องให้พนักงานใหม่</p>
        </div>
        <span class="status-pill">{{ $onboarding->statusLabel() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table detail-table">
            <tbody>
                <tr><th>รหัสพนักงาน</th><td>{{ $onboarding->employee_code }}</td><th>วันที่เริ่มงาน</th><td>{{ optional($onboarding->start_date)->format('d/m/Y') ?: '-' }}</td></tr>
                <tr><th>ชื่ออังกฤษ</th><td>{{ $onboarding->english_name ?: '-' }}</td><th>ชื่อเล่นอังกฤษ</th><td>{{ $onboarding->english_nickname ?: '-' }}</td></tr>
                <tr><th>ชื่อไทย</th><td>{{ $onboarding->thai_name ?: '-' }}</td><th>ชื่อเล่นไทย</th><td>{{ $onboarding->thai_nickname ?: '-' }}</td></tr>
                <tr><th>ตำแหน่ง</th><td>{{ $onboarding->position ?: '-' }}</td><th>แผนก/BU</th><td>{{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</td></tr>
                <tr><th>ทีม</th><td>{{ $onboarding->team ?: '-' }}</td><th>สาขา</th><td>{{ $onboarding->location ?: '-' }}</td></tr>
                <tr><th>อีเมล</th><td>{{ $onboarding->corporate_email ?: '-' }}</td><th>โทร</th><td>{{ $onboarding->personal_phone ?: '-' }}</td></tr>
                <tr><th>เบอร์โต๊ะ</th><td>{{ $onboarding->extension_number ?: '-' }}</td><th>ผู้ส่งคำขอ</th><td>{{ $onboarding->requester?->name ?? '-' }}</td></tr>
                <tr><th>หมายเหตุ HR</th><td colspan="3">{{ $onboarding->hr_note ?: '-' }}</td></tr>
            </tbody>
        </table>
    </div>
    @if($onboarding->cancel_reason)
        <div class="alert-panel compact-alert">
            <strong>เหตุผลการยกเลิก</strong>
            <p>{{ $onboarding->cancel_reason }}</p>
            <small>
                ขอโดย {{ $onboarding->cancelRequester?->name ?? '-' }}
                {{ $onboarding->cancel_requested_at ? '· '.$onboarding->cancel_requested_at->format('d/m/Y H:i') : '' }}
                @if($onboarding->cancelConfirmer)
                    · ยืนยันโดย {{ $onboarding->cancelConfirmer->name }} {{ $onboarding->cancel_confirmed_at ? $onboarding->cancel_confirmed_at->format('d/m/Y H:i') : '' }}
                @endif
            </small>
        </div>
    @endif
    @if(auth()->user()->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']) && ! in_array($onboarding->status, ['hr_approved', 'cancelled'], true))
        @php
            $itStartedForCancel = $onboarding->hasItStarted();
        @endphp
        <details class="onboarding-cancel-panel mt-3">
            <summary class="btn btn-outline-primary"><i class="bi bi-x-circle"></i> ขอยกเลิกคำขอ</summary>
            <form method="post" action="{{ route('hr.onboarding.cancel', $onboarding) }}" class="form-stack mt-2" onsubmit="return confirm('ยืนยันยกเลิกคำขอพนักงานใหม่นี้หรือไม่?');">
                @csrf
                @method('PATCH')
                <label>
                    <span>เหตุผลการยกเลิก</span>
                    <textarea class="form-control" name="cancel_reason" rows="2" required placeholder="เช่น พนักงานไม่มาเริ่มงาน / ติดต่อไม่ได้ / เลื่อนเริ่มงาน">{{ old('cancel_reason') }}</textarea>
                </label>
                @if($itStartedForCancel)
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="cancel_acknowledged" value="1" required>
                        <span class="form-check-label">ยืนยันว่า IT เริ่มเปิดระบบแล้ว ต้องให้ IT ตรวจสอบการยกเลิกก่อนปิดงาน</span>
                    </label>
                @endif
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i> ยืนยันขอยกเลิก</button>
            </form>
        </details>
    @endif
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>รายการเปิดระบบโดย IT</h2>
            <p>รับงานก่อนแก้ checklist เพื่อให้ทีมเห็นว่าใครกำลังดำเนินการอยู่</p>
        </div>
        <div class="button-row">
            @if($onboarding->claimedBy)
                <span class="status-pill status-soft">รับงานโดย {{ $onboarding->claimedBy->name }}{{ $onboarding->claimed_at ? ' · '.$onboarding->claimed_at->format('d/m/Y H:i') : '' }}</span>
            @else
                <span class="status-pill status-warning">ยังไม่มีคนรับงาน</span>
            @endif
            @if($canManageItOnboarding && ! $onboarding->claimed_by_id && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true))
                <form method="post" action="{{ route('it.onboarding.claim', $onboarding) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i> รับงาน</button>
                </form>
            @elseif($canManageItOnboarding && $claimedByMe && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true))
                <form method="post" action="{{ route('it.onboarding.release', $onboarding) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> ปล่อยงาน</button>
                </form>
            @endif
        </div>
    </div>

    @if($claimedByOther)
        <div class="alert-panel compact-alert">รายการนี้มี {{ $onboarding->claimedBy?->name ?? 'ทีม IT คนอื่น' }} กำลังดำเนินการอยู่ จึงเปิดดูได้แต่ยังแก้ checklist ไม่ได้</div>
    @endif
    @if($onboarding->status === 'cancel_requested')
        <div class="alert-panel compact-alert">
            <strong>HR ขอให้ยกเลิกคำขอ</strong>
            <p>{{ $onboarding->cancel_reason ?: 'ไม่มีเหตุผลระบุ' }}</p>
        </div>
    @endif

    @if($canManageItOnboarding)
        <form method="post" action="{{ route('it.onboarding.update', $onboarding) }}">
            @csrf
            @method('PATCH')
            <div class="content-grid onboarding-system-grid">
                @foreach($onboarding->systems as $system)
                    @php
                        $systemName = $system->system_name;
                        $lowerSystemName = \Illuminate\Support\Str::lower($systemName);
                        $isWdcPortal = $systemName === 'WDC Portal';
                        $isAssetSystem = $systemName === 'ทรัพย์สิน' || \Illuminate\Support\Str::contains($lowerSystemName, ['asset', 'inventory']);
                        $isEmailSystem = $systemName === 'EMAIL';
                        $isDirectorySystem = $systemName === 'Active Directory';
                        $usernameValue = $isWdcPortal ? $onboarding->employee_code : $system->username;
                    @endphp
                    <article class="list-card onboarding-system-card">
                        <div class="section-title">
                            <div>
                                <h3>{{ $systemName }}</h3>
                                <p>{{ $isWdcPortal ? 'รหัสเข้าใช้งาน WDC Portal ล็อกตามรหัสพนักงาน' : $system->requested_access }}</p>
                            </div>
                            <span class="status-pill">{{ $system->statusLabel() }}</span>
                        </div>
                        <div class="form-grid onboarding-system-form">
                            <label><span>สถานะ</span>
                                <select class="form-select" name="systems[{{ $system->id }}][status]" @disabled(! $canEditChecklist)>
                                    <option value="pending" @selected($system->status === 'pending')>รอดำเนินการ</option>
                                    <option value="provisioned" @selected($system->status === 'provisioned')>เปิดแล้ว</option>
                                    <option value="skipped" @selected($system->status === 'skipped')>ไม่ต้องเปิด</option>
                                </select>
                            </label>
                            @if(! $isAssetSystem)
                                <label><span>{{ $isWdcPortal ? 'รหัสเข้าใช้งาน WDC' : 'Username' }}</span>
                                    <input class="form-control" name="systems[{{ $system->id }}][username]" value="{{ $usernameValue }}" placeholder="username" @readonly($isWdcPortal) @disabled(! $canEditChecklist)>
                                </label>
                            @endif
                            @if($isEmailSystem)
                                <label><span>Email</span><input class="form-control" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}" placeholder="email@wdc.co.th" @disabled(! $canEditChecklist)></label>
                            @elseif($isDirectorySystem)
                                <label><span>Domain / Email อ้างอิง</span><input class="form-control" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}" placeholder="ถ้ามี" @disabled(! $canEditChecklist)></label>
                            @else
                                <input type="hidden" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}">
                            @endif
                            @if($isAssetSystem)
                                <label class="span-2"><span>ทรัพย์สิน</span>
                                    <select class="form-select" name="systems[{{ $system->id }}][it_asset_id]" @disabled(! $canEditChecklist)>
                                        <option value="">เลือกทรัพย์สิน</option>
                                        @foreach($availableAssets as $asset)
                                            <option value="{{ $asset->id }}" @selected($system->it_asset_id === $asset->id)>{{ $asset->code }} · {{ $asset->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                <input type="hidden" name="systems[{{ $system->id }}][it_asset_id]" value="{{ $system->it_asset_id }}">
                            @endif
                            <div class="span-2 onboarding-audit-note">
                                <span>ผู้เปิด: <strong>{{ $system->provisioner?->name ?? '-' }}</strong></span>
                                <span>{{ $system->provisioned_at?->format('d/m/Y H:i') ?: '' }}</span>
                            </div>
                            <label class="span-2"><span>หมายเหตุ</span><input class="form-control" name="systems[{{ $system->id }}][notes]" value="{{ $system->notes }}" placeholder="หมายเหตุ" @disabled(! $canEditChecklist)></label>
                        </div>
                    </article>
                @endforeach
            </div>
            <label class="form-label">หมายเหตุ IT</label>
            <textarea class="form-control mb-2" name="it_note" rows="3" @disabled(! $canEditChecklist)>{{ $onboarding->it_note }}</textarea>
            <div class="button-row">
                <button class="btn btn-outline-primary" type="submit" @disabled(! $canEditChecklist)><i class="bi bi-save"></i> บันทึกข้อมูล IT</button>
            </div>
        </form>

        @if($canEditChecklist)
            <form method="post" action="{{ route('it.onboarding.complete', $onboarding) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> อนุมัติเปิดระบบและส่งกลับ HR</button>
            </form>
        @endif
        @if($onboarding->status === 'cancel_requested' && ($claimedByMe || ! $onboarding->claimed_by_id || auth()->user()->canAccessAny(['admin.system.manage', 'admin.users.manage'])))
            <form method="post" action="{{ route('it.onboarding.cancel', $onboarding) }}" class="mt-3" onsubmit="return confirm('ยืนยันว่า IT ตรวจสอบการยกเลิกและคืนงานเรียบร้อยแล้ว?');">
                @csrf
                @method('PATCH')
                <label class="form-label">หมายเหตุ IT ก่อนยืนยันยกเลิก</label>
                <textarea class="form-control mb-2" name="it_note" rows="2">{{ $onboarding->it_note }}</textarea>
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-check2-circle"></i> ยืนยันยกเลิกและแจ้ง HR</button>
            </form>
        @endif
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>ระบบ</th><th>สถานะ</th><th>User</th><th>Email</th><th>ทรัพย์สิน</th><th>ผู้เปิด</th><th>หมายเหตุ</th></tr>
                </thead>
                <tbody>
                @foreach($onboarding->systems as $system)
                    <tr>
                        <td><strong>{{ $system->system_name }}</strong><small class="d-block muted">{{ $system->requested_access }}</small></td>
                        <td>{{ $system->statusLabel() }}</td>
                        <td>{{ ($system->system_name === 'WDC Portal' ? $onboarding->employee_code : $system->username) ?: '-' }}</td>
                        <td>{{ $system->email ?: '-' }}</td>
                        <td>{{ $system->asset?->code ?? '-' }}</td>
                        <td>{{ $system->provisioner?->name ?? '-' }}<small class="d-block muted">{{ $system->provisioned_at?->format('d/m/Y H:i') ?: '' }}</small></td>
                        <td>{{ $system->notes ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($onboarding->it_note)
            <div class="alert-panel"><strong>หมายเหตุ IT</strong><p>{{ $onboarding->it_note }}</p></div>
        @endif
    @endif
</section>
@endsection
