@extends('layouts.app')

@section('title', 'Admin | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Super Admin Console</p>
        <h1>ศูนย์หลังบ้านและสิทธิ์ผู้ใช้งาน</h1>
        <p>กำหนดว่าแต่ละคนทำอะไรได้ เห็นข้อมูลได้ถึงระดับไหน และให้หลังบ้านสอดคล้องกับเมนูหน้าบ้าน</p>
    </div>
    @if($canManageSystem)
        <div class="role-badge">สิทธิ์แอดมินสูงสุด</div>
    @endif
</div>

<div class="metric-grid">
    <div class="metric-card"><span>ผู้ใช้งานทั้งหมด</span><strong>{{ $totalUsers }}</strong><small>บัญชีในระบบ WDC</small></div>
    <div class="metric-card"><span>ใช้งานอยู่</span><strong>{{ $activeUsers }}</strong><small>ล็อกอินและเปิดเมนูได้ตามสิทธิ์</small></div>
    <div class="metric-card"><span>ระงับ</span><strong>{{ $suspendedUsers }}</strong><small>บัญชีที่ปิดการใช้งาน</small></div>
    <div class="metric-card"><span>Role</span><strong>{{ $roles->count() }}</strong><small>รวม Super Admin</small></div>
    <div class="metric-card"><span>Admin Access</span><strong>{{ $adminCapableUsers }}</strong><small>บัญชีที่แตะหลังบ้านได้</small></div>
</div>

<section class="panel">
    <div class="section-title">
        <h2>เมนูด้านซ้ายหน้าบ้าน</h2>
        <span class="status-pill">ผูกกับ Permission</span>
    </div>
    <div class="menu-permission-grid">
        @foreach($menuPermissions as $menuPermission)
            <div class="menu-permission-card">
                <strong>{{ $menuPermission['label'] }}</strong>
                <small>{{ collect($menuPermission['permissions'])->join(' / ') }}</small>
            </div>
        @endforeach
    </div>
</section>

@if($canManageUsers)
<section class="panel">
    <div class="section-title">
        <h2>เพิ่มผู้ใช้งาน</h2>
        <span class="status-pill">สร้างบัญชี WDC Login</span>
    </div>
    <form method="post" action="{{ route('admin.users.store') }}" class="form-grid">
        @csrf
        <label><span>รหัสพนักงาน</span><input class="form-control" name="employee_code" value="{{ old('employee_code') }}" required></label>
        <label><span>ชื่อที่แสดง</span><input class="form-control" name="name" value="{{ old('name') }}" required></label>
        <label><span>ชื่ออังกฤษ</span><input class="form-control" name="english_name" value="{{ old('english_name') }}"></label>
        <label><span>ชื่อเล่นอังกฤษ</span><input class="form-control" name="english_nickname" value="{{ old('english_nickname') }}"></label>
        <label><span>ชื่อไทย</span><input class="form-control" name="thai_name" value="{{ old('thai_name') }}"></label>
        <label><span>ชื่อเล่นไทย</span><input class="form-control" name="thai_nickname" value="{{ old('thai_nickname') }}"></label>
        <label><span>อีเมล</span><input class="form-control" name="email" type="email" value="{{ old('email') }}"></label>
        <label><span>รหัสผ่าน</span><input class="form-control" name="password" type="password" required></label>
        <label><span>Role</span>
            <select class="form-select" name="role_id">
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @disabled($role->isSuperAdmin() && ! auth()->user()->isSuperAdmin())>{{ $role->name }}</option>
                @endforeach
            </select>
        </label>
        <label><span>ขอบเขตข้อมูล</span>
            <select class="form-select" name="data_scope">
                <option value="">ตาม Role</option>
                @foreach($scopeLabels as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label><span>แผนก</span>
            <select class="form-select" name="department_id">
                @foreach($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
        </label>
        <label><span>ตำแหน่ง</span><input class="form-control" name="position" value="{{ old('position') }}" required></label>
        <label><span>เบอร์โทร</span><input class="form-control" name="phone" value="{{ old('phone') }}"></label>
        <label><span>เบอร์ต่อ</span><input class="form-control" name="extension_number" value="{{ old('extension_number') }}"></label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</button>
    </form>
</section>
@endif

@if($canManageUsers || $canManageRoles)
<section class="panel">
    <div class="section-title">
        <h2>สิทธิ์รายผู้ใช้</h2>
        <span class="status-pill">{{ $users->count() }} รายการที่แสดง</span>
    </div>
    <form method="get" action="{{ route('admin.index') }}" class="form-grid admin-filter">
        <label class="span-2"><span>ค้นหาผู้ใช้</span>
            <input class="form-control" name="q" value="{{ $userSearch }}" placeholder="รหัสพนักงาน ชื่อ อีเมล แผนก หรือตำแหน่ง">
        </label>
        <label><span>Role</span>
            <select class="form-select" name="role">
                <option value="">ทุก Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}" @selected($roleFilter === $role->slug)>{{ $role->name }}</option>
                @endforeach
            </select>
        </label>
        <label><span>สถานะ</span>
            <select class="form-select" name="status">
                <option value="">ทุกสถานะ</option>
                <option value="active" @selected($statusFilter === 'active')>ใช้งานอยู่</option>
                <option value="suspended" @selected($statusFilter === 'suspended')>ระงับ</option>
            </select>
        </label>
        <div class="button-row">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
            <a class="btn btn-outline-primary" href="{{ route('admin.index') }}">ล้างตัวกรอง</a>
        </div>
    </form>
    <div class="admin-user-list">
        @forelse($users as $managedUser)
            @php
                $overrideMap = $managedUser->permissionOverrides->mapWithKeys(fn ($permission) => [$permission->key => $permission->pivot->effect]);
                $effectiveKeys = $managedUser->effectivePermissionKeys();
            @endphp
            <article class="admin-user-card">
                <form method="post" action="{{ route('admin.users.access', $managedUser) }}">
                    @csrf
                    @method('PATCH')
                    <div class="admin-user-head">
                        <div>
                            <strong>{{ $managedUser->employee_code }} · {{ $managedUser->name }}</strong>
                            <small>{{ $managedUser->employee?->department?->name ?? '-' }} · {{ $managedUser->email ?? 'ไม่มีอีเมล' }}</small>
                        </div>
                        <span class="status-pill {{ $managedUser->is_active ? 'status-done' : 'status-open' }}">{{ $managedUser->is_active ? 'ใช้งาน' : 'ระงับ' }}</span>
                    </div>

                    <div class="admin-access-grid">
                        <label><span>Role</span>
                            <select class="form-select form-select-sm" name="role_id" @disabled(! $canManageUsers || ($managedUser->isSuperAdmin() && ! auth()->user()->isSuperAdmin()))>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected($managedUser->role_id === $role->id) @disabled($role->isSuperAdmin() && ! auth()->user()->isSuperAdmin())>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label><span>ขอบเขตข้อมูล</span>
                            <select class="form-select form-select-sm" name="data_scope" @disabled(! $canManageUsers)>
                                <option value="" @selected($managedUser->data_scope === null)>ตาม Role: {{ $scopeLabels[$managedUser->role?->default_data_scope ?? 'own'] ?? 'เฉพาะของตนเอง' }}</option>
                                @foreach($scopeLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($managedUser->data_scope === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-check small-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($managedUser->is_active) @disabled(! $canManageUsers || auth()->id() === $managedUser->id)>
                            <span class="form-check-label">เปิดใช้งาน</span>
                        </label>
                        <div class="permission-count">
                            <strong>{{ $effectiveKeys->count() }}</strong>
                            <span>สิทธิ์ใช้งานจริง</span>
                        </div>
                    </div>

                    <details class="permission-details">
                        <summary>ข้อมูลพนักงานสำหรับหน้ารายชื่อ</summary>
                        <div class="form-grid compact-form-grid">
                            <label><span>ชื่อที่แสดง</span>
                                <input class="form-control form-control-sm" name="name" value="{{ old('name', $managedUser->name) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>อีเมล</span>
                                <input class="form-control form-control-sm" type="email" name="email" value="{{ old('email', $managedUser->email) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>ชื่ออังกฤษ</span>
                                <input class="form-control form-control-sm" name="english_name" value="{{ old('english_name', $managedUser->employee?->english_name) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>ชื่อเล่นอังกฤษ</span>
                                <input class="form-control form-control-sm" name="english_nickname" value="{{ old('english_nickname', $managedUser->employee?->english_nickname) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>ชื่อไทย</span>
                                <input class="form-control form-control-sm" name="thai_name" value="{{ old('thai_name', $managedUser->employee?->thai_name) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>ชื่อเล่นไทย</span>
                                <input class="form-control form-control-sm" name="thai_nickname" value="{{ old('thai_nickname', $managedUser->employee?->thai_nickname ?? $managedUser->employee?->nickname) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>แผนก</span>
                                <select class="form-select form-select-sm" name="department_id" @disabled(! $canManageUsers)>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected($managedUser->employee?->department_id === $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span>ตำแหน่ง</span>
                                <input class="form-control form-control-sm" name="position" value="{{ old('position', $managedUser->employee?->position) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>เบอร์โทร</span>
                                <input class="form-control form-control-sm" name="phone" value="{{ old('phone', $managedUser->employee?->phone) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>เบอร์ต่อ</span>
                                <input class="form-control form-control-sm" name="extension_number" value="{{ old('extension_number', $managedUser->employee?->extension_number) }}" @disabled(! $canManageUsers)>
                            </label>
                            <label><span>วันเริ่มงาน</span>
                                <input class="form-control form-control-sm" type="date" name="start_date" value="{{ old('start_date', $managedUser->employee?->start_date?->format('Y-m-d')) }}" @disabled(! $canManageUsers)>
                            </label>
                        </div>
                    </details>

                    <details class="permission-details">
                        <summary>ปรับสิทธิ์รายคน</summary>
                        <div class="permission-matrix">
                            @foreach($permissions as $group => $groupPermissions)
                                <section>
                                    <h3>{{ $group }}</h3>
                                    @foreach($groupPermissions as $permission)
                                        @php($effect = $overrideMap[$permission->key] ?? null)
                                        <div class="permission-row">
                                            <div>
                                                <strong>{{ $permission->name }}</strong>
                                                <small>{{ $permission->description }}</small>
                                            </div>
                                            <label class="small-check">
                                                <input class="form-check-input" type="checkbox" name="permission_grants[]" value="{{ $permission->key }}" @checked($effect === 'grant') @disabled(! $canManageRoles)>
                                                เพิ่ม
                                            </label>
                                            <label class="small-check">
                                                <input class="form-check-input" type="checkbox" name="permission_denies[]" value="{{ $permission->key }}" @checked($effect === 'deny') @disabled(! $canManageRoles || $managedUser->isSuperAdmin())>
                                                ปิด
                                            </label>
                                        </div>
                                    @endforeach
                                </section>
                            @endforeach
                        </div>
                    </details>

                    @if($canManageUsers)
                        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-shield-check"></i> บันทึกสิทธิ์</button>
                    @endif
                </form>
            </article>
        @empty
            <div class="empty-state">ไม่พบผู้ใช้ตามตัวกรองที่เลือก</div>
        @endforelse
    </div>
</section>
@endif

@if($canManageRoles)
<section class="panel">
    <div class="section-title">
        <h2>Role Template</h2>
        <span class="status-pill">สิทธิ์เริ่มต้นของแต่ละกลุ่ม</span>
    </div>
    <div class="role-template-grid">
        @foreach($roles as $role)
            <article class="role-template-card">
                <form method="post" action="{{ route('admin.roles.permissions', $role) }}">
                    @csrf
                    @method('PATCH')
                    <div class="admin-user-head">
                        <div>
                            <strong>{{ $role->name }}</strong>
                            <small>{{ $role->description }} · {{ $role->users_count }} users</small>
                        </div>
                        @if($role->isSuperAdmin())
                            <span class="status-pill status-primary">สูงสุด</span>
                        @endif
                    </div>
                    <label><span>ขอบเขตข้อมูลเริ่มต้น</span>
                        <select class="form-select form-select-sm" name="default_data_scope">
                            @foreach($scopeLabels as $key => $label)
                                <option value="{{ $key }}" @selected($role->default_data_scope === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="permission-matrix compact-matrix">
                        @foreach($permissions as $group => $groupPermissions)
                            <section>
                                <h3>{{ $group }}</h3>
                                @foreach($groupPermissions as $permission)
                                    <label class="permission-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->key }}" @checked($role->isSuperAdmin() || $role->permissions->contains('key', $permission->key)) @disabled($role->isSuperAdmin())>
                                        <span>{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-save"></i> บันทึก Role</button>
                </form>
            </article>
        @endforeach
    </div>
</section>
@endif

@if($canViewLogs)
<section class="panel">
    <div class="section-title">
        <h2>Activity Logs</h2>
        <span class="status-pill">{{ $logs->count() }} รายการล่าสุด</span>
    </div>
    <div class="item-list">
        @foreach($logs as $log)
            <div class="result-row">
                <strong>{{ $log->action }}</strong>
                <small>{{ $log->user?->employee_code ?? 'system' }} · {{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->description }}</small>
            </div>
        @endforeach
    </div>
</section>
@endif
@endsection
