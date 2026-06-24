@extends('layouts.app')

@section('title', 'HR Portal | WDC Portal')

@section('content')
<div class="button-row mb-3">
    @if($canManageOnboarding)
        <a class="btn btn-primary" href="#new-employee"><i class="bi bi-person-plus"></i> เพิ่มพนักงานใหม่</a>
    @endif
    @if($canManageAnnouncements)
        <a class="btn btn-outline-primary" href="#create-announcement"><i class="bi bi-megaphone"></i> สร้างประกาศ</a>
    @endif
    @if($canManageEmployees)
        <a class="btn btn-outline-primary" href="#profile-change-requests"><i class="bi bi-person-gear"></i> คำขอแก้ข้อมูลโปรไฟล์</a>
    @endif
    @if($canReviewComplaints)
        <a class="btn btn-outline-primary" href="#recent-complaints"><i class="bi bi-shield-check"></i> เรื่องร้องเรียนล่าสุด</a>
    @endif
</div>

@if($canManageOnboarding)
    <section class="panel" id="new-employee">
        <h2>เพิ่มพนักงานใหม่</h2>
        <p class="muted">HR กรอกข้อมูลครั้งเดียว ระบบจะส่งรายการให้ IT เปิดระบบ เมื่อ IT กดเสร็จ HR จะอนุมัติให้แสดงในหน้ารายชื่อพนักงานได้ทันที</p>
        <form method="post" action="{{ route('hr.onboarding.store') }}" class="form-grid">
            @csrf
            <label><span>รหัสพนักงาน</span><input class="form-control" name="employee_code" required placeholder="EMP00125"></label>
            <label><span>ชื่ออังกฤษ</span><input class="form-control" name="english_name" required placeholder="Somchai Jaidee"></label>
            <label><span>ชื่อไทย</span><input class="form-control" name="thai_name" placeholder="สมชาย ใจดี"></label>
            <label><span>ชื่อเล่นอังกฤษ</span><input class="form-control" name="english_nickname" placeholder="Som"></label>
            <label><span>ชื่อเล่นไทย</span><input class="form-control" name="thai_nickname" placeholder="สม"></label>
            <label><span>แผนก</span>
                <select class="form-select" name="department_id">
                    <option value="">เลือกแผนก</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>ตำแหน่ง</span><input class="form-control" name="position" placeholder="Sales Executive"></label>
            <label><span>แผนก/BU</span><input class="form-control" name="business_unit" placeholder="Sales BU1"></label>
            <label><span>ทีม</span><input class="form-control" name="team" placeholder="Team A"></label>
            <label><span>สาขา</span><input class="form-control" name="location" placeholder="Lumpini"></label>
            <label><span>อีเมลองค์กรที่ต้องการ</span><input class="form-control" name="corporate_email" type="email" placeholder="name@wdc.co.th"></label>
            <label><span>เบอร์โทร / เบอร์โต๊ะ</span><input class="form-control" name="personal_phone" placeholder="08x-xxx-xxxx"></label>
            <label><span>เบอร์ต่อ</span><input class="form-control" name="extension_number" placeholder="8004"></label>
            <label><span>วันเริ่มงาน</span><input class="form-control" name="start_date" type="date"></label>
            <fieldset class="span-3">
                <legend>ระบบที่ให้ IT เปิด</legend>
                <div class="button-row">
                    @foreach(['WDC Portal', 'Email', 'ERP', 'POS', 'VPN', 'Shared Drive', 'Printer', 'AI-CRM'] as $system)
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="requested_systems[]" value="{{ $system }}" @checked(in_array($system, ['WDC Portal', 'Email'], true))><span class="form-check-label">{{ $system }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <label class="span-2"><span>ระบบอื่น ๆ</span><input class="form-control" name="other_systems" placeholder="ใส่หลายระบบคั่นด้วย comma หรือขึ้นบรรทัดใหม่"></label>
            <label class="span-3"><span>หมายเหตุ HR ถึง IT</span><textarea class="form-control" name="hr_note" rows="3" placeholder="รายละเอียดสิทธิ์ แผนก หรืออุปกรณ์ที่ต้องเตรียม"></textarea></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งให้ IT เปิดระบบ</button>
        </form>
    </section>

    <section class="panel" id="create-announcement">
        <h2>คำขอพนักงานใหม่</h2>
        <div class="item-list">
            @forelse($onboardingRequests as $onboarding)
                <article class="list-card">
                    <div class="meta-row">
                        <span class="status-pill">{{ $onboarding->statusLabel() }}</span>
                        <span>{{ $onboarding->employee_code }} · {{ optional($onboarding->start_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันเริ่มงาน' }}</span>
                    </div>
                    <h3>{{ $onboarding->displayName() }}</h3>
                    <p>{{ $onboarding->thai_name ?: '-' }} · {{ $onboarding->position ?: '-' }} · {{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</p>
                    <div class="asset-chip-list">
                        @foreach($onboarding->systems as $system)
                            <span><strong>{{ $system->system_name }}</strong><small>{{ $system->statusLabel() }} {{ $system->username ? '· '.$system->username : '' }}</small></span>
                        @endforeach
                    </div>
                    @if($onboarding->status === 'it_completed')
                        <form class="mt-3" method="post" action="{{ route('hr.onboarding.publish', $onboarding) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <label class="form-label">รูปพนักงานสำหรับหน้ารายชื่อ</label>
                            <input class="form-control mb-2" type="file" name="photo" accept="image/*">
                            <textarea class="form-control mb-2" name="hr_note" rows="2" placeholder="หมายเหตุ HR ก่อนเผยแพร่"></textarea>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> อนุมัติให้แสดงในรายชื่อพนักงาน</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state">ยังไม่มีรายการพนักงานใหม่</div>
            @endforelse
        </div>
    </section>
@endif

@if($canManageAnnouncements)
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

<div class="content-grid">
    @if($canManageEmployees)
        <section class="panel" id="employee-list">
            <h2 id="profile-change-requests">คำขอแก้ข้อมูลโปรไฟล์</h2>
            <div class="item-list mb-4">
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

            <h2>รายชื่อพนักงาน</h2>
            <p class="muted">ปุ่มสถานะนี้ใช้เปิด/ปิดการใช้งานและซ่อน/แสดงในหน้ารายชื่อพนักงาน กรณีพนักงานลาออกจะถูกย้ายไปสถานะไม่แสดง</p>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>รหัส</th><th>ชื่อ</th><th>แผนก</th><th>สิทธิ์</th><th>สถานะ</th><th></th></tr></thead>
                    <tbody>
                    @foreach($employees as $employee)
                        <tr>
                            <td>{{ $employee->employee_code }}</td>
                            <td>{{ $employee->name }}</td>
                            <td>{{ $employee->employee?->department?->name }}</td>
                            <td>{{ $employee->role?->name }}</td>
                            <td>{{ $employee->is_active ? 'แสดงในรายชื่อ / ใช้งาน' : 'พนักงานลาออก / ไม่แสดง' }}</td>
                            <td>
                                <form method="post" action="{{ route('hr.employees.status', $employee) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary" @disabled(auth()->id() === $employee->id)>{{ $employee->is_active ? 'ระงับ' : 'เปิดใช้งาน' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($canReviewComplaints)
        <section class="panel" id="recent-complaints">
            <h2>เรื่องร้องเรียนล่าสุด</h2>
            <div class="item-list">
                @foreach($complaints as $complaint)
                    <div class="result-row"><strong>{{ $complaint->subject }}</strong><small>{{ $complaint->type }} · {{ $complaint->status }}</small></div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
