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

@if($canCreate)
    <section class="panel">
        <div class="section-title">
            <h2>สร้างเอกสารใหม่</h2>
            <span class="status-pill">WDC จะออกเลข WDC-SF ให้อัตโนมัติ</span>
        </div>
        <form method="post" action="{{ route('workflows.store') }}" class="form-grid">
            @csrf
            <label>
                <span>Workflow</span>
                <select class="form-select" name="workflow_template_id" required>
                    @foreach($templateCatalog as $template)
                        <option value="{{ $template->id }}" @selected((int) old('workflow_template_id') === $template->id)>
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
            <label>
                <span>ลูกค้า/แผนก/หน่วยงาน</span>
                <input class="form-control" name="form_payload[ลูกค้า/แผนก/หน่วยงาน]" value="{{ old('form_payload.ลูกค้า/แผนก/หน่วยงาน') }}">
            </label>
            <label>
                <span>เลขเอกสาร/PO/ระบบที่เกี่ยวข้อง</span>
                <input class="form-control" name="form_payload[เลขเอกสาร/PO/ระบบที่เกี่ยวข้อง]" value="{{ old('form_payload.เลขเอกสาร/PO/ระบบที่เกี่ยวข้อง') }}">
            </label>
            <label>
                <span>มูลค่า/จำนวน/ผลกระทบ</span>
                <input class="form-control" name="form_payload[มูลค่า/จำนวน/ผลกระทบ]" value="{{ old('form_payload.มูลค่า/จำนวน/ผลกระทบ') }}">
            </label>
            <label>
                <span>วันที่ต้องการ</span>
                <input class="form-control" name="form_payload[วันที่ต้องการ]" value="{{ old('form_payload.วันที่ต้องการ') }}" placeholder="เช่น 20/06/2026">
            </label>
            <label class="span-3">
                <span>รายละเอียด</span>
                <textarea class="form-control" name="details" rows="3" required>{{ old('details') }}</textarea>
            </label>
            <label class="span-3">
                <span>ไฟล์/ลิงก์/หมายเหตุประกอบ</span>
                <input class="form-control" name="form_payload[ไฟล์/ลิงก์/หมายเหตุประกอบ]" value="{{ old('form_payload.ไฟล์/ลิงก์/หมายเหตุประกอบ') }}" placeholder="วางลิงก์ไฟล์ รูป หรือหมายเหตุที่ต้องใช้ตรวจงาน">
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
                @if($template->schemaFields())
                    <div class="workflow-schema">
                        @foreach($template->schemaFields() as $field)
                            <span>{{ $field }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="workflow-steps">
                    @foreach($template->steps as $step)
                        <span>
                            <strong>{{ $step->step_order }}. {{ $step->name }}</strong>
                            @if($step->approver_hint || $step->condition_label)
                                <small>{{ collect([$step->approver_hint, $step->condition_label])->filter()->join(' · ') }}</small>
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

            @if($canManage)
                <form class="inline-form" method="post" action="{{ route('workflows.status', $requestItem) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" @selected($requestItem->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm" name="comment" placeholder="หมายเหตุ / ผลการอนุมัติ / สิ่งที่ต้องดำเนินการ">
                    <button class="btn btn-sm btn-outline-primary">อัปเดตสถานะ</button>
                </form>
            @endif

            <div class="comments">
                @foreach($requestItem->events as $event)
                    <div><strong>{{ $event->user?->name ?? 'ระบบ' }}</strong> {{ $event->action }} {{ $event->to_status ? '→ '.$event->to_status : '' }} {{ $event->comment }}</div>
                @endforeach
            </div>
        </article>
    @empty
        <div class="empty-state">ยังไม่มีเอกสารในมุมมองนี้</div>
    @endforelse
</div>

{{ $requests->links() }}
@endsection
