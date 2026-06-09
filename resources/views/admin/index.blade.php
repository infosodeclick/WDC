@extends('layouts.app')

@section('title', 'Admin | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Admin</p>
        <h1>จัดการผู้ใช้งานและ Log</h1>
        <p>ควบคุมสิทธิ์ Employee, Supervisor, HR และ Admin</p>
    </div>
</div>

<section class="panel">
    <h2>เพิ่มผู้ใช้งาน</h2>
    <form method="post" action="{{ route('admin.users.store') }}" class="form-grid">
        @csrf
        <label><span>รหัสพนักงาน</span><input class="form-control" name="employee_code" required></label>
        <label><span>ชื่อ</span><input class="form-control" name="name" required></label>
        <label><span>อีเมล</span><input class="form-control" name="email" type="email"></label>
        <label><span>รหัสผ่าน</span><input class="form-control" name="password" type="password" required></label>
        <label><span>สิทธิ์</span><select class="form-select" name="role_id">@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label>
        <label><span>แผนก</span><select class="form-select" name="department_id">@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></label>
        <label><span>ตำแหน่ง</span><input class="form-control" name="position" required></label>
        <label><span>เบอร์โทร</span><input class="form-control" name="phone"></label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</button>
    </form>
</section>

<div class="content-grid">
    <section class="panel">
        <h2>ผู้ใช้งาน</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>รหัส</th><th>ชื่อ</th><th>สิทธิ์</th><th>สถานะ</th><th>แก้ไข</th></tr></thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->employee_code }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->role?->name }}</td>
                        <td>{{ $user->is_active ? 'ใช้งาน' : 'ระงับ' }}</td>
                        <td>
                            <form class="inline-form" method="post" action="{{ route('admin.users.update', $user) }}">
                                @csrf
                                @method('PATCH')
                                <select class="form-select form-select-sm" name="role_id">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <label class="form-check small-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($user->is_active)> ใช้งาน</label>
                                <button class="btn btn-sm btn-outline-primary">บันทึก</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Activity Logs</h2>
        <div class="item-list">
            @foreach($logs as $log)
                <div class="result-row">
                    <strong>{{ $log->action }}</strong>
                    <small>{{ $log->user?->employee_code ?? 'system' }} · {{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->description }}</small>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
