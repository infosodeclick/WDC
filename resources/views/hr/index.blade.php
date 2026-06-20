@extends('layouts.app')

@section('title', 'HR Portal | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">HR Portal</p>
        <h1>จัดการ HR</h1>
        <p>จัดการพนักงาน ประกาศ เอกสาร และเรื่องร้องเรียน</p>
    </div>
</div>

@if($canManageAnnouncements)
    <section class="panel">
        <h2>สร้างประกาศ</h2>
        <form method="post" action="{{ route('hr.announcements.store') }}" class="form-grid">
            @csrf
            <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="title" required></label>
            <label><span>หมวด</span>
                <select class="form-select" name="category" required>
                    <option>ประกาศ</option>
                    <option>นโยบาย</option>
                </select>
            </label>
            <label><span>แผนก</span>
                <select class="form-select" name="department_id">
                    <option value="">ทุกแผนก</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="body" rows="3" required></textarea></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_pinned" value="1"><span class="form-check-label">ปักหมุด</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_urgent" value="1"><span class="form-check-label">ด่วน</span></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-megaphone"></i> สร้างประกาศ</button>
        </form>
    </section>
@endif

<div class="content-grid">
    @if($canManageEmployees)
        <section class="panel">
            <h2>พนักงาน</h2>
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
                            <td>{{ $employee->is_active ? 'ใช้งาน' : 'ระงับ' }}</td>
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
        <section class="panel">
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
