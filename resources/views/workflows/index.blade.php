@extends('layouts.app')

@section('title', 'คำขอ/อนุมัติ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">SmartFlow Migration</p>
        <h1>ศูนย์คำขอและอนุมัติ</h1>
        <p>นำประเภทงานจาก SmartFlow เดิมมาเริ่มใช้งานใน WDC Portal พร้อมเก็บเลขอ้างอิงเอกสารเดิมได้</p>
    </div>
    <div class="role-badge">{{ $canManage ? 'เห็นงานรวม' : 'เห็นงานของฉัน' }}</div>
</div>

<div class="metric-grid">
    <div class="metric-card"><span>ส่งคำขอแล้ว</span><strong>{{ $metrics['submitted'] }}</strong><small>รอรับเรื่อง</small></div>
    <div class="metric-card"><span>กำลังตรวจสอบ</span><strong>{{ $metrics['in_review'] }}</strong><small>อยู่ใน workflow</small></div>
    <div class="metric-card"><span>ปิดงานแล้ว</span><strong>{{ $metrics['completed'] }}</strong><small>อนุมัติหรือเสร็จสิ้น</small></div>
</div>

<section class="panel">
    <div class="section-title">
        <h2>ส่งคำขอใหม่</h2>
        <a href="{{ route('systems.index') }}">ดูระบบเดิม</a>
    </div>
    <form method="post" action="{{ route('workflows.store') }}" class="form-grid">
        @csrf
        <label>
            <span>ประเภทงาน</span>
            <select class="form-select" name="workflow_template_id" required>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected((int) old('workflow_template_id') === $template->id)>{{ $template->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="span-2">
            <span>หัวข้อ</span>
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
        <label class="span-2">
            <span>เลขอ้างอิง SmartFlow เดิม</span>
            <input class="form-control" name="legacy_reference" value="{{ old('legacy_reference') }}" placeholder="เช่น REF: #2606815">
        </label>
        <label class="span-3">
            <span>รายละเอียด</span>
            <textarea class="form-control" name="details" rows="3" required>{{ old('details') }}</textarea>
        </label>
        <p class="form-help span-3">เริ่มจากแบบฟอร์มกลางนี้ก่อน หากงานยังต้องใช้ลายเซ็น/ขั้นตอนเดิม ให้ใส่เลขอ้างอิงและเปิดลิงก์ SmartFlow จากการ์ดประเภทงานด้านล่างได้</p>
        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งคำขอ</button>
    </form>
</section>

<section class="panel">
    <div class="section-title">
        <h2>ประเภทงานจาก SmartFlow</h2>
        <span class="muted">{{ $templates->count() }} workflow</span>
    </div>
    <div class="workflow-template-grid">
        @foreach($templates as $template)
            <article class="workflow-template-card">
                <div class="meta-row">
                    <span class="tag">{{ $template->category }}</span>
                    <span>Workflow #{{ $template->legacy_workflow_id }}</span>
                </div>
                <h3>{{ $template->name }}</h3>
                <p>{{ $template->description }}</p>
                <div class="workflow-steps">
                    @foreach($template->steps as $step)
                        <span>{{ $step->step_order }}. {{ $step->name }}</span>
                    @endforeach
                </div>
                @if($template->legacy_url)
                    <a href="{{ $template->legacy_url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> เปิด SmartFlow เดิม</a>
                @endif
            </article>
        @endforeach
    </div>
</section>

<div class="filter-row">
    <a class="filter-chip {{ $activeStatus === '' ? 'active' : '' }}" href="{{ route('workflows.index') }}">ทั้งหมด</a>
    @foreach($statusLabels as $key => $label)
        <a class="filter-chip {{ $activeStatus === $key ? 'active' : '' }}" href="{{ route('workflows.index', ['status' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="item-list">
    @forelse($requests as $requestItem)
        <article class="list-card">
            <div class="meta-row">
                <span class="tag">{{ $requestItem->template->name }}</span>
                <span class="status-pill status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
            </div>
            <h3>{{ $requestItem->title }}</h3>
            <p>{{ $requestItem->details }}</p>
            <div class="meta-row">
                <span>ผู้ส่งคำขอ: {{ $requestItem->requester->name }}</span>
                <span>{{ $requestItem->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="workflow-current-step">
                <strong>ขั้นตอนปัจจุบัน</strong>
                <span>{{ $requestItem->currentStep?->name ?? 'ไม่มีขั้นตอนค้าง' }}</span>
                @if($requestItem->currentStep?->condition_label)
                    <small>{{ $requestItem->currentStep->condition_label }}</small>
                @endif
            </div>
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
                    <input class="form-control form-control-sm" name="comment" placeholder="หมายเหตุการอนุมัติ">
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
        <div class="empty-state">ยังไม่มีคำขอในเงื่อนไขนี้</div>
    @endforelse
</div>

{{ $requests->links() }}
@endsection
