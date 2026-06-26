@extends('layouts.app')

@section('title', 'HR | WDC Portal')

@section('content')
@php
    $hrMenu = [
        ['section' => 'dashboard', 'label' => 'แดชบอร์ด', 'icon' => 'bi-speedometer2', 'show' => true],
        ['section' => 'employees', 'label' => 'รายชื่อพนักงาน', 'icon' => 'bi-people', 'show' => $canManageEmployees],
        ['section' => 'offboarding', 'label' => 'พนักงานลาออก', 'icon' => 'bi-person-dash', 'show' => $canManageEmployees],
        ['section' => 'announcements', 'label' => 'สร้างประกาศ', 'icon' => 'bi-megaphone', 'show' => $canManageAnnouncements],
        ['section' => 'complaints', 'label' => 'เรื่องร้องเรียนล่าสุด', 'icon' => 'bi-shield-check', 'show' => $canReviewComplaints],
    ];
@endphp

<div class="button-row mb-3">
    @foreach($hrMenu as $item)
        @if($item['show'])
            <a class="btn {{ $activeSection === $item['section'] ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('hr.index', ['section' => $item['section']]) }}">
                <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</div>

@if($activeSection === 'dashboard')
    <div class="metric-grid mb-3">
        <div class="metric-card"><span>พนักงานทั้งหมด</span><strong>{{ number_format($employeeCount) }}</strong><small>ตามสิทธิ์ข้อมูลที่เข้าถึงได้</small></div>
        <div class="metric-card"><span>ใช้งานอยู่</span><strong>{{ number_format($activeEmployeeCount) }}</strong><small>แสดงในระบบ</small></div>
        <div class="metric-card"><span>พนักงานลาออก/ไม่แสดง</span><strong>{{ number_format($inactiveEmployeeCount) }}</strong><small>ถูกปิดการใช้งาน</small></div>
        @if($canManageOnboarding)
            <div class="metric-card"><span>พนักงานใหม่รอดำเนินการ</span><strong>{{ number_format($pendingOnboardingCount) }}</strong><small>รอ IT หรือ HR อนุมัติ</small></div>
        @endif
        @if($canManageEmployees)
            <div class="metric-card"><span>คำขอแก้โปรไฟล์</span><strong>{{ number_format($pendingProfileChangeCount) }}</strong><small>รอ HR ตรวจสอบ</small></div>
            <div class="metric-card"><span>พนักงานลาออกรอดำเนินการ</span><strong>{{ number_format($pendingOffboardingCount) }}</strong><small>รอ IT หรือ HR ปิดบัญชี</small></div>
        @endif
        @if($canReviewComplaints)
            <div class="metric-card"><span>เรื่องร้องเรียน</span><strong>{{ number_format($complaintCount) }}</strong><small>รายการล่าสุดที่เกี่ยวข้อง</small></div>
        @endif
    </div>

    <div class="content-grid">
        @if($canManageOnboarding)
            <section class="panel">
                <div class="section-title">
                    <h2>คำขอพนักงานใหม่</h2>
                    <a class="text-link" href="{{ route('hr.index', ['section' => 'onboarding']) }}">เปิดเมนู</a>
                </div>
                <div class="item-list">
                    @forelse($onboardingRequests->take(5) as $onboarding)
                        <div class="result-row">
                            <strong>{{ $onboarding->employee_code }} · {{ $onboarding->displayName() }}</strong>
                            <small>{{ $onboarding->statusLabel() }} · {{ optional($onboarding->start_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันเริ่มงาน' }}</small>
                        </div>
                    @empty
                        <div class="empty-state">ยังไม่มีรายการพนักงานใหม่</div>
                    @endforelse
                </div>
            </section>
        @endif

        @if($canManageEmployees)
            <section class="panel">
                <div class="section-title">
                    <h2>คำขอแก้ข้อมูลโปรไฟล์</h2>
                    <a class="text-link" href="{{ route('hr.index', ['section' => 'profile-requests']) }}">เปิดเมนู</a>
                </div>
                <div class="item-list">
                    @forelse($profileChangeRequests->take(5) as $profileRequest)
                        <div class="result-row">
                            <strong>{{ $profileRequest->user?->employee_code }} · {{ $profileRequest->user?->name }}</strong>
                            <small>{{ $profileRequest->field }} · {{ $profileRequest->requested_value ?: '-' }}</small>
                        </div>
                    @empty
                        <div class="empty-state">ไม่มีคำขอแก้ข้อมูลที่รออนุมัติ</div>
                    @endforelse
                </div>
            </section>
        @endif

        @if($canReviewComplaints)
            <section class="panel">
                <div class="section-title">
                    <h2>เรื่องร้องเรียนล่าสุด</h2>
                    <a class="text-link" href="{{ route('hr.index', ['section' => 'complaints']) }}">เปิดเมนู</a>
                </div>
                <div class="item-list">
                    @forelse($complaints->take(5) as $complaint)
                        <div class="result-row">
                            <strong>{{ $complaint->subject }}</strong>
                            <small>{{ $complaint->status }} · {{ $complaint->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                    @empty
                        <div class="empty-state">ยังไม่มีเรื่องร้องเรียนล่าสุด</div>
                    @endforelse
                </div>
            </section>
        @endif

        @if($canManageAnnouncements)
            <section class="panel">
                <div class="section-title">
                    <h2>ประกาศล่าสุด</h2>
                    <a class="text-link" href="{{ route('hr.index', ['section' => 'announcements']) }}">เปิดเมนู</a>
                </div>
                <div class="item-list">
                    @forelse($announcements->take(5) as $announcement)
                        <div class="result-row">
                            <strong>{{ $announcement->announcement_no }} · {{ $announcement->title }}</strong>
                            <small>{{ $announcement->category }} · {{ $announcement->published_at?->format('d/m/Y') ?: '-' }}</small>
                        </div>
                    @empty
                        <div class="empty-state">ยังไม่มีประกาศล่าสุด</div>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
@endif

@if($activeSection === 'employees' && $canManageEmployees)
    <section class="panel">
        <div class="section-title">
            <div></div>
            <div class="button-row">
                @if($canManageOnboarding)
                    <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'onboarding']) }}"><i class="bi bi-person-plus"></i> เพิ่มพนักงานใหม่</a>
                @endif
                <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'offboarding']) }}"><i class="bi bi-person-dash"></i> แจ้งพนักงานลาออก</a>
                <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'profile-requests']) }}"><i class="bi bi-person-gear"></i> คำขอแก้ข้อมูลโปรไฟล์</a>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i> ส่งออกข้อมูล
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('hr.employees.export', ['format' => 'xls']) }}">Excel (.xls)</a></li>
                        <li><a class="dropdown-item" href="{{ route('hr.employees.export', ['format' => 'csv']) }}">CSV (.csv)</a></li>
                    </ul>
                </div>
            </div>
        </div>
        @if($employeeRows->isNotEmpty())
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            @foreach(array_keys($employeeRows->first()) as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($employeeRows as $row)
                            <tr>
                                @foreach($row as $value)
                                    <td>{{ $value ?: '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">ยังไม่มีรายชื่อพนักงานในระบบ</div>
        @endif
    </section>
@endif

@if($activeSection === 'offboarding' && $canManageEmployees)
    <section class="panel">
        <div class="section-title">
            <div>
                <h2>แจ้งพนักงานลาออก</h2>
                <p class="muted">HR ส่งรายการให้ IT ปิดระบบและรับคืนทรัพย์สิน ก่อน HR ปิดบัญชีและย้ายไปสถานะลาออก</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'employees']) }}" aria-label="ปิดหน้าแจ้งพนักงานลาออก"><i class="bi bi-x-lg"></i></a>
        </div>
        <form method="post" action="{{ route('hr.offboarding.store') }}" class="form-grid">
            @csrf
            <label class="span-2"><span>พนักงาน</span>
                <select class="form-select" name="employee_user_id" required>
                    <option value="">เลือกพนักงาน</option>
                    @foreach($employees->where('is_active', true) as $employeeUser)
                        <option value="{{ $employeeUser->id }}">{{ $employeeUser->employee_code }} · {{ $employeeUser->name }} · {{ $employeeUser->employee?->department?->name ?? '-' }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>วันที่ลาออก / วันสุดท้าย</span><input class="form-control" type="date" name="resignation_date"></label>
            <label class="span-3"><span>หมายเหตุ HR</span><textarea class="form-control" name="hr_note" rows="3" placeholder="เช่น ปิดระบบหลังเลิกงาน รับคืนอุปกรณ์ทั้งหมด"></textarea></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งให้ IT ปิดระบบ</button>
        </form>
    </section>

    <section class="panel">
        <h2>คำขอพนักงานลาออก</h2>
        <div class="item-list">
            @forelse($offboardingRequests as $offboarding)
                <article class="list-card onboarding-request-card">
                    <div class="meta-row">
                        <span class="status-pill">{{ $offboarding->statusLabel() }}</span>
                        <span>{{ optional($offboarding->resignation_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันลาออก' }}</span>
                    </div>
                    <h3><a class="text-link" href="{{ route('offboarding.show', $offboarding) }}">{{ $offboarding->displayName() }}</a></h3>
                    <p>{{ $offboarding->thai_name ?: '-' }} · {{ $offboarding->position ?: '-' }} · {{ $offboarding->department ?: '-' }}</p>
                    <div class="asset-chip-list onboarding-system-summary">
                        @foreach($offboarding->systems as $system)
                            <span><strong>{{ $system->system_name }}</strong><small>{{ $system->statusLabel() }}</small></span>
                        @endforeach
                    </div>
                    @if($offboarding->status === 'it_completed')
                        <form method="post" action="{{ route('hr.offboarding.approve', $offboarding) }}" class="mt-2">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-primary" type="submit"><i class="bi bi-person-x"></i> ปิดบัญชีและย้ายเป็นลาออก</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state">ยังไม่มีรายการพนักงานลาออก</div>
            @endforelse
        </div>
    </section>
@endif

@if($activeSection === 'onboarding' && $canManageOnboarding)
    <section class="panel" id="new-employee">
        <div class="section-title">
            <div>
                <h2>เพิ่มพนักงานใหม่</h2>
                <p class="muted">HR กรอกข้อมูลครั้งเดียว ระบบจะส่งรายการให้ IT เปิดระบบ เมื่อ IT กดเสร็จ HR จะอนุมัติให้แสดงในหน้ารายชื่อพนักงานได้ทันที</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('hr.index', ['section' => 'employees']) }}" aria-label="ปิดหน้าฟอร์มเพิ่มพนักงานใหม่"><i class="bi bi-x-lg"></i></a>
        </div>
        <form method="post" action="{{ route('hr.onboarding.store') }}" class="form-grid">
            @csrf
            <label><span>รหัสพนักงาน</span><input class="form-control" name="employee_code" required placeholder="EMP00125"></label>
            <label><span>ชื่อ ภาษาอังกฤษ</span><input class="form-control" name="english_first_name" required placeholder="Somchai"></label>
            <label><span>นามสกุล ภาษาอังกฤษ</span><input class="form-control" name="english_last_name" required placeholder="Jaidee"></label>
            <label><span>ชื่อเล่น ภาษาอังกฤษ</span><input class="form-control" name="english_nickname" placeholder="Som"></label>
            <label><span>ชื่อ ภาษาไทย</span><input class="form-control" name="thai_first_name" placeholder="สมชาย"></label>
            <label><span>นามสกุล ภาษาไทย</span><input class="form-control" name="thai_last_name" placeholder="ใจดี"></label>
            <label><span>ชื่อเล่น ภาษาไทย</span><input class="form-control" name="thai_nickname" placeholder="สม"></label>
            <label><span>ตำแหน่ง</span>
                <select class="form-select" name="position">
                    <option value="">เลือกตำแหน่ง</option>
                    @foreach($onboardingPositions as $position)
                        <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>แผนก/BU</span>
                <select class="form-select" name="department_id">
                    <option value="">เลือกแผนก/BU</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>ทีม</span>
                <select class="form-select" name="team">
                    <option value="">เลือกทีม</option>
                    @foreach($onboardingTeams as $team)
                        <option value="{{ $team }}" @selected(old('team') === $team)>{{ $team }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>สาขา</span>
                <select class="form-select" name="location">
                    <option value="">เลือกสาขา</option>
                    @foreach($onboardingLocations as $location)
                        <option value="{{ $location }}" @selected(old('location') === $location)>{{ $location }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>อีเมล</span><input class="form-control" name="corporate_email" type="email" placeholder="name@wdc.co.th"></label>
            <label><span>โทร</span><input class="form-control" name="personal_phone" placeholder="08x-xxx-xxxx"></label>
            <label><span>เบอร์โต๊ะ</span>
                <select class="form-select" name="extension_number">
                    <option value="">เลือกเบอร์โต๊ะ</option>
                    @foreach($onboardingDeskPhones as $deskPhone)
                        <option value="{{ $deskPhone }}" @selected(old('extension_number') === $deskPhone)>{{ $deskPhone }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>วันที่เริ่มงาน</span><input class="form-control" name="start_date" type="date"></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งให้ IT เปิดระบบ</button>
        </form>
    </section>

    <section class="panel">
        <h2>คำขอพนักงานใหม่</h2>
        <div class="item-list">
            @forelse($onboardingRequests as $onboarding)
                <article class="list-card onboarding-request-card">
                    <div class="meta-row">
                        <span class="status-pill">{{ $onboarding->statusLabel() }}</span>
                        <span>{{ $onboarding->employee_code }} · {{ optional($onboarding->start_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันเริ่มงาน' }}</span>
                    </div>
                    <h3><a class="text-link" href="{{ route('onboarding.show', $onboarding) }}">{{ $onboarding->displayName() }}</a></h3>
                    <p>{{ $onboarding->thai_name ?: '-' }} · {{ $onboarding->position ?: '-' }} · {{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</p>
                    <div class="asset-chip-list onboarding-system-summary">
                        @foreach($onboarding->systems as $system)
                            <span><strong>{{ $system->system_name }}</strong><small>{{ $system->statusLabel() }} {{ $system->username ? '· '.$system->username : '' }}</small></span>
                        @endforeach
                    </div>
                    @if($onboarding->cancel_reason)
                        <div class="alert-panel compact-alert">
                            <strong>เหตุผลการยกเลิก</strong>
                            <p>{{ $onboarding->cancel_reason }}</p>
                        </div>
                    @endif
                    @if($onboarding->status === 'it_completed')
                        <form class="onboarding-publish-form" method="post" action="{{ route('hr.onboarding.publish', $onboarding) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <label class="form-label">รูปพนักงานสำหรับหน้ารายชื่อ</label>
                            <input class="form-control mb-2" type="file" name="photo" accept="image/*">
                            <div class="onboarding-publish-date">
                                <label class="form-label">วันที่เริ่มแสดงในรายชื่อ</label>
                                <input class="form-control" type="date" name="published_at" value="{{ old('published_at', now()->toDateString()) }}">
                            </div>
                            <small class="form-help d-block">ถ้าเลือกวันอนาคต ระบบจะอนุมัติไว้ก่อน และจะแสดงในรายชื่อพนักงานเมื่อถึงวันที่กำหนด</small>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> อนุมัติให้แสดงในรายชื่อพนักงาน</button>
                        </form>
                    @endif
                    @if(! in_array($onboarding->status, ['hr_approved', 'cancelled'], true))
                        @php
                            $itStartedForCancel = $onboarding->hasItStarted();
                        @endphp
                        <details class="onboarding-cancel-panel">
                            <summary class="btn btn-outline-primary"><i class="bi bi-x-circle"></i> ขอยกเลิกคำขอ</summary>
                            <form method="post" action="{{ route('hr.onboarding.cancel', $onboarding) }}" class="form-stack mt-2" onsubmit="return confirm('ยืนยันยกเลิกคำขอพนักงานใหม่นี้หรือไม่?');">
                                @csrf
                                @method('PATCH')
                                <label>
                                    <span>เหตุผลการยกเลิก</span>
                                    <textarea class="form-control" name="cancel_reason" rows="2" required placeholder="เช่น พนักงานไม่มาเริ่มงาน / ติดต่อไม่ได้ / เลื่อนเริ่มงาน"></textarea>
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
                </article>
            @empty
                <div class="empty-state">ยังไม่มีรายการพนักงานใหม่</div>
            @endforelse
        </div>
    </section>
@endif

@if($activeSection === 'announcements' && $canManageAnnouncements)
    <section class="panel">
        <h2>สร้างประกาศ</h2>
        <form method="post" action="{{ route('hr.announcements.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            <label><span>เลขที่ประกาศ</span><input class="form-control" name="announcement_no" placeholder="เช่น HR-2026-001"></label>
            <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="title" required></label>
            <label><span>หมวด</span>
                <select class="form-select" name="category" required>
                    <option>ประกาศ</option>
                    <option>กิจกรรม</option>
                    <option>นโยบาย</option>
                </select>
            </label>
            <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="body" rows="3" required></textarea></label>
            <label class="span-2"><span>อัปโหลดไฟล์ PDF / JPG / PNG / ไฟล์ที่เกี่ยวข้อง</span><input class="form-control" type="file" name="files[]" multiple></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_pinned" value="1"><span class="form-check-label">ปักหมุด</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_urgent" value="1"><span class="form-check-label">ด่วน</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="popup_enabled" value="1"><span class="form-check-label">แสดง Popup ตอนเข้าใช้งานครั้งแรก</span></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-megaphone"></i> สร้างประกาศ</button>
        </form>
    </section>
@endif

@if($activeSection === 'profile-requests' && $canManageEmployees)
    <section class="panel">
        <h2>คำขอแก้ข้อมูลโปรไฟล์</h2>
        <div class="item-list">
            @forelse($profileChangeRequests as $profileRequest)
                <div class="result-row">
                    <strong>{{ $profileRequest->user?->employee_code }} · {{ $profileRequest->user?->name }}</strong>
                    <small>ขอแก้ {{ $profileRequest->field }} จาก {{ $profileRequest->current_value ?: '-' }} เป็น {{ $profileRequest->requested_value ?: '-' }}</small>
                    <div class="button-row">
                        <form method="post" action="{{ route('hr.profile-requests.review', $profileRequest) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="approved">
                            <button class="btn btn-sm btn-primary" type="submit">อนุมัติ</button>
                        </form>
                        <form method="post" action="{{ route('hr.profile-requests.review', $profileRequest) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <button class="btn btn-sm btn-outline-primary" type="submit">ไม่อนุมัติ</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">ไม่มีคำขอแก้ข้อมูลที่รออนุมัติ</div>
            @endforelse
        </div>
    </section>
@endif

@if($activeSection === 'complaints' && $canReviewComplaints)
    <section class="panel">
        <h2>เรื่องร้องเรียนล่าสุด</h2>
        <div class="item-list">
            @forelse($complaints as $complaint)
                <div class="result-row"><strong>{{ $complaint->subject }}</strong><small>{{ $complaint->type }} · {{ $complaint->status }}</small></div>
            @empty
                <div class="empty-state">ยังไม่มีเรื่องร้องเรียนล่าสุด</div>
            @endforelse
        </div>
    </section>
@endif
@endsection
