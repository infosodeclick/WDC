@extends('layouts.app')

@section('title', 'คำขอพนักงานใหม่ | WDC Portal')

@section('content')
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
            <p>ตรวจสอบข้อมูลพนักงานใหม่ก่อนเปิดระบบ อีเมล และสิทธิ์ที่เกี่ยวข้อง</p>
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
</section>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>รายการเปิดระบบโดย IT</h2>
            <p>บันทึก user, email, ทรัพย์สิน หรือหมายเหตุของแต่ละระบบ ก่อนกดอนุมัติส่งกลับ HR</p>
        </div>
        @if($onboarding->it_completed_at)
            <span class="status-pill">ส่งกลับ HR แล้ว {{ $onboarding->it_completed_at->format('d/m/Y H:i') }}</span>
        @endif
    </div>

    @if($canManageItOnboarding)
        <form method="post" action="{{ route('it.onboarding.update', $onboarding) }}">
            @csrf
            @method('PATCH')
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>ระบบ</th><th>สถานะ</th><th>User / Email</th><th>ทรัพย์สิน</th><th>หมายเหตุ</th></tr>
                    </thead>
                    <tbody>
                    @foreach($onboarding->systems as $system)
                        <tr>
                            <td><strong>{{ $system->system_name }}</strong><small class="d-block muted">{{ $system->requested_access }}</small></td>
                            <td>
                                <select class="form-select form-select-sm" name="systems[{{ $system->id }}][status]">
                                    <option value="pending" @selected($system->status === 'pending')>รอดำเนินการ</option>
                                    <option value="provisioned" @selected($system->status === 'provisioned')>เปิดแล้ว</option>
                                    <option value="skipped" @selected($system->status === 'skipped')>ไม่ต้องเปิด</option>
                                </select>
                            </td>
                            <td>
                                <input class="form-control form-control-sm mb-1" name="systems[{{ $system->id }}][username]" value="{{ $system->username }}" placeholder="username">
                                <input class="form-control form-control-sm" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}" placeholder="email@wdc.co.th">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="systems[{{ $system->id }}][it_asset_id]">
                                    <option value="">ไม่ผูกทรัพย์สิน</option>
                                    @foreach($availableAssets as $asset)
                                        <option value="{{ $asset->id }}" @selected($system->it_asset_id === $asset->id)>{{ $asset->code }} · {{ $asset->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input class="form-control form-control-sm" name="systems[{{ $system->id }}][notes]" value="{{ $system->notes }}" placeholder="หมายเหตุ"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <label class="form-label">หมายเหตุ IT</label>
            <textarea class="form-control mb-2" name="it_note" rows="3">{{ $onboarding->it_note }}</textarea>
            <div class="button-row">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> บันทึกข้อมูล IT</button>
            </div>
        </form>

        @if($onboarding->status !== 'it_completed')
            <form method="post" action="{{ route('it.onboarding.complete', $onboarding) }}" class="mt-3">
                @csrf
                @method('PATCH')
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> อนุมัติเปิดระบบและส่งกลับ HR</button>
            </form>
        @endif
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th>ระบบ</th><th>สถานะ</th><th>User</th><th>Email</th><th>ทรัพย์สิน</th><th>หมายเหตุ</th></tr>
                </thead>
                <tbody>
                @foreach($onboarding->systems as $system)
                    <tr>
                        <td><strong>{{ $system->system_name }}</strong><small class="d-block muted">{{ $system->requested_access }}</small></td>
                        <td>{{ $system->statusLabel() }}</td>
                        <td>{{ $system->username ?: '-' }}</td>
                        <td>{{ $system->email ?: '-' }}</td>
                        <td>{{ $system->asset?->code ?? '-' }}</td>
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
