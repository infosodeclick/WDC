@extends('layouts.app')

@section('title', 'IT | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">IT</p>
        <h1>ศูนย์งาน IT</h1>
        <p>รายการพนักงานใหม่จาก HR, Helpdesk และงานระบบที่ทีม IT ต้องดำเนินการ</p>
    </div>
    <a class="btn btn-primary" href="{{ $itHelpdeskUrl }}"><i class="bi bi-plus-circle"></i> เปิดคำขอ IT</a>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <h2>รายการแจ้งสำหรับพนักงานเริ่มงานใหม่</h2>
            <p>สร้างตามข้อมูลที่ HR แจ้งมา เพื่อเปิดอีเมล user ระบบ และผูกทรัพย์สินให้พนักงานใหม่</p>
        </div>
        <span class="tag">{{ $onboardingRequests->count() }} รายการ</span>
    </div>
    <div class="item-list">
        @forelse($onboardingRequests as $onboarding)
            <article class="list-card">
                <div class="meta-row">
                    <span class="status-pill">{{ $onboarding->statusLabel() }}</span>
                    <span>{{ $onboarding->employee_code }} · {{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</span>
                </div>
                <h3>{{ $onboarding->displayName() }}</h3>
                <p>{{ $onboarding->position ?: '-' }} · เริ่มงาน {{ optional($onboarding->start_date)->format('d/m/Y') ?: '-' }}</p>
                @if($onboarding->hr_note)
                    <div class="alert-panel"><strong>หมายเหตุ HR</strong><p>{{ $onboarding->hr_note }}</p></div>
                @endif
                <form method="post" action="{{ route('it.onboarding.update', $onboarding) }}" class="mt-3">
                    @csrf
                    @method('PATCH')
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>ระบบ</th><th>สถานะ</th><th>User / Email</th><th>ทรัพย์สิน</th><th>หมายเหตุ</th></tr></thead>
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
                    <textarea class="form-control mb-2" name="it_note" rows="2">{{ $onboarding->it_note }}</textarea>
                    <div class="button-row">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> บันทึก</button>
                    </div>
                </form>
                @if($onboarding->status !== 'it_completed')
                    <form method="post" action="{{ route('it.onboarding.complete', $onboarding) }}" class="mt-2">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> เปิดระบบเรียบร้อย แจ้ง HR</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="empty-state">ยังไม่มีรายการพนักงานใหม่จาก HR</div>
        @endforelse
    </div>
</section>

<div class="metric-grid">
    <div class="metric-card"><span>งานใหม่</span><strong>{{ $newTickets }}</strong><small>ส่งคำขอแล้ว</small></div>
    <div class="metric-card"><span>งานค้าง</span><strong>{{ $pendingTickets }}</strong><small>ตรวจสอบ/รับเรื่อง/ดำเนินการ</small></div>
    <div class="metric-card"><span>งานเสร็จ</span><strong>{{ $doneTickets }}</strong><small>อนุมัติหรือปิดงานแล้ว</small></div>
</div>

<div class="item-list">
    @forelse($requests as $requestItem)
        <article class="list-card">
            <div class="meta-row">
                <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                <span>{{ $requestItem->template->name }} · {{ $requestItem->requester->employee?->department?->name }}</span>
            </div>
            <h3>{{ $requestItem->title }}</h3>
            <p>{{ $requestItem->details }}</p>
            <div class="meta-row">
                <span>ผู้แจ้ง: {{ $requestItem->requester->name }}</span>
                <span>{{ $requestItem->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @if($requestItem->legacy_reference)
                <div class="legacy-ref"><i class="bi bi-link-45deg"></i> เอกสารเดิม: {{ $requestItem->legacy_reference }}</div>
            @endif
            <a class="text-link" href="{{ route('workflows.index', ['template' => $requestItem->workflow_template_id, 'status' => $requestItem->status]) }}">เปิดใน Workflow</a>
        </article>
    @empty
        <div class="empty-state">ยังไม่มีงาน IT Helpdesk ค้างใน Workflow</div>
    @endforelse
</div>

{{ $requests->links() }}
@endsection
