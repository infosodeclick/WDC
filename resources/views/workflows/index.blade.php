@extends('layouts.app')

@section('title', 'SmartFlow Work Center | WDC Portal')

@section('content')
@php
    $documentCreateView = in_array($activeView, ['authorizations', 'statistics', 'dynamic_fields', 'user_list', 'permission_map', 'user_group_diagram', 'password'], true) ? 'all' : $activeView;
    $createPanelOpen = $canCreate && ((int) $activeTemplateId > 0 || $errors->any());
@endphp
<div class="page-heading">
    <div>
        <p class="eyebrow">SmartFlow Work Center</p>
        <h1>ศูนย์เอกสารและอนุมัติ WDC</h1>
        <p>ใช้งานแทน SmartFlow เดิมสำหรับเอกสาร คำขอ งานรออนุมัติ งาน IT และการติดตามสถานะในเว็บเดียว</p>
    </div>
    <div class="role-badge">{{ $canManage ? 'เห็นงานตามสิทธิ์' : 'เห็นงานของฉัน' }}</div>
</div>

<section class="smartflow-toolbar" aria-label="เมนูศูนย์เอกสารและอนุมัติ">
    <nav class="smartflow-primary-tabs" aria-label="มุมมองเอกสาร">
        @foreach(['all', 'tasks', 'favorites', 'authorizations'] as $key)
            @php($tab = $menuTabs[$key])
            <a class="smartflow-primary-tab {{ $activeView === $key ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => $key]) }}" @if($activeView === $key) aria-current="page" @endif>
                <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="smartflow-toolbar-actions">
    @if($canCreate)
        <details class="smartflow-new-document">
            <summary>
                <i class="bi bi-plus-circle"></i>
                <span>สร้างคำขอ</span>
            </summary>
            <div class="smartflow-new-document-menu">
                <div class="smartflow-menu-heading">
                    <strong>เลือก Workflow</strong>
                    <small>เลือกแบบคำขอที่ต้องการ ระบบจะออกเลขเอกสารให้อัตโนมัติ</small>
                </div>
                @foreach($templateCatalog as $template)
                    <a href="{{ route('workflows.index', ['view' => $documentCreateView, 'template' => $template->id]) }}#workflow-create-form">
                        <span>{{ $template->name }}</span>
                        <small>
                            Workflow #{{ $template->legacy_workflow_id ?? '-' }}
                            @if($template->service_team)
                                · {{ $template->service_team }}
                            @endif
                        </small>
                    </a>
                @endforeach
            </div>
        </details>
    @endif

    <details class="smartflow-tools-menu">
        <summary>
            <i class="bi bi-three-dots" aria-hidden="true"></i>
            <span>เครื่องมือ</span>
        </summary>
        <div class="smartflow-tools-popover">
            <div class="smartflow-menu-heading">
                <strong>บัญชีและเครื่องมือระบบ</strong>
                <small>แสดงเฉพาะเมนูที่บัญชีนี้มีสิทธิ์ใช้งาน</small>
            </div>
            <a class="{{ $activeView === 'password' ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => 'password']) }}">
                <i class="bi bi-key" aria-hidden="true"></i>
                <span><strong>เปลี่ยนรหัสผ่าน</strong><small>จัดการรหัสผ่าน WDC</small></span>
            </a>
            @if($canManage)
                @foreach(['statistics', 'user_list', 'permission_map', 'user_group_diagram', 'dynamic_fields', 'workflows'] as $key)
                    @php($tab = $menuTabs[$key])
                    <a class="{{ $activeView === $key ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => $key]) }}">
                        <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>
                        <span><strong>{{ $tab['label'] }}</strong><small>{{ $tab['description'] ?? 'ตั้งค่าและตรวจสอบระบบ' }}</small></span>
                    </a>
                @endforeach
                <a href="{{ route('workflows.export', request()->except('page')) }}">
                    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                    <span><strong>ส่งออก Excel/CSV</strong><small>ดาวน์โหลดข้อมูลตามตัวกรองปัจจุบัน</small></span>
                </a>
            @endif
        </div>
    </details>
    </div>
</section>

