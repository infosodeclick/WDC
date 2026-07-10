@extends('layouts.app')

@section('title', 'IT | WDC Portal')

@section('content')
@php
    $activeItSection = request()->string('section')->toString();
    $activeItSection = in_array($activeItSection, ['onboarding', 'access', 'offboarding', 'helpdesk'], true) ? $activeItSection : 'onboarding';
@endphp
<div class="page-heading">
    <div>
        <h1>IT</h1>
        <p>งานพนักงานและ Helpdesk ของทีม IT</p>
    </div>
    <a class="btn btn-primary" href="{{ $itHelpdeskUrl }}"><i class="bi bi-plus-circle"></i> เปิดคำขอ IT</a>
</div>

<nav class="it-section-tabs" aria-label="เมนูงาน IT">
    <a class="btn {{ $activeItSection === 'onboarding' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('it.index', ['section' => 'onboarding']) }}"><i class="bi bi-person-plus"></i> พนักงานใหม่</a>
    <a class="btn {{ $activeItSection === 'access' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('it.index', ['section' => 'access']) }}"><i class="bi bi-key"></i> ทะเบียนสิทธิ์</a>
    <a class="btn {{ $activeItSection === 'offboarding' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('it.index', ['section' => 'offboarding']) }}"><i class="bi bi-person-dash"></i> พนักงานลาออก</a>
    <a class="btn {{ $activeItSection === 'helpdesk' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('it.index', ['section' => 'helpdesk']) }}"><i class="bi bi-life-preserver"></i> Helpdesk</a>
</nav>

