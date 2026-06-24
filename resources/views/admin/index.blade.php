@extends('layouts.app')

@section('title', 'Admin | WDC Portal')

@section('content')
<div class="button-row mb-3">
    @foreach($adminSections as $section)
        <a class="btn {{ $activeSection === $section['key'] ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.index', ['section' => $section['key']]) }}">
            <i class="bi {{ $section['icon'] }}"></i> {{ $section['label'] }}
        </a>
    @endforeach
</div>

@if($activeSection === 'system')
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
@endif

@if($activeSection === 'create-user' && $canCreateUsers)
<section class="panel" id="create-user">
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
                    @php
                        $roleIsRestricted = $role->permissions->pluck('key')->intersect(['admin.users.manage', 'admin.roles.manage', 'admin.activity.view', 'admin.system.manage'])->isNotEmpty();
                    @endphp
                    @if($canManageUsers || ! $roleIsRestricted)
                        <option value="{{ $role->id }}" @disabled($role->isSuperAdmin() && ! auth()->user()->isSuperAdmin())>{{ $role->name }}</option>
                    @endif
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

@if($activeSection === 'notifications')
<section class="panel" id="admin-notifications">
    <div class="section-title">
        <h2>แจ้งเตือนแอดมิน</h2>
        <span class="status-pill">{{ $adminNotifications->whereNull('read_at')->count() }} ยังไม่อ่าน</span>
    </div>
    <div class="content-grid">
        <section>
            <h3>รายการแจ้งเตือนล่าสุด</h3>
            <div class="item-list">
                @forelse($adminNotifications as $notification)
                    <a class="result-row" href="{{ $notification->url ?: route('admin.index', ['section' => 'notifications']) }}">
                        <strong>{{ $notification->title }}</strong>
                        <small>{{ $notification->body ?: '-' }} · {{ $notification->created_at->format('d/m/Y H:i') }}{{ $notification->read_at ? ' · อ่านแล้ว' : ' · ยังไม่อ่าน' }}</small>
                    </a>
                @empty
                    <div class="empty-state">ยังไม่มีแจ้งเตือนของแอดมิน</div>
                @endforelse
            </div>
        </section>
        <section>
            <h3>คำขอพนักงานใหม่ที่รอ IT</h3>
            <div class="item-list">
                @forelse($pendingAdminOnboardingRequests as $onboarding)
                    <article class="result-row">
                        <strong>{{ $onboarding->employee_code }} · {{ $onboarding->displayName() }}</strong>
                        <small>{{ $onboarding->statusLabel() }} · {{ optional($onboarding->start_date)->format('d/m/Y') ?: 'ยังไม่ระบุวันเริ่มงาน' }} · {{ $onboarding->position ?: '-' }} · {{ $onboarding->department?->name ?? $onboarding->business_unit ?? '-' }}</small>
                    </article>
                @empty
                    <div class="empty-state">ยังไม่มีคำขอพนักงานใหม่ที่รอ IT</div>
                @endforelse
            </div>
        </section>
    </div>
</section>
@endif

@if($activeSection === 'permissions' && ($canManageUsers || $canManageRoles || $canManageDirectory))
<section class="panel" id="permission-management">
    <div class="section-title">
        <h2>กำหนดสิทธิ์</h2>
        <span class="status-pill">{{ $users->count() }} รายการที่แสดง</span>
    </div>
    <h3>รายชื่อพนักงาน</h3>
    <p class="muted">ปุ่มสถานะนี้ใช้เปิด/ปิดการใช้งานและซ่อน/แสดงในหน้ารายชื่อพนักงาน กรณีพนักงานลาออกจะถูกย้ายไปสถานะไม่แสดง</p>
    <div class="table-responsive mb-4">
        <table class="table align-middle">
            <thead><tr><th>รหัส</th><th>ชื่อ</th><th>แผนก</th><th>สิทธิ์</th><th>สถานะ</th><th></th></tr></thead>
            <tbody>
            @foreach($users as $managedUser)
                <tr>
                    <td>{{ $managedUser->employee_code }}</td>
                    <td>{{ $managedUser->name }}</td>
                    <td>{{ $managedUser->employee?->department?->name }}</td>
                    <td>{{ $managedUser->role?->name }}</td>
                    <td>{{ $managedUser->is_active ? 'แสดงในรายชื่อ / ใช้งาน' : 'พนักงานลาออก / ไม่แสดง' }}</td>
                    <td>
                        @if($canManageUsers || $canManageDirectory)
                            <form method="post" action="{{ route('admin.users.access', $managedUser) }}">
                                @csrf
                                @method('PATCH')
                                @unless($managedUser->is_active)
                                    <input type="hidden" name="is_active" value="1">
                                @endunless
                                <button class="btn btn-sm btn-outline-secondary" @disabled(auth()->id() === $managedUser->id || ($managedUser->isSuperAdmin() && ! auth()->user()->isSuperAdmin()))>{{ $managedUser->is_active ? 'ระงับ' : 'เปิดใช้งาน' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
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
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($managedUser->is_active) @disabled(! ($canManageUsers || $canManageDirectory) || auth()->id() === $managedUser->id)>
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
                                <input class="form-control form-control-sm" name="name" value="{{ old('name', $managedUser->name) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>อีเมล</span>
                                <input class="form-control form-control-sm" type="email" name="email" value="{{ old('email', $managedUser->email) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>ชื่ออังกฤษ</span>
                                <input class="form-control form-control-sm" name="english_name" value="{{ old('english_name', $managedUser->employee?->english_name) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>ชื่อเล่นอังกฤษ</span>
                                <input class="form-control form-control-sm" name="english_nickname" value="{{ old('english_nickname', $managedUser->employee?->english_nickname) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>ชื่อไทย</span>
                                <input class="form-control form-control-sm" name="thai_name" value="{{ old('thai_name', $managedUser->employee?->thai_name) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>ชื่อเล่นไทย</span>
                                <input class="form-control form-control-sm" name="thai_nickname" value="{{ old('thai_nickname', $managedUser->employee?->thai_nickname ?? $managedUser->employee?->nickname) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>แผนก</span>
                                <select class="form-select form-select-sm" name="department_id" @disabled(! ($canManageUsers || $canManageDirectory))>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected($managedUser->employee?->department_id === $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label><span>ตำแหน่ง</span>
                                <input class="form-control form-control-sm" name="position" value="{{ old('position', $managedUser->employee?->position) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>เบอร์โทร</span>
                                <input class="form-control form-control-sm" name="phone" value="{{ old('phone', $managedUser->employee?->phone) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>เบอร์ต่อ</span>
                                <input class="form-control form-control-sm" name="extension_number" value="{{ old('extension_number', $managedUser->employee?->extension_number) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
                            </label>
                            <label><span>วันเริ่มงาน</span>
                                <input class="form-control form-control-sm" type="date" name="start_date" value="{{ old('start_date', $managedUser->employee?->start_date?->format('Y-m-d')) }}" @disabled(! ($canManageUsers || $canManageDirectory))>
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

                    @if($canManageUsers || $canManageDirectory)
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

@if($activeSection === 'role-template' && $canManageRoles)
<section class="panel" id="role-template">
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

@if($activeSection === 'activity-logs' && $canViewLogs)
<section class="panel" id="activity-logs">
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