@if($activeView === 'authorizations')
    <section class="panel smartflow-authorization-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Approval Authorizations</p>
                <h2>Manage approval delegation</h2>
                <p>Give another user temporary approval authority and see authorizations given to you, matching the SmartFlow authorization flow.</p>
            </div>
            <span class="status-pill">{{ $authorizationsGiven->where('status', 'active')->count() }} active</span>
        </div>

        <details class="smartflow-inline-create" @if($errors->any()) open @endif>
            <summary><i class="bi bi-plus-circle" aria-hidden="true"></i> สร้างการมอบอำนาจ</summary>
        <form method="post" action="{{ route('workflows.authorizations.store') }}" class="smartflow-authorization-form">
            @csrf
            <label>
                <span>Authorized user</span>
                <select class="form-select" name="authorized_user_id" required>
                    <option value="">Select a user</option>
                    @foreach($authorizationUsers as $authorizationUser)
                        <option value="{{ $authorizationUser->id }}">
                            {{ $authorizationUser->name }} · {{ $authorizationUser->employee_code ?? '-' }}
                            @if($authorizationUser->employee?->department)
                                · {{ $authorizationUser->employee->department->name }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Valid from</span>
                <input class="form-control" name="valid_from" type="datetime-local">
            </label>
            <label>
                <span>Valid until</span>
                <input class="form-control" name="valid_until" type="datetime-local">
            </label>
            <label class="span-3">
                <span>Reason</span>
                <textarea class="form-control" name="reason" rows="2" placeholder="e.g., Vacation coverage"></textarea>
            </label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-shield-check"></i> Create Authorization</button>
        </form>
        </details>
    </section>

    <div class="smartflow-authorization-grid">
        <section class="panel">
            <div class="section-title">
                <h2>Authorizations You've Given</h2>
            </div>
            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>Authorized User</th>
                            <th>Valid From</th>
                            <th>Valid Until</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($authorizationsGiven as $authorization)
                            <tr>
                                <td>
                                    <strong>{{ $authorization->authorizedUser?->name }}</strong>
                                    <small>{{ $authorization->authorizedUser?->employee_code ?? '-' }}</small>
                                </td>
                                <td>{{ $authorization->valid_from?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $authorization->valid_until?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $authorization->reason ?: '-' }}</td>
                                <td><span class="status-pill status-{{ $authorization->status }}">{{ ucfirst($authorization->status) }}</span></td>
                                <td>
                                    @if($authorization->status === 'active')
                                        <form method="post" action="{{ route('workflows.authorizations.revoke', $authorization) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Revoke</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No approval authorizations given.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="section-title">
                <h2>Authorizations Given To You</h2>
            </div>
            <div class="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>Authorizer</th>
                            <th>Valid From</th>
                            <th>Valid Until</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($authorizationsReceived as $authorization)
                            <tr>
                                <td>
                                    <strong>{{ $authorization->authorizer?->name }}</strong>
                                    <small>{{ $authorization->authorizer?->employee_code ?? '-' }}</small>
                                </td>
                                <td>{{ $authorization->valid_from?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $authorization->valid_until?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $authorization->reason ?: '-' }}</td>
                                <td><span class="status-pill status-{{ $authorization->status }}">{{ ucfirst($authorization->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No one has authorized you to approve documents on their behalf.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@elseif($activeView === 'password')
    <section class="panel smartflow-password-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Account Security</p>
                <h2>Change Password</h2>
                <p>Use the same SmartFlow password flow inside WDC. Enter your current password, then set a new password for future logins.</p>
            </div>
            <span class="status-pill"><i class="bi bi-shield-lock"></i> Logged in</span>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="post" action="{{ route('password.change.update') }}" class="smartflow-password-form">
            @csrf
            @method('PATCH')
            <label>
                <span>Current Password *</span>
                <input class="form-control @error('current_password') is-invalid @enderror" name="current_password" type="password" placeholder="Current Password" autocomplete="current-password" required>
                @error('current_password')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </label>
            <label>
                <span>New Password *</span>
                <input class="form-control @error('password') is-invalid @enderror" name="password" type="password" placeholder="New Password" autocomplete="new-password" required>
                @error('password')
                    <small class="form-error">{{ $message }}</small>
                @enderror
            </label>
            <label>
                <span>New Password (again) *</span>
                <input class="form-control" name="password_confirmation" type="password" placeholder="New Password (again)" autocomplete="new-password" required>
            </label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Change Password</button>
        </form>
    </section>
@elseif($activeView === 'statistics')
    <section class="panel smartflow-statistics-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Approval Statistics</p>
                <h2>Track workflow efficiency</h2>
                <p>สรุปประสิทธิภาพการอนุมัติและงานเอกสารใน WDC ตามรูปแบบ SmartFlow เดิม</p>
            </div>
            <div class="button-row">
                <a class="btn btn-sm btn-primary" href="#smartflow-user-statistics"><i class="bi bi-person-lines-fill"></i> User Statistics</a>
                <a class="btn btn-sm btn-outline-primary" href="#smartflow-workflow-statistics"><i class="bi bi-diagram-3"></i> Workflow Statistics</a>
            </div>
        </div>

        <div class="smartflow-stat-summary">
            <div>
                <span>Total Documents</span>
                <strong>{{ number_format($statisticsData['summary']['documents'] ?? 0) }}</strong>
            </div>
            <div>
                <span>Pending Approvals</span>
                <strong>{{ number_format($statisticsData['summary']['pending'] ?? 0) }}</strong>
            </div>
            <div>
                <span>Processed</span>
                <strong>{{ number_format($statisticsData['summary']['processed'] ?? 0) }}</strong>
            </div>
            <div>
                <span>Active Users</span>
                <strong>{{ number_format($statisticsData['summary']['active_users'] ?? 0) }}</strong>
            </div>
        </div>
    </section>

    <div class="smartflow-statistics-grid">
        <section class="panel" id="smartflow-user-statistics">
            <div class="section-title">
                <h2>User Statistics</h2>
                <span class="status-pill">{{ $statisticsData['users']->count() }} users</span>
            </div>
            <div class="responsive-table">
                <table class="smartflow-stat-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total Decisions</th>
                            <th>Pending Approvals</th>
                            <th>Processed</th>
                            <th>Avg. Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statisticsData['users'] as $userStat)
                            <tr>
                                <td>
                                    <span class="smartflow-stat-user">
                                        <span class="smartflow-avatar">{{ $userStat['initial'] }}</span>
                                        <span>
                                            <strong>{{ $userStat['name'] }}</strong>
                                            <small>{{ $userStat['email'] ?? '-' }}</small>
                                        </span>
                                    </span>
                                </td>
                                <td>{{ number_format($userStat['total_decisions']) }}</td>
                                <td>{{ $userStat['pending_approvals'] ? number_format($userStat['pending_approvals']) : '-' }}</td>
                                <td>{{ number_format($userStat['processed']) }}</td>
                                <td>{{ $userStat['avg_response'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No decision statistics yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="smartflow-workflow-statistics">
            <div class="section-title">
                <h2>Workflow Statistics</h2>
                <span class="status-pill">{{ $statisticsData['workflows']->count() }} workflows</span>
            </div>
            <div class="responsive-table">
                <table class="smartflow-stat-table">
                    <thead>
                        <tr>
                            <th>Workflow</th>
                            <th>Documents</th>
                            <th>Pending</th>
                            <th>Completed</th>
                            <th>Avg. Completion Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($statisticsData['workflows'] as $workflowStat)
                            <tr>
                                <td>
                                    <strong>{{ $workflowStat['workflow'] }}</strong>
                                    <small>{{ $workflowStat['service_team'] ?? '-' }}</small>
                                </td>
                                <td>{{ number_format($workflowStat['documents']) }}</td>
                                <td>{{ $workflowStat['pending'] ? number_format($workflowStat['pending']) : '-' }}</td>
                                <td>{{ number_format($workflowStat['completed']) }}</td>
                                <td>{{ $workflowStat['avg_completion'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No workflow statistics yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@elseif($activeView === 'dynamic_fields')
    <section class="panel smartflow-dynamic-fields-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Dynamic Fields</p>
                <h2>จัดการฟิลด์ในแบบคำขอ</h2>
                <p>เพิ่มและแก้ไขช่องกรอกของแต่ละ Workflow ได้จากจุดเดียว โดยไม่ต้องแก้โครงสร้างแบบคำขอทั้งหมด</p>
            </div>
            <span class="status-pill">{{ $dynamicFieldsData->count() }} ฟิลด์</span>
        </div>

        @if($canManageSystem && $templates->isNotEmpty())
            <details class="dynamic-field-create" @if($errors->hasAny(['field_key', 'field_label', 'field_type', 'field_help', 'field_options'])) open @endif>
                <summary><i class="bi bi-plus-circle" aria-hidden="true"></i> เพิ่มฟิลด์ใหม่</summary>
                <form method="POST" action="{{ route('workflows.fields.store', ['template' => old('workflow_template_id', $templates->first()?->id)]) }}" class="dynamic-field-editor" data-dynamic-field-create-form>
                    @csrf
                    <label class="field-wide">Workflow
                        <select name="workflow_template_id" data-dynamic-field-template required>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" @selected((string) old('workflow_template_id') === (string) $template->id) data-store-url="{{ route('workflows.fields.store', $template) }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>รหัสฟิลด์
                        <input name="field_key" value="{{ old('field_key') }}" placeholder="เช่น cost_center" pattern="[A-Za-z0-9_-]+" required>
                    </label>
                    <label>ชื่อที่แสดง
                        <input name="field_label" value="{{ old('field_label') }}" placeholder="เช่น Cost Center" required>
                    </label>
                    <label>ประเภทฟิลด์
                        <select name="field_type" required>
                            @foreach(['text' => 'ข้อความสั้น', 'textarea' => 'ข้อความหลายบรรทัด', 'rich_text' => 'ข้อความแบบจัดรูปแบบ', 'checkbox' => 'ใช่ / ไม่ใช่', 'select' => 'ตัวเลือก', 'file' => 'ไฟล์แนบ', 'date' => 'วันที่', 'number' => 'ตัวเลข', 'tel' => 'เบอร์โทร', 'email' => 'อีเมล'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('field_type', 'text') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field-wide">คำอธิบาย
                        <input name="field_help" value="{{ old('field_help') }}" placeholder="ข้อความช่วยอธิบายใต้ช่องกรอก">
                    </label>
                    <label class="field-wide">ตัวเลือก
                        <textarea name="field_options" rows="3" placeholder="กรอกหนึ่งตัวเลือกต่อหนึ่งบรรทัด ใช้เฉพาะประเภทตัวเลือก">{{ old('field_options') }}</textarea>
                    </label>
                    <label class="check-row field-wide"><input type="checkbox" name="field_required" value="1" @checked(old('field_required'))> ต้องกรอกข้อมูล</label>
                    <div class="dynamic-field-form-actions field-wide">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่มฟิลด์</button>
                    </div>
                </form>
            </details>
        @endif

        <div class="dynamic-field-grid">
            @forelse($dynamicFieldsData as $field)
                <article class="dynamic-field-card">
                    <div class="dynamic-field-head">
                        <div>
                            <h3>{{ $field['label'] }}</h3>
                            <small>{{ $field['workflow'] }} @if($field['workflow_id']) · Workflow #{{ $field['workflow_id'] }} @endif</small>
                        </div>
                        <span class="status-pill">{{ ucwords(str_replace('_', ' ', $field['type'])) }}</span>
                    </div>
                    <div class="dynamic-field-meta">
                        @if($field['required'])
                            <span>Required</span>
                        @endif
                        @if($field['options']->isNotEmpty())
                            <span>Options Configured</span>
                        @endif
                        @if($field['help'])
                            <span>{{ $field['help'] }}</span>
                        @endif
                        <span>{{ $field['category'] }}</span>
                    </div>
                    @if($field['options']->isNotEmpty())
                        <div class="dynamic-field-options">
                            @foreach($field['options']->take(5) as $option)
                                <span>{{ $option }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if($canManageSystem)
                        <div class="dynamic-field-actions">
                            <details class="dynamic-field-edit">
                                <summary><i class="bi bi-pencil-square" aria-hidden="true"></i> แก้ไข</summary>
                                <form method="POST" action="{{ route('workflows.fields.update', ['template' => $field['template_id'], 'fieldKey' => $field['key']]) }}" class="dynamic-field-editor">
                                    @csrf
                                    @method('PATCH')
                                    <label>ชื่อที่แสดง
                                        <input name="field_label" value="{{ $field['label'] }}" required>
                                    </label>
                                    <label>ประเภทฟิลด์
                                        <select name="field_type" required>
                                            @foreach(['text' => 'ข้อความสั้น', 'textarea' => 'ข้อความหลายบรรทัด', 'rich_text' => 'ข้อความแบบจัดรูปแบบ', 'checkbox' => 'ใช่ / ไม่ใช่', 'select' => 'ตัวเลือก', 'file' => 'ไฟล์แนบ', 'date' => 'วันที่', 'number' => 'ตัวเลข', 'tel' => 'เบอร์โทร', 'email' => 'อีเมล'] as $value => $label)
                                                <option value="{{ $value }}" @selected($field['type'] === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field-wide">คำอธิบาย
                                        <input name="field_help" value="{{ $field['help'] }}">
                                    </label>
                                    <label class="field-wide">ตัวเลือก
                                        <textarea name="field_options" rows="3">{{ $field['options']->implode("\n") }}</textarea>
                                    </label>
                                    <label class="check-row field-wide"><input type="checkbox" name="field_required" value="1" @checked($field['required'])> ต้องกรอกข้อมูล</label>
                                    <div class="dynamic-field-form-actions field-wide">
                                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> บันทึก</button>
                                    </div>
                                </form>
                            </details>
                            <details class="dynamic-field-delete">
                                <summary><i class="bi bi-trash3" aria-hidden="true"></i> ลบ</summary>
                                <div class="dynamic-field-delete-confirm">
                                    <p>ยืนยันลบฟิลด์ <strong>{{ $field['label'] }}</strong> ออกจาก {{ $field['workflow'] }}?</p>
                                    <form method="POST" action="{{ route('workflows.fields.destroy', ['template' => $field['template_id'], 'fieldKey' => $field['key']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">ยืนยันลบฟิลด์</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    @endif
                </article>
            @empty
                <div class="empty-state">No dynamic fields configured.</div>
            @endforelse
        </div>
    </section>
    @if($canManageSystem && $templates->isNotEmpty())
        <script>
            document.querySelector('[data-dynamic-field-template]')?.addEventListener('change', (event) => {
                const option = event.target.selectedOptions[0];
                const form = event.target.closest('[data-dynamic-field-create-form]');
                if (option?.dataset.storeUrl && form) form.action = option.dataset.storeUrl;
            });
        </script>
    @endif
@elseif($activeView === 'user_list' && $canManage)
    <section class="panel smartflow-user-list-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">User List</p>
                <h2>SmartFlow users on WDC</h2>
                <p>รายชื่อผู้ใช้ที่เข้าใช้งาน WDC แทน SmartFlow พร้อม role, ขอบเขตข้อมูล และกลุ่มสิทธิ์ที่ใช้งานจริง</p>
            </div>
            <span class="status-pill">{{ $smartflowUsersData->count() }} users</span>
        </div>

        <div class="smartflow-user-grid">
            @forelse($smartflowUsersData as $smartflowUser)
                <article class="smartflow-user-card">
                    <div class="smartflow-user-head">
                        <span class="smartflow-avatar">{{ $smartflowUser['initial'] }}</span>
                        <div>
                            <h3>{{ $smartflowUser['name'] }}</h3>
                            <small>{{ $smartflowUser['email'] ?? '-' }}</small>
                        </div>
                    </div>
                    <div class="smartflow-user-facts">
                        <span><strong>Employee</strong>{{ $smartflowUser['employee_code'] ?? '-' }}</span>
                        <span><strong>Role</strong>{{ $smartflowUser['role'] }}</span>
                        <span><strong>Scope</strong>{{ $smartflowUser['data_scope'] }}</span>
                        <span><strong>Department</strong>{{ $smartflowUser['department'] }}</span>
                    </div>
                    <div class="smartflow-user-groups">
                        @forelse($smartflowUser['groups']->take(8) as $group)
                            <span>{{ $group }}</span>
                        @empty
                            <span>No permission group</span>
                        @endforelse
                    </div>
                    <div class="smartflow-user-footer">
                        <span>{{ $smartflowUser['permission_count'] }} permissions</span>
                        <span>{{ $smartflowUser['override_count'] }} overrides</span>
                    </div>
                </article>
            @empty
                <div class="empty-state">No active SmartFlow users in WDC.</div>
            @endforelse
        </div>
    </section>
@elseif($activeView === 'permission_map' && $canManage)
    <section class="panel smartflow-permission-map-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">Permission Map</p>
                <h2>Role and permission matrix</h2>
                <p>แผนที่สิทธิ์แบบ SmartFlow เดิม โดยใช้ role และ permission จริงของ WDC เพื่อดูว่าแต่ละกลุ่มเข้าถึงเมนูใดได้บ้าง</p>
            </div>
            <span class="status-pill">{{ $permissionMapData['roles']->count() }} roles</span>
        </div>

        <div class="smartflow-permission-map">
            @foreach($permissionMapData['permissionGroups'] as $groupName => $permissions)
                <article class="smartflow-permission-group">
                    <div class="smartflow-permission-group-head">
                        <h3>{{ $groupName }}</h3>
                        <span>{{ $permissions->count() }} permissions</span>
                    </div>
                    <div class="smartflow-permission-rows">
                        @foreach($permissions as $permission)
                            <div class="smartflow-permission-row">
                                <div>
                                    <strong>{{ $permission->name }}</strong>
                                    <small>{{ $permission->key }}</small>
                                </div>
                                <div class="smartflow-role-chip-list">
                                    @foreach($permissionMapData['roles'] as $role)
                                        @php($roleHasPermission = $role->isSuperAdmin() || $role->permissions->contains('key', $permission->key))
                                        @if($roleHasPermission)
                                            <span>{{ $role->name }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@elseif($activeView === 'user_group_diagram' && $canManage)
    <section class="panel smartflow-group-diagram-panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">User Group Diagram</p>
                <h2>How SmartFlow groups work in WDC</h2>
                <p>Reference view for business unit groups, manager groups, approver groups, and delegation logic using the live WDC role and permission data.</p>
            </div>
            <span class="status-pill">{{ $userGroupDiagramData['summary']['groups'] ?? 0 }} groups</span>
        </div>

        <div class="smartflow-group-summary">
            <div><span>Roles</span><strong>{{ number_format($userGroupDiagramData['summary']['roles'] ?? 0) }}</strong></div>
            <div><span>Permission Groups</span><strong>{{ number_format($userGroupDiagramData['summary']['groups'] ?? 0) }}</strong></div>
            <div><span>Active Users</span><strong>{{ number_format($userGroupDiagramData['summary']['users'] ?? 0) }}</strong></div>
            <div><span>Possible Approvers</span><strong>{{ number_format($userGroupDiagramData['summary']['approvers'] ?? 0) }}</strong></div>
        </div>
    </section>

    <section class="smartflow-group-concepts">
        @foreach($userGroupDiagramData['concepts'] as $concept)
            <article class="smartflow-group-concept">
                <span class="smartflow-group-dot"></span>
                <div>
                    <h3>{{ $concept['title'] }}</h3>
                    <p>{{ $concept['body'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <div class="smartflow-group-layout">
        <section class="panel smartflow-group-panel">
            <div class="section-title">
                <h2>Permission groups</h2>
                <span class="status-pill">Role to access map</span>
            </div>
            <div class="smartflow-group-node-list">
                @foreach($userGroupDiagramData['permissionGroups'] as $group)
                    <article class="smartflow-group-node">
                        <div class="smartflow-group-node-head">
                            <div>
                                <h3>{{ $group['name'] }}</h3>
                                <small>{{ $group['permission_count'] }} permissions · {{ $group['user_count'] }} users</small>
                            </div>
                            <span>{{ $group['roles']->count() }} roles</span>
                        </div>
                        <div class="smartflow-role-chip-list">
                            @foreach($group['roles']->take(8) as $roleName)
                                <span>{{ $roleName }}</span>
                            @endforeach
                        </div>
                        <div class="smartflow-group-permissions">
                            @foreach($group['permissions']->take(5) as $permissionName)
                                <span>{{ $permissionName }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel smartflow-group-panel">
            <div class="section-title">
                <h2>Possible approvers</h2>
                <span class="status-pill">{{ $userGroupDiagramData['approverUsers']->count() }} shown</span>
            </div>
            <div class="smartflow-approver-list">
                @forelse($userGroupDiagramData['approverUsers'] as $approver)
                    <div class="smartflow-approver-row">
                        <span class="smartflow-avatar">{{ mb_substr($approver['name'] ?: 'U', 0, 1) }}</span>
                        <div>
                            <strong>{{ $approver['name'] }}</strong>
                            <small>{{ $approver['employee_code'] ?? '-' }} · {{ $approver['role'] }} · {{ $approver['department'] }}</small>
                        </div>
                        <span class="status-pill">{{ $approver['scope'] }}</span>
                    </div>
                @empty
                    <div class="empty-state">No approver users found.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel smartflow-group-panel">
        <div class="section-title">
            <h2>Workflow approver groups</h2>
            <span class="status-pill">{{ $userGroupDiagramData['workflowGroups']->count() }} workflows</span>
        </div>
        <div class="smartflow-workflow-group-grid">
            @foreach($userGroupDiagramData['workflowGroups'] as $workflow)
                <article class="smartflow-workflow-group-card">
                    <div>
                        <h3>{{ $workflow['workflow'] }}</h3>
                        <small>Workflow #{{ $workflow['legacy_workflow_id'] ?? '-' }} · {{ $workflow['step_count'] }} steps</small>
                    </div>
                    <div class="smartflow-role-chip-list">
                        @forelse($workflow['approver_groups']->take(8) as $approverGroup)
                            <span>{{ $approverGroup }}</span>
                        @empty
                            <span>No approver group</span>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@else

@if(in_array($activeView, ['all', 'tasks', 'favorites'], true))
    <section class="smartflow-summary-strip" aria-label="สรุปสถานะเอกสาร">
        <div><span>รอรับเรื่อง</span><strong>{{ $metrics['submitted'] }}</strong></div>
        <div><span>กำลังดำเนินการ</span><strong>{{ $metrics['in_review'] }}</strong></div>
        <div><span>เสร็จสิ้น</span><strong>{{ $metrics['completed'] }}</strong></div>
        <div class="{{ $metrics['overdue'] > 0 ? 'warning' : '' }}"><span>เกิน SLA</span><strong>{{ $metrics['overdue'] }}</strong></div>
    </section>
@endif

@if(session('import_errors'))
    <section class="panel alert-panel">
        <div class="section-title">
            <h2>รายการที่ import ไม่สำเร็จ</h2>
            <span class="status-pill">{{ count(session('import_errors', [])) }} rows</span>
        </div>
        <div class="item-list">
            @foreach(array_slice(session('import_errors', []), 0, 8) as $error)
                <div class="result-row"><i class="bi bi-exclamation-triangle"></i><span>{{ $error }}</span></div>
            @endforeach
        </div>
    </section>
@endif

@if($canManage && $activeView === 'workflows')
    <details class="panel smartflow-workspace-panel smartflow-import-panel">
        <summary class="smartflow-panel-summary">
            <span class="smartflow-panel-icon"><i class="bi bi-cloud-arrow-up"></i></span>
            <span>
                <strong>SmartFlow Import</strong>
                <small>Import CSV from the old SmartFlow only when needed.</small>
            </span>
            <i class="bi bi-chevron-down smartflow-panel-chevron"></i>
        </summary>
        <div class="smartflow-panel-body">
        <div class="section-title">
            <h2>นำเข้าข้อมูลจาก SmartFlow เดิม</h2>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('workflows.import-template') }}"><i class="bi bi-download"></i> ดาวน์โหลด CSV Template</a>
        </div>
        <form method="post" action="{{ route('workflows.import') }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            <label class="span-2">
                <span>ไฟล์ CSV Export จาก SmartFlow</span>
                <input class="form-control" name="smartflow_csv" type="file" accept=".csv,text/csv,text/plain" required>
            </label>
            <div class="import-column-list">
                <strong>หัวคอลัมน์ที่รองรับ</strong>
                <span>{{ collect($importHeaders)->join(', ') }}</span>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Import เข้า WDC</button>
        </form>
        </div>
    </details>
@endif

@if($createPanelOpen)
    <details class="panel smartflow-workspace-panel smartflow-create-panel" id="workflow-create-form" @if($createPanelOpen) open @endif>
        <summary class="smartflow-panel-summary">
            <span class="smartflow-panel-icon"><i class="bi bi-file-earmark-plus"></i></span>
            <span>
                <strong>New Document</strong>
                <small>Create a request with the same workflow fields as SmartFlow.</small>
            </span>
            <span class="status-pill">WDC-SF Auto No.</span>
            <i class="bi bi-chevron-down smartflow-panel-chevron"></i>
        </summary>
        <div class="smartflow-panel-body">
        <div class="section-title">
            <h2>สร้างเอกสารใหม่</h2>
            <span class="status-pill">WDC จะออกเลข WDC-SF ให้อัตโนมัติ</span>
        </div>
        <form method="post" action="{{ route('workflows.store') }}" class="form-grid" enctype="multipart/form-data">
            @csrf
            @php($selectedTemplateId = (int) old('workflow_template_id', $activeTemplateId ?: $templateCatalog->first()?->id))
            <label>
                <span>Workflow</span>
                <select class="form-select" name="workflow_template_id" data-smartflow-template-select required>
                    @foreach($templateCatalog as $template)
                        <option value="{{ $template->id }}" @selected($selectedTemplateId === $template->id)>
                            {{ $template->name }} · {{ $template->service_team ?? $template->category }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="span-2">
                <span>หัวข้อเอกสาร</span>
                <input class="form-control" name="title" value="{{ old('title') }}" required>
            </label>
            <label>
                <span>ความเร่งด่วน</span>
                <select class="form-select" name="priority">
                    <option value="low" @selected(old('priority') === 'low')>ต่ำ</option>
                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>ปกติ</option>
                    <option value="high" @selected(old('priority') === 'high')>สูง</option>
                    <option value="critical" @selected(old('priority') === 'critical')>วิกฤต</option>
                </select>
            </label>
            <label>
                <span>เลขอ้างอิง SmartFlow เดิม</span>
                <input class="form-control" name="legacy_reference" value="{{ old('legacy_reference') }}" placeholder="เช่น REF: #2606815">
            </label>
            <div class="span-3 smartflow-form-fields" data-smartflow-fieldsets>
                @foreach($templateCatalog as $template)
                    @php($isSelectedTemplate = $selectedTemplateId === $template->id)
                    <fieldset class="smartflow-template-fields" data-template-id="{{ $template->id }}" @if(! $isSelectedTemplate) hidden @endif>
                        <legend>{{ $template->name }} · Dynamic Fields</legend>
                        <div class="form-grid compact-form-grid">
                            @forelse($template->schemaFieldDefinitions() as $field)
                                @php($fieldName = "form_payload[{$field['label']}]")
                                @php($oldValue = old("form_payload.{$field['label']}"))
                                <label class="{{ in_array($field['type'], ['textarea', 'rich_text', 'file'], true) ? 'span-3' : '' }}">
                                    <span>
                                        {{ $field['label'] }}
                                        @if($field['required'])
                                            <strong class="required-mark">*</strong>
                                        @endif
                                        <small>{{ strtoupper($field['type']) }}</small>
                                    </span>
                                    @if(in_array($field['type'], ['textarea', 'rich_text'], true))
                                        <textarea class="form-control" name="{{ $fieldName }}" rows="3" @disabled(! $isSelectedTemplate)>{{ $oldValue }}</textarea>
                                    @elseif($field['type'] === 'checkbox')
                                        <select class="form-select" name="{{ $fieldName }}" @disabled(! $isSelectedTemplate)>
                                            <option value="">No</option>
                                            <option value="on" @selected($oldValue === 'on')>Yes</option>
                                        </select>
                                    @elseif($field['type'] === 'select' && ! empty($field['options']))
                                        <select class="form-select" name="{{ $fieldName }}" @disabled(! $isSelectedTemplate)>
                                            <option value="">เลือก</option>
                                            @foreach($field['options'] as $option)
                                                <option value="{{ $option }}" @selected($oldValue === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field['type'] === 'file')
                                        <input class="form-control" name="{{ $fieldName }}" value="{{ $oldValue }}" placeholder="วางลิงก์ไฟล์หรือหมายเหตุไฟล์แนบ" @disabled(! $isSelectedTemplate)>
                                    @else
                                        <input class="form-control" name="{{ $fieldName }}" value="{{ $oldValue }}" type="{{ in_array($field['type'], ['date', 'number', 'tel'], true) ? $field['type'] : 'text' }}" @disabled(! $isSelectedTemplate)>
                                    @endif
                                    @if($field['help'])
                                        <em>{{ $field['help'] }}</em>
                                    @endif
                                </label>
                            @empty
                                <div class="empty-state">SmartFlow เดิมยังไม่มี dynamic field สำหรับ workflow นี้</div>
                            @endforelse
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <label class="span-3">
                <span>รายละเอียด</span>
                <textarea class="form-control" name="details" rows="3" required>{{ old('details') }}</textarea>
            </label>
            <label class="span-3">
                <span>ลิงก์ไฟล์แนบ</span>
                <textarea class="form-control" name="attachment_links" rows="2" placeholder="วางลิงก์ไฟล์จาก SmartFlow, Google Drive หรือรูปภาพ แยกแต่ละลิงก์ด้วยบรรทัดใหม่">{{ old('attachment_links') }}</textarea>
            </label>
            <label class="span-3">
                <span>อัปโหลดไฟล์แนบ</span>
                <input class="form-control" name="workflow_files[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                <em>รองรับรูป, PDF, Word, Excel, CSV, TXT, ZIP สูงสุด 5 ไฟล์ / 10 MB ต่อไฟล์</em>
            </label>
            <div class="workflow-submit-actions span-3">
                <button class="btn btn-outline-primary" type="submit" name="submit_action" value="draft">
                    <i class="bi bi-file-earmark"></i> Save Draft
                </button>
                <button class="btn btn-primary" type="submit" name="submit_action" value="submit">
                    <i class="bi bi-send"></i> Submit Document
                </button>
            </div>
        </form>
        </div>
    </details>
@endif

@if($activeView === 'workflows')
<details class="panel smartflow-workspace-panel smartflow-diagram-panel" id="smartflow-diagrams" open>
    <summary class="smartflow-panel-summary">
        <span class="smartflow-panel-icon"><i class="bi bi-diagram-3"></i></span>
        <span>
            <strong>Workflow Diagrams</strong>
            <small>View approval steps, routing, conditions, and SmartFlow field maps.</small>
        </span>
        <span class="status-pill">{{ $templates->count() }} workflow</span>
        <i class="bi bi-chevron-down smartflow-panel-chevron"></i>
    </summary>
    <div class="smartflow-panel-body">
    <div class="section-title">
        <h2>Workflows จาก SmartFlow</h2>
        <span class="muted">{{ $templates->count() }} workflow</span>
    </div>
    <div class="workflow-template-grid">
        @foreach($templates as $template)
            <article class="workflow-template-card" id="smartflow-workflow-{{ $template->legacy_workflow_id ?? $template->id }}">
                <div class="meta-row">
                    <span class="tag">{{ $template->smartflow_menu ?? 'Workflows' }}</span>
                    <span>Workflow #{{ $template->legacy_workflow_id }}</span>
                </div>
                <h3>{{ $template->name }}</h3>
                <p>{{ $template->description }}</p>
                <div class="workflow-template-meta">
                    <span><i class="bi bi-people"></i> {{ $template->service_team ?? '-' }}</span>
                    <span><i class="bi bi-clock"></i> SLA {{ $template->sla_hours ?? '-' }} ชม.</span>
                    <span><i class="bi bi-signpost"></i> {{ $template->approval_policy }}</span>
                </div>
                @if($template->schemaFieldDefinitions())
                    <div class="workflow-schema">
                        @foreach($template->schemaFieldDefinitions() as $field)
                            <span>
                                {{ $field['label'] }}
                                <small>{{ strtoupper($field['type']) }}{{ $field['required'] ? ' · REQUIRED' : '' }}</small>
                            </span>
                        @endforeach
                    </div>
                @endif
                @if($template->routingRules())
                    <div class="workflow-routing">
                        <strong>Routing จาก SmartFlow</strong>
                        @foreach($template->routingRules() as $rule)
                            <small>{{ $rule['when'] ?? '-' }} → {{ $rule['step'] ?? '-' }}</small>
                        @endforeach
                    </div>
                @endif
                @if($template->statusFlow())
                    <div class="workflow-routing">
                        <strong>Status / Action Flow</strong>
                        @foreach(array_slice($template->statusFlow(), 0, 5) as $statusRule)
                            <small>{{ $statusRule['from'] ?? '-' }} → {{ $statusRule['to'] ?? '-' }} · {{ $statusRule['action'] ?? '-' }}</small>
                        @endforeach
                    </div>
                @endif
                <div class="workflow-steps workflow-visualizer-steps">
                    @foreach($template->steps as $step)
                        @php($stepApprovers = collect($step->smartflowApprovers())->filter())
                        @php($stepConditions = collect($step->smartflowConditions())->filter())
                        <span class="workflow-step-card">
                            <strong>Order {{ $step->step_order }} · {{ $step->name }}</strong>
                            <small class="workflow-step-mode">
                                MODE: {{ strtoupper(str_replace('_', ' ', $step->mode ?: 'any_one')) }}
                                @if($step->requires_input)
                                    · INPUT REQUIRED
                                @endif
                            </small>
                            @if($step->action_label)
                                <small>Action: {{ $step->action_label }}</small>
                            @endif
                            @if($stepApprovers->isNotEmpty())
                                <small>APPROVERS</small>
                                <div class="workflow-step-chip-list">
                                    @foreach($stepApprovers as $approver)
                                        <em>{{ $approver }}</em>
                                    @endforeach
                                </div>
                            @endif
                            @if($stepConditions->isNotEmpty())
                                <small>CONDITION</small>
                                <div class="workflow-step-condition-list">
                                    @foreach($stepConditions as $condition)
                                        <em>{{ $condition }}</em>
                                    @endforeach
                                </div>
                            @endif
                        </span>
                    @endforeach
                </div>
                <form method="post" action="{{ route('workflows.templates.favorite', $template) }}">
                    @csrf
                    <button class="btn btn-sm {{ $favoriteTemplateIds->contains($template->id) ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">
                        <i class="bi {{ $favoriteTemplateIds->contains($template->id) ? 'bi-star-fill' : 'bi-star' }}"></i>
                        {{ $favoriteTemplateIds->contains($template->id) ? 'ปักหมุดแบบคำขอแล้ว' : 'ปักหมุดแบบคำขอ' }}
                    </button>
                </form>
            </article>
        @endforeach
    </div>
</div>
</details>
@endif

@if($canManageSystem && $activeView === 'workflows')
    <details class="panel smartflow-workspace-panel smartflow-backend-panel" id="workflow-backend">
        <summary class="smartflow-panel-summary">
            <span class="smartflow-panel-icon"><i class="bi bi-sliders"></i></span>
            <span>
                <strong>Workflow Backend</strong>
                <small>Super Admin tools for catalog sync and workflow template maintenance.</small>
            </span>
            <span class="status-pill">Super Admin</span>
            <i class="bi bi-chevron-down smartflow-panel-chevron"></i>
        </summary>
        <div class="smartflow-panel-body">
        <div class="section-title">
            <h2>Workflow Backend</h2>
            <div class="button-row">
                <form method="post" action="{{ route('workflows.templates.sync-smartflow') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Sync SmartFlow Catalog</button>
                </form>
                <span class="status-pill">Super Admin</span>
            </div>
        </div>
        <form method="post" action="{{ route('workflows.templates.store') }}" class="form-grid template-admin-form">
            @csrf
            <label>
                <span>Workflow ID เดิม</span>
                <input class="form-control" name="legacy_workflow_id" placeholder="เช่น 15">
            </label>
            <label>
                <span>ชื่อ Workflow</span>
                <input class="form-control" name="name" required>
            </label>
            <label>
                <span>หมวด</span>
                <input class="form-control" name="category" value="SmartFlow Import" required>
            </label>
            <label>
                <span>เมนู</span>
                <select class="form-select" name="smartflow_menu" required>
                    @foreach($menuTabs as $key => $tab)
                        @if($key !== 'export')
                            <option value="{{ $key }}">{{ $tab['label'] }}</option>
                        @endif
                    @endforeach
                </select>
            </label>
            <label>
                <span>ทีมรับผิดชอบ</span>
                <input class="form-control" name="service_team" placeholder="เช่น IT Helpdesk">
            </label>
            <label>
                <span>SLA ชั่วโมง</span>
                <input class="form-control" name="sla_hours" type="number" min="1" max="720" value="48">
            </label>
            <label class="span-3">
                <span>คำอธิบาย</span>
                <textarea class="form-control" name="description" rows="2"></textarea>
            </label>
            <label class="span-2">
                <span>ช่องฟอร์มที่ต้องการ เก็บหนึ่งบรรทัดต่อหนึ่งช่อง</span>
                <textarea class="form-control" name="form_schema_fields" rows="4">Requester
Reference
รายละเอียดเดิม</textarea>
                <small>Format ใหม่: key|label|type|required|help เช่น dynamic_181|แจ้งขอใช้งาน VPN|checkbox|0</small>
            </label>
            <label>
                <span>Step format</span>
                <textarea class="form-control" name="step_lines" rows="4">1|Submit Request|Requester|ส่งคำขอเข้าระบบ|0
2|Manager Review|Manager / Approver|ตรวจสอบรายละเอียด|0
3|Complete Request|Service Owner|ปิดงานหรืออนุมัติขั้นสุดท้าย|1</textarea>
            </label>
            <input type="hidden" name="approval_policy" value="any_one">
            <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i> สร้าง Workflow Template</button>
        </form>

        <div class="template-admin-list">
            @foreach($templateCatalog as $template)
                @php($templateMenuKey = collect($menuTabs)->filter(fn ($tab) => $tab['label'] === $template->smartflow_menu)->keys()->first() ?? 'all')
                <details class="template-admin-card" id="smartflow-workflow-admin-{{ $template->legacy_workflow_id ?? $template->id }}">
                    <summary>
                        <span>{{ $template->name }}</span>
                        <small>{{ $template->category }} · {{ $template->service_team ?? '-' }} · SLA {{ $template->sla_hours ?? '-' }} ชม.</small>
                    </summary>
                    <form method="post" action="{{ route('workflows.templates.update', $template) }}" class="form-grid template-admin-form">
                        @csrf
                        @method('PATCH')
                        <label>
                            <span>Workflow ID เดิม</span>
                            <input class="form-control" name="legacy_workflow_id" value="{{ $template->legacy_workflow_id }}">
                        </label>
                        <label>
                            <span>ชื่อ Workflow</span>
                            <input class="form-control" name="name" value="{{ $template->name }}" required>
                        </label>
                        <label>
                            <span>หมวด</span>
                            <input class="form-control" name="category" value="{{ $template->category }}" required>
                        </label>
                        <label>
                            <span>เมนู</span>
                            <select class="form-select" name="smartflow_menu" required>
                                @foreach($menuTabs as $key => $tab)
                                    @if($key !== 'export')
                                        <option value="{{ $key }}" @selected($templateMenuKey === $key)>{{ $tab['label'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>ทีมรับผิดชอบ</span>
                            <input class="form-control" name="service_team" value="{{ $template->service_team }}">
                        </label>
                        <label>
                            <span>SLA ชั่วโมง</span>
                            <input class="form-control" name="sla_hours" type="number" min="1" max="720" value="{{ $template->sla_hours }}">
                        </label>
                        <label class="span-3">
                            <span>คำอธิบาย</span>
                            <textarea class="form-control" name="description" rows="2">{{ $template->description }}</textarea>
                        </label>
                        <label class="span-2">
                            <span>ช่องฟอร์มที่ต้องการ</span>
                            <textarea class="form-control" name="form_schema_fields" rows="4">{{ collect($template->schemaFieldDefinitions())->map(fn ($field) => ($field['key'] ?? '').'|'.($field['label'] ?? '').'|'.($field['type'] ?? 'text').'|'.(($field['required'] ?? false) ? '1' : '0').'|'.($field['help'] ?? ''))->join("\n") }}</textarea>
                            <small>Format: key|label|type|required|help</small>
                        </label>
                        <label>
                            <span>Step format</span>
                            <textarea class="form-control" name="step_lines" rows="4">{{ $template->steps->map(fn ($step) => $step->step_order.'|'.$step->name.'|'.$step->approver_group.'|'.$step->condition_label.'|'.($step->requires_input ? '1' : '0'))->join("\n") }}</textarea>
                        </label>
                        <label class="span-2">
                            <span>ลิงก์ SmartFlow เดิม</span>
                            <input class="form-control" name="legacy_url" value="{{ $template->legacy_url }}">
                        </label>
                        <label class="form-check small-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($template->is_active)>
                            <span class="form-check-label">เปิดใช้งาน</span>
                        </label>
                        <input type="hidden" name="approval_policy" value="{{ $template->approval_policy ?: 'any_one' }}">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> บันทึก Template</button>
                    </form>
                </details>
            @endforeach
        </div>
        </div>
    </details>
@endif

@if(in_array($activeView, ['all', 'tasks', 'favorites'], true))
<section class="panel workflow-filter-panel">
    <form method="get" action="{{ route('workflows.index') }}" class="smartflow-filter-form">
        <input type="hidden" name="view" value="{{ $activeView }}">
        <label>
            <span>ค้นหาเอกสาร</span>
            <input class="form-control" name="q" value="{{ $activeSearch }}" placeholder="เลขเอกสาร หัวข้อ ผู้ขอ REF">
        </label>
        <label>
            <span>Workflow</span>
            <select class="form-select" name="template">
                <option value="">ทุก Workflow</option>
                @foreach($templateCatalog as $template)
                    <option value="{{ $template->id }}" @selected($activeTemplateId === $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>สถานะ</span>
            <select class="form-select" name="status">
                <option value="">ทุกสถานะ</option>
                @foreach($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected($activeStatus === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> กรอง</button>
        <a class="btn btn-outline-secondary" href="{{ route('workflows.index', ['view' => $activeView]) }}"><i class="bi bi-x-circle"></i> ล้าง</a>
    </form>
    @php($advancedOpen = $activeDateFrom || $activeDateTo || $activeRequesterId || $activeAssigneeId)
    <details class="smartflow-advanced-filters" @if($advancedOpen) open @endif>
        <summary>
            <span>Show Advanced Filters</span>
            <i class="bi bi-chevron-down"></i>
        </summary>
        <form method="get" action="{{ route('workflows.index') }}" class="smartflow-advanced-filter-form">
            <input type="hidden" name="view" value="{{ $activeView }}">
            <input type="hidden" name="q" value="{{ $activeSearch }}">
            <input type="hidden" name="template" value="{{ $activeTemplateId ?: '' }}">
            <input type="hidden" name="status" value="{{ $activeStatus }}">
            <label>
                <span>วันที่เริ่ม</span>
                <input class="form-control" name="date_from" type="date" value="{{ $activeDateFrom }}">
            </label>
            <label>
                <span>วันที่สิ้นสุด</span>
                <input class="form-control" name="date_to" type="date" value="{{ $activeDateTo }}">
            </label>
            @if($canManage)
                <label>
                    <span>ผู้ขอ</span>
                    <select class="form-select" name="requester">
                        <option value="">ทุกคน</option>
                        @foreach($manageableUsers as $manageableUser)
                            <option value="{{ $manageableUser->id }}" @selected($activeRequesterId === $manageableUser->id)>
                                {{ $manageableUser->name }} · {{ $manageableUser->employee_code }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>ผู้รับผิดชอบ</span>
                    <select class="form-select" name="assignee">
                        <option value="">ทุกคน</option>
                        @foreach($manageableUsers as $manageableUser)
                            <option value="{{ $manageableUser->id }}" @selected($activeAssigneeId === $manageableUser->id)>
                                {{ $manageableUser->name }} · {{ $manageableUser->employee_code }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-sliders"></i> ใช้ตัวกรองขั้นสูง</button>
        </form>
    </details>
</section>

<div class="filter-row">
    @php($baseFilterParams = array_filter([
        'view' => $activeView,
        'q' => $activeSearch,
        'template' => $activeTemplateId ?: null,
        'date_from' => $activeDateFrom,
        'date_to' => $activeDateTo,
        'requester' => $activeRequesterId ?: null,
        'assignee' => $activeAssigneeId ?: null,
    ], fn ($value) => $value !== null && $value !== ''))
    <a class="filter-chip {{ $activeStatus === '' ? 'active' : '' }}" href="{{ route('workflows.index', $baseFilterParams) }}">ทั้งหมด</a>
    @foreach($statusLabels as $key => $label)
        <a class="filter-chip {{ $activeStatus === $key ? 'active' : '' }}" href="{{ route('workflows.index', [...$baseFilterParams, 'status' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="item-list">
    @forelse($requests as $requestItem)
        <details class="list-card smartflow-document-card">
            <summary class="smartflow-document-summary">
                <div class="smartflow-document-title">
                    <h3>{{ $requestItem->title }}</h3>
                    <div class="smartflow-document-title-actions">
                        @if($favoriteRequestIds->contains($requestItem->id))
                            <i class="bi bi-star-fill smartflow-favorite-mark" aria-label="รายการโปรด"></i>
                        @endif
                        <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                    </div>
                </div>
                <div class="smartflow-document-facts">
                    <span><strong>REF:</strong> {{ $requestItem->document_number ?? $requestItem->legacy_reference ?? '-' }}</span>
                    <span><strong>Flow:</strong> {{ $requestItem->template->name }}</span>
                    <span><strong>Step:</strong> {{ $requestItem->currentStep?->name ?? $requestItem->statusLabel() }}</span>
                </div>
                <div class="smartflow-document-foot">
                    <span class="smartflow-avatar">{{ mb_substr($requestItem->requester->name, 0, 1) }}</span>
                    <span>By {{ $requestItem->requester->name }}</span>
                    <span>{{ $requestItem->created_at->format('d M · H:i') }}</span>
                    <a class="smartflow-open-document" href="{{ route('workflows.show', ['workflowRequest' => $requestItem, 'from' => $activeView]) }}">
                        เปิดรายละเอียด <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </summary>
            <div class="smartflow-document-body">
            <div class="meta-row">
                <span class="tag">{{ $requestItem->document_number ?? 'รอเลขเอกสาร' }}</span>
                <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
            </div>
            <h3>{{ $requestItem->title }}</h3>
            <p>{{ $requestItem->details }}</p>
            <div class="meta-row">
                <span>{{ $requestItem->template->name }} · {{ $requestItem->smartflow_menu }}</span>
                <span>ผู้ขอ: {{ $requestItem->requester->name }} · {{ $requestItem->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="smartflow-document-grid">
                <div>
                    <span>Document No.</span>
                    <strong>{{ $requestItem->document_number ?? '-' }}</strong>
                </div>
                <div>
                    <span>Workflow</span>
                    <strong>{{ $requestItem->template->name }}</strong>
                </div>
                <div>
                    <span>Current Step</span>
                    <strong>{{ $requestItem->currentStep?->name ?? $requestItem->statusLabel() }}</strong>
                </div>
                <div>
                    <span>Requester</span>
                    <strong>{{ $requestItem->requester->name }}</strong>
                </div>
                <div>
                    <span>Priority</span>
                    <strong>{{ $requestItem->priorityLabel() }}</strong>
                </div>
                <div>
                    <span>Due</span>
                    <strong>{{ $requestItem->due_at?->format('d/m/Y H:i') ?? '-' }}</strong>
                </div>
            </div>
            <div class="workflow-current-step">
                <strong>ขั้นตอนปัจจุบัน</strong>
                <span>{{ $requestItem->currentStep?->name ?? 'ไม่มีขั้นตอนค้าง' }}</span>
                @if($requestItem->assigned_group || $requestItem->assignee)
                    <small>ผู้รับผิดชอบ: {{ $requestItem->assignee?->name ?? $requestItem->assigned_group }}</small>
                @endif
                @if($requestItem->due_at)
                    <small>กำหนด: {{ $requestItem->due_at->format('d/m/Y H:i') }}</small>
                @endif
                @if($requestItem->currentStep?->condition_label)
                    <small>{{ $requestItem->currentStep->condition_label }}</small>
                @endif
            </div>
            @if($requestItem->form_payload)
                <dl class="workflow-payload">
                    @foreach($requestItem->form_payload as $label => $value)
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    @endforeach
                </dl>
            @endif
            @if($requestItem->legacy_reference)
                <div class="legacy-ref"><i class="bi bi-link-45deg"></i> เอกสารเดิม: {{ $requestItem->legacy_reference }}</div>
            @endif
            @if($requestItem->external_url)
                <a class="source-link" href="{{ $requestItem->external_url }}" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> เปิดเอกสาร SmartFlow เดิม
                </a>
            @endif
            @if($requestItem->attachments->isNotEmpty())
                <div class="attachment-list">
                    @foreach($requestItem->attachments as $attachment)
                        <a class="file-chip" href="{{ $attachment->file_path ? route('workflows.attachments.download', $attachment) : $attachment->file_url }}" @if(! $attachment->file_path) target="_blank" rel="noopener" @endif>
                            <i class="bi bi-paperclip"></i> {{ $attachment->file_name }}
                            @if($attachment->file_size)
                                <small>{{ number_format($attachment->file_size / 1024, 1) }} KB</small>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif

            @if($requestItem->status === 'draft' && ($requestItem->requester_id === auth()->id() || $canManage))
                <form class="workflow-draft-actions" method="post" action="{{ route('workflows.drafts.submit', $requestItem) }}">
                    @csrf
                    @method('PATCH')
                    <span><i class="bi bi-file-earmark"></i> Draft is visible here until it is submitted into the workflow.</span>
                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-send"></i> Submit Draft</button>
                </form>
            @endif

            @if($canManage)
                <form class="inline-form" method="post" action="{{ route('workflows.status', $requestItem) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                        @foreach(collect($statusLabels)->except('draft') as $key => $label)
                            <option value="{{ $key }}" @selected($requestItem->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="assigned_to">
                        <option value="">ผู้รับผิดชอบเดิม/ว่าง</option>
                        @foreach($manageableUsers as $manageableUser)
                            <option value="{{ $manageableUser->id }}" @selected($requestItem->assigned_to === $manageableUser->id)>
                                {{ $manageableUser->name }} · {{ $manageableUser->employee?->department?->name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm" name="due_at" type="datetime-local" value="{{ $requestItem->due_at?->format('Y-m-d\TH:i') }}">
                    <input class="form-control form-control-sm" name="comment" placeholder="หมายเหตุ / ผลการอนุมัติ / สิ่งที่ต้องดำเนินการ">
                    <button class="btn btn-sm btn-outline-primary">อัปเดตสถานะ</button>
                </form>
            @endif

            <div class="comments">
                @foreach($requestItem->events->filter(fn ($event) => ! $event->is_internal || $canManage) as $event)
                    <div><strong>{{ $event->user?->name ?? 'ระบบ' }}</strong> {{ $event->action }} {{ $event->to_status ? '→ '.$event->to_status : '' }} {{ $event->comment }}</div>
                @endforeach
            </div>
            <form class="comment-form" method="post" action="{{ route('workflows.comments.store', $requestItem) }}" enctype="multipart/form-data">
                @csrf
                <input class="form-control form-control-sm" name="comment" placeholder="เพิ่มคอมเมนต์หรือคำตอบกลับ" required>
                <input class="form-control form-control-sm" name="attachment_links" placeholder="ลิงก์ไฟล์แนบเพิ่มเติม (ถ้ามี)">
                <input class="form-control form-control-sm" name="workflow_files[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-chat-dots"></i> ส่งคอมเมนต์</button>
            </form>
            </div>
        </details>
    @empty
        <div class="empty-state">ยังไม่มีเอกสารในมุมมองนี้</div>
    @endforelse
</div>

{{ $requests->links() }}
@endif
@endif
@endsection