<section class="panel" id="it-onboarding" @if($activeItSection !== 'onboarding') hidden @endif>
    <div class="section-title">
        <div>
            <h2>คิวพนักงานเริ่มงานใหม่</h2>
            <p>ทีม IT กดรับงานก่อนทำ checklist เพื่อกันการเปิดสิทธิ์ซ้ำกัน</p>
        </div>
        <div class="button-row">
            <a class="btn btn-outline-primary" href="{{ route('it.onboarding.export') }}"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            <a class="btn btn-outline-primary" href="{{ route('it.onboarding.export', ['format' => 'csv']) }}"><i class="bi bi-filetype-csv"></i> CSV</a>
            <span class="tag">{{ $onboardingRequests->count() }} รายการ</span>
        </div>
    </div>
    <div class="item-list onboarding-queue-list">
        @forelse($onboardingRequests as $onboarding)
            @php
                $claimedByMe = $onboarding->claimed_by_id === auth()->id();
                $claimedByOther = $onboarding->claimed_by_id && ! $claimedByMe;
                $canEditChecklist = $claimedByMe && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true);
                $systemTotal = $onboarding->systems->count();
                $systemReady = $onboarding->systems->where('status', 'provisioned')->count();
            @endphp
            <article class="list-card onboarding-queue-card">
                <div class="onboarding-queue-head">
                    <div>
                        <div class="meta-row">
                            <span class="status-pill">{{ $onboarding->statusLabel() }}</span>
                            @if($onboarding->claimedBy)
                                <span class="status-pill status-soft">รับงานโดย {{ $onboarding->claimedBy->name }}{{ $onboarding->claimed_at ? ' · '.$onboarding->claimed_at->format('d/m H:i') : '' }}</span>
                            @else
                                <span class="status-pill status-warning">ยังไม่มีคนรับงาน</span>
                            @endif
                            <span>{{ $onboarding->employee_code }} · {{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</span>
                        </div>
                        <h3><a class="text-link" href="{{ route('onboarding.show', $onboarding) }}">{{ $onboarding->displayName() }}</a></h3>
                        <p>{{ $onboarding->position ?: '-' }} · เริ่มงาน {{ optional($onboarding->start_date)->format('d/m/Y') ?: '-' }}</p>
                    </div>
                    <div class="button-row onboarding-queue-actions">
                        @if(! $onboarding->claimed_by_id && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true))
                            <form method="post" action="{{ route('it.onboarding.claim', $onboarding) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i> รับงาน</button>
                            </form>
                        @elseif($claimedByMe && ! in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true))
                            <form method="post" action="{{ route('it.onboarding.release', $onboarding) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> ปล่อยงาน</button>
                            </form>
                        @endif
                        <a class="btn btn-outline-primary" href="{{ route('onboarding.show', $onboarding) }}"><i class="bi bi-box-arrow-up-right"></i> เปิดรายละเอียด</a>
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

                <div class="onboarding-system-summary">
                    @forelse($onboarding->systems->take(4) as $system)
                        <span class="access-system-chip {{ $system->status === 'provisioned' ? 'is-ready' : ($system->status === 'skipped' ? 'is-skipped' : '') }}">
                            {{ $system->system_name }}
                            <small>{{ $system->statusLabel() }}</small>
                        </span>
                    @empty
                        <span class="muted">ยังไม่มีรายการระบบที่ต้องเปิด</span>
                    @endforelse
                    @if($systemTotal > 4)
                        <span class="status-pill status-soft">+{{ $systemTotal - 4 }} ระบบ</span>
                    @endif
                </div>

                <details class="onboarding-inline-details">
                    <summary>
                        <span><i class="bi bi-list-check"></i> Checklist เปิดระบบ</span>
                        <strong>{{ $systemReady }} / {{ $systemTotal }}</strong>
                    </summary>
                <form method="post" action="{{ route('it.onboarding.update', $onboarding) }}" class="mt-3">
                    @csrf
                    @method('PATCH')
                    <div class="table-responsive">
                        <table class="table align-middle onboarding-checklist-table">
                            <thead>
                                <tr>
                                    <th>ระบบ</th>
                                    <th>สถานะ</th>
                                    <th>User / Email</th>
                                    <th>ทรัพย์สิน</th>
                                    <th>ผู้เปิด</th>
                                    <th>หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($onboarding->systems as $system)
                                @php
                                    $systemName = $system->system_name;
                                    $lowerSystemName = \Illuminate\Support\Str::lower($systemName);
                                    $isWdcPortal = $systemName === 'WDC Portal';
                                    $isAssetSystem = $systemName === 'ทรัพย์สิน' || \Illuminate\Support\Str::contains($lowerSystemName, ['asset', 'inventory']);
                                    $usernameValue = $isWdcPortal ? $onboarding->employee_code : $system->username;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $systemName }}</strong>
                                        <small class="d-block muted">{{ $isWdcPortal ? 'รหัสเข้า WDC ล็อกตามรหัสพนักงาน' : $system->requested_access }}</small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="systems[{{ $system->id }}][status]" @disabled(! $canEditChecklist)>
                                            <option value="pending" @selected($system->status === 'pending')>รอดำเนินการ</option>
                                            <option value="provisioned" @selected($system->status === 'provisioned')>เปิดแล้ว</option>
                                            <option value="skipped" @selected($system->status === 'skipped')>ไม่ต้องเปิด</option>
                                        </select>
                                    </td>
                                    <td>
                                        @if(! $isAssetSystem)
                                            <input class="form-control form-control-sm mb-1" name="systems[{{ $system->id }}][username]" value="{{ $usernameValue }}" placeholder="username" @readonly($isWdcPortal) @disabled(! $canEditChecklist)>
                                            <input class="form-control form-control-sm" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}" placeholder="email@wdc.co.th" @disabled(! $canEditChecklist)>
                                        @else
                                            <input type="hidden" name="systems[{{ $system->id }}][username]" value="{{ $system->username }}">
                                            <input type="hidden" name="systems[{{ $system->id }}][email]" value="{{ $system->email }}">
                                            <span class="muted">เลือกทรัพย์สินด้านขวา</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isAssetSystem)
                                            <select class="form-select form-select-sm" name="systems[{{ $system->id }}][it_asset_id]" @disabled(! $canEditChecklist)>
                                                <option value="">ไม่ผูกทรัพย์สิน</option>
                                                @foreach($availableAssets as $asset)
                                                    <option value="{{ $asset->id }}" @selected($system->it_asset_id === $asset->id)>{{ $asset->code }} · {{ $asset->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="hidden" name="systems[{{ $system->id }}][it_asset_id]" value="{{ $system->it_asset_id }}">
                                            <span class="muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $system->provisioner?->name ?? '-' }}</strong>
                                        <small class="d-block muted">{{ $system->provisioned_at?->format('d/m/Y H:i') ?: '' }}</small>
                                    </td>
                                    <td><input class="form-control form-control-sm" name="systems[{{ $system->id }}][notes]" value="{{ $system->notes }}" placeholder="หมายเหตุ" @disabled(! $canEditChecklist)></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <label class="form-label">หมายเหตุ IT</label>
                    <textarea class="form-control mb-2" name="it_note" rows="2" @disabled(! $canEditChecklist)>{{ $onboarding->it_note }}</textarea>
                    <div class="button-row">
                        <button class="btn btn-outline-primary" type="submit" @disabled(! $canEditChecklist)><i class="bi bi-save"></i> บันทึก checklist</button>
                    </div>
                </form>
                @if($canEditChecklist)
                    <form method="post" action="{{ route('it.onboarding.complete', $onboarding) }}" class="mt-2">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> เปิดระบบเรียบร้อย แจ้ง HR</button>
                    </form>
                @endif
                @if($onboarding->status === 'cancel_requested' && ($claimedByMe || ! $onboarding->claimed_by_id || auth()->user()->canAccessAny(['admin.system.manage', 'admin.users.manage'])))
                    <form method="post" action="{{ route('it.onboarding.cancel', $onboarding) }}" class="mt-2" onsubmit="return confirm('ยืนยันว่า IT ตรวจสอบการยกเลิกและคืนงานเรียบร้อยแล้ว?');">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="it_note" value="{{ $onboarding->it_note }}">
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-check2-circle"></i> ยืนยันยกเลิกและแจ้ง HR</button>
                    </form>
                @endif
                </details>
            </article>
        @empty
            <div class="empty-state">ยังไม่มีรายการพนักงานใหม่จาก HR</div>
        @endforelse
    </div>
</section>

<section class="panel" id="it-access-registry" @if($activeItSection !== 'access') hidden @endif>
    <div class="section-title">
        <div>
            <h2>ทะเบียนสิทธิ์พนักงาน</h2>
            <p>ดูระบบที่เปิดไว้, user/email, ทรัพย์สิน และผู้ดำเนินการจาก checklist พนักงานเริ่มงานใหม่</p>
        </div>
        <span class="tag">{{ $itEmployeeAccessRows->count() }} รายการ</span>
    </div>
    @if($itEmployeeAccessRows->isNotEmpty())
        <div class="table-responsive it-access-table-wrap">
            <table class="table align-middle it-access-table">
                <thead>
                    <tr>
                        <th>พนักงาน</th>
                        <th>แผนก/BU</th>
                        <th>ระบบ / สิทธิ์</th>
                        <th>User / Email</th>
                        <th>ทรัพย์สิน</th>
                        <th>ผู้เปิดระบบ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itEmployeeAccessRows as $accessRequest)
                        <tr>
                            <td data-label="พนักงาน">
                                <strong>{{ $accessRequest->employee_code ?: '-' }}</strong>
                                <div class="employee-table-name">{{ $accessRequest->displayName() }}</div>
                                <small class="muted">{{ $accessRequest->thai_name ?: '-' }}</small>
                            </td>
                            <td data-label="แผนก/BU">
                                <strong>{{ $accessRequest->department?->name ?? $accessRequest->business_unit ?? '-' }}</strong>
                                <small class="d-block muted">{{ $accessRequest->position ?: '-' }}</small>
                            </td>
                            <td data-label="ระบบ / สิทธิ์">
                                <div class="access-chip-list">
                                    @forelse($accessRequest->systems as $system)
                                        <span class="access-system-chip {{ $system->status === 'provisioned' ? 'is-ready' : ($system->status === 'skipped' ? 'is-skipped' : '') }}">
                                            {{ $system->system_name }}
                                            <small>{{ $system->statusLabel() }}</small>
                                        </span>
                                    @empty
                                        <span class="muted">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-label="User / Email">
                                <div class="access-account-list">
                                    @forelse($accessRequest->systems as $system)
                                        @php
                                            $username = $system->system_name === 'WDC Portal' ? $accessRequest->employee_code : $system->username;
                                            $accountLine = collect([$username, $system->email])->filter()->implode(' / ');
                                        @endphp
                                        <div>
                                            <strong>{{ $system->system_name }}</strong>
                                            <span>{{ $accountLine ?: '-' }}</span>
                                        </div>
                                    @empty
                                        <span class="muted">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-label="ทรัพย์สิน">
                                @php
                                    $assets = $accessRequest->systems->pluck('asset')->filter();
                                @endphp
                                @forelse($assets as $asset)
                                    <span class="status-pill status-soft">{{ $asset->code }} · {{ $asset->name }}</span>
                                @empty
                                    <span class="muted">-</span>
                                @endforelse
                            </td>
                            <td data-label="ผู้เปิดระบบ">
                                <div class="access-account-list">
                                    @forelse($accessRequest->systems->where('status', 'provisioned') as $system)
                                        <div>
                                            <strong>{{ $system->system_name }}</strong>
                                            <span>{{ $system->provisioner?->name ?? '-' }}{{ $system->provisioned_at ? ' · '.$system->provisioned_at->format('d/m/Y H:i') : '' }}</span>
                                        </div>
                                    @empty
                                        <span class="muted">ยังไม่มีรายการเปิดระบบ</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-label="สถานะ">
                                <span class="status-pill">{{ $accessRequest->statusLabel() }}</span>
                                @if($accessRequest->claimedBy)
                                    <small class="d-block muted">รับงานโดย {{ $accessRequest->claimedBy->name }}</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">ยังไม่มีข้อมูลสิทธิ์พนักงานจาก HR</div>
    @endif
</section>

<section class="panel" id="it-offboarding" @if($activeItSection !== 'offboarding') hidden @endif>
    <div class="section-title">
        <div>
            <h2>คิวพนักงานลาออก</h2>
            <p>รับงานก่อนปิดระบบหรือรับคืนทรัพย์สิน เพื่อกันทีม IT ทำซ้ำกัน</p>
        </div>
        <span class="tag">{{ $offboardingRequests->count() }} รายการ</span>
    </div>
    <div class="item-list onboarding-queue-list">
        @forelse($offboardingRequests as $offboarding)
            @php
                $claimedByMe = $offboarding->claimed_by_id === auth()->id();
                $claimedByOther = $offboarding->claimed_by_id && ! $claimedByMe;
            @endphp
            <article class="list-card onboarding-queue-card">
                <div class="onboarding-queue-head">
                    <div>
                        <div class="meta-row">
                            <span class="status-pill">{{ $offboarding->statusLabel() }}</span>
                            @if($offboarding->claimedBy)
                                <span class="status-pill status-soft">รับงานโดย {{ $offboarding->claimedBy->name }}{{ $offboarding->claimed_at ? ' · '.$offboarding->claimed_at->format('d/m H:i') : '' }}</span>
                            @else
                                <span class="status-pill status-warning">ยังไม่มีคนรับงาน</span>
                            @endif
                            <span>{{ optional($offboarding->resignation_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันลาออก' }}</span>
                        </div>
                        <h3><a class="text-link" href="{{ route('offboarding.show', $offboarding) }}">{{ $offboarding->displayName() }}</a></h3>
                        <p>{{ $offboarding->position ?: '-' }} · {{ $offboarding->department ?: '-' }}</p>
                    </div>
                    <div class="button-row onboarding-queue-actions">
                        @if(! $offboarding->claimed_by_id && ! in_array($offboarding->status, ['it_completed', 'hr_approved'], true))
                            <form method="post" action="{{ route('it.offboarding.claim', $offboarding) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-primary" type="submit"><i class="bi bi-person-check"></i> รับงาน</button>
                            </form>
                        @elseif($claimedByMe && ! in_array($offboarding->status, ['it_completed', 'hr_approved'], true))
                            <form method="post" action="{{ route('it.offboarding.release', $offboarding) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-counterclockwise"></i> ปล่อยงาน</button>
                            </form>
                        @endif
                        <a class="btn btn-outline-primary" href="{{ route('offboarding.show', $offboarding) }}"><i class="bi bi-box-arrow-up-right"></i> เปิดรายละเอียด</a>
                    </div>
                </div>
                @if($claimedByOther)
                    <div class="alert-panel compact-alert">รายการนี้มี {{ $offboarding->claimedBy?->name ?? 'ทีม IT คนอื่น' }} กำลังดำเนินการอยู่</div>
                @endif
            </article>
        @empty
            <div class="empty-state">ยังไม่มีรายการพนักงานลาออกจาก HR</div>
        @endforelse
    </div>
</section>

<section class="it-helpdesk-section" id="it-helpdesk" @if($activeItSection !== 'helpdesk') hidden @endif>
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
</section>
@endsection
