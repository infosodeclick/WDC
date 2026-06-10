@extends('layouts.app')

@section('title', 'SmartFlow Work Center | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">SmartFlow Work Center</p>
        <h1>ศูนย์เอกสารและอนุมัติ WDC</h1>
        <p>ใช้งานแทน SmartFlow เดิมสำหรับเอกสาร คำขอ งานรออนุมัติ งาน IT และการติดตามสถานะในเว็บเดียว</p>
    </div>
    <div class="role-badge">{{ $canManage ? 'เห็นงานตามสิทธิ์' : 'เห็นงานของฉัน' }}</div>
</div>

<div class="smartflow-tabs">
    @foreach($menuTabs as $key => $tab)
        @if($key === 'export')
            @if($canManage)
                <a class="smartflow-tab {{ $activeView === $key ? 'active' : '' }}" href="{{ route('workflows.export') }}">
                    <i class="bi {{ $tab['icon'] }}"></i><span>{{ $tab['label'] }}</span>
                </a>
            @endif
        @else
            <a class="smartflow-tab {{ $activeView === $key ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => $key]) }}">
                <i class="bi {{ $tab['icon'] }}"></i><span>{{ $tab['label'] }}</span>
            </a>
        @endif
    @endforeach
</div>

<div class="metric-grid">
    <div class="metric-card"><span>Submitted</span><strong>{{ $metrics['submitted'] }}</strong><small>รอรับเรื่อง</small></div>
    <div class="metric-card"><span>In Workflow</span><strong>{{ $metrics['in_review'] }}</strong><small>ตรวจสอบ/รับเรื่อง/ดำเนินการ</small></div>
    <div class="metric-card"><span>Completed</span><strong>{{ $metrics['completed'] }}</strong><small>อนุมัติหรือปิดงานแล้ว</small></div>
    <div class="metric-card"><span>Overdue</span><strong>{{ $metrics['overdue'] }}</strong><small>เกิน SLA</small></div>
</div>

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

@if($canManage)
    <section class="panel">
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
    </section>
@endif

@if($canCreate)
    <section class="panel">
        <div class="section-title">
            <h2>สร้างเอกสารใหม่</h2>
            <span class="status-pill">WDC จะออกเลข WDC-SF ให้อัตโนมัติ</span>
        </div>
        <form method="post" action="{{ route('workflows.store') }}" class="form-grid">
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
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งเข้า Workflow</button>
        </form>
    </section>
@endif

<section class="panel">
    <div class="section-title">
        <h2>Workflows จาก SmartFlow</h2>
        <span class="muted">{{ $templates->count() }} workflow</span>
    </div>
    <div class="workflow-template-grid">
        @foreach($templates as $template)
            <article class="workflow-template-card">
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
                <div class="workflow-steps">
                    @foreach($template->steps as $step)
                        <span>
                            <strong>{{ $step->step_order }}. {{ $step->name }}</strong>
                            @if($step->action_label)
                                <small>Action: {{ $step->action_label }}</small>
                            @endif
                            @if($step->approver_hint || $step->condition_label)
                                <small>{{ collect([$step->approver_hint, $step->condition_label])->filter()->join(' · ') }}</small>
                            @endif
                            @if($step->requires_input)
                                <small>Input required</small>
                            @endif
                        </span>
                    @endforeach
                </div>
                <form method="post" action="{{ route('workflows.templates.favorite', $template) }}">
                    @csrf
                    <button class="btn btn-sm {{ $favoriteTemplateIds->contains($template->id) ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">
                        <i class="bi {{ $favoriteTemplateIds->contains($template->id) ? 'bi-star-fill' : 'bi-star' }}"></i>
                        {{ $favoriteTemplateIds->contains($template->id) ? 'อยู่ใน Favorites' : 'เพิ่ม Favorites' }}
                    </button>
                </form>
            </article>
        @endforeach
    </div>
</section>

@if($canManageSystem)
    <section class="panel">
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
                <details class="template-admin-card">
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
    </section>
@endif

<div class="filter-row">
    <a class="filter-chip {{ $activeStatus === '' ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => $activeView]) }}">ทั้งหมด</a>
    @foreach($statusLabels as $key => $label)
        <a class="filter-chip {{ $activeStatus === $key ? 'active' : '' }}" href="{{ route('workflows.index', ['view' => $activeView, 'status' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="item-list">
    @forelse($requests as $requestItem)
        <article class="list-card">
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
                        <a class="file-chip" href="{{ $attachment->file_url }}" target="_blank" rel="noopener">
                            <i class="bi bi-paperclip"></i> {{ $attachment->file_name }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if($canManage)
                <form class="inline-form" method="post" action="{{ route('workflows.status', $requestItem) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                        @foreach($statusLabels as $key => $label)
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
                @foreach($requestItem->events as $event)
                    <div><strong>{{ $event->user?->name ?? 'ระบบ' }}</strong> {{ $event->action }} {{ $event->to_status ? '→ '.$event->to_status : '' }} {{ $event->comment }}</div>
                @endforeach
            </div>
            <form class="comment-form" method="post" action="{{ route('workflows.comments.store', $requestItem) }}">
                @csrf
                <input class="form-control form-control-sm" name="comment" placeholder="เพิ่มคอมเมนต์หรือคำตอบกลับ" required>
                <input class="form-control form-control-sm" name="attachment_links" placeholder="ลิงก์ไฟล์แนบเพิ่มเติม (ถ้ามี)">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-chat-dots"></i> ส่งคอมเมนต์</button>
            </form>
        </article>
    @empty
        <div class="empty-state">ยังไม่มีเอกสารในมุมมองนี้</div>
    @endforelse
</div>

{{ $requests->links() }}
@endsection
