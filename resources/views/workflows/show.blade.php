@extends('layouts.app')

@section('title', ($workflowRequest->document_number ?? 'รายละเอียดคำขอ').' | WDC Portal')

@section('content')
@php
    $eventLabels = [
        'create' => 'สร้างคำขอ',
        'create_draft' => 'บันทึกฉบับร่าง',
        'submit_draft' => 'ส่งฉบับร่าง',
        'status_change' => 'อัปเดตสถานะ',
        'comment' => 'เพิ่มความเห็น',
        'internal_comment' => 'หมายเหตุภายใน',
        'smartflow_import' => 'นำเข้าจาก SmartFlow',
        'smartflow_import_update' => 'อัปเดตจาก SmartFlow',
    ];
@endphp

<div class="workflow-detail-heading">
    <div>
        <a class="workflow-back-link" href="{{ route('workflows.index', ['view' => request('from', 'all')]) }}">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้ารายการ
        </a>
        <p class="eyebrow">{{ $workflowRequest->template->name }}</p>
        <h1>{{ $workflowRequest->title }}</h1>
        <div class="workflow-detail-heading-meta">
            <span>{{ $workflowRequest->document_number ?? $workflowRequest->legacy_reference ?? 'รอเลขเอกสาร' }}</span>
            <span class="status-pill status-{{ $workflowRequest->status }}">{{ $workflowRequest->statusLabel() }}</span>
            <span>{{ $workflowRequest->created_at->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    <div class="workflow-detail-actions">
        <form method="post" action="{{ route('workflows.requests.favorite', $workflowRequest) }}">
            @csrf
            <button class="btn {{ $isFavorite ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">
                <i class="bi {{ $isFavorite ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
                {{ $isFavorite ? 'อยู่ในรายการโปรด' : 'เพิ่มรายการโปรด' }}
            </button>
        </form>
        <a class="btn btn-outline-secondary" href="{{ route('workflows.report', $workflowRequest) }}" target="_blank" rel="noopener">
            <i class="bi bi-printer" aria-hidden="true"></i> รายงาน/พิมพ์ PDF
        </a>
        @if($workflowRequest->external_url)
            <a class="btn btn-outline-secondary" href="{{ $workflowRequest->external_url }}" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> SmartFlow เดิม
            </a>
        @endif
    </div>
</div>

<div class="workflow-detail-layout">
    <div class="workflow-detail-main">
        <section class="panel workflow-detail-section">
            <div class="section-title compact-section-title">
                <div>
                    <p class="eyebrow">รายละเอียดคำขอ</p>
                    <h2>ข้อมูลเอกสาร</h2>
                </div>
            </div>
            <p class="workflow-detail-description">{{ $workflowRequest->details ?: 'ไม่มีรายละเอียดเพิ่มเติม' }}</p>

            @if($workflowRequest->form_payload)
                <dl class="workflow-detail-payload">
                    @foreach($workflowRequest->form_payload as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value === 'on' ? 'ใช่' : ($value ?: '-') }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        @if($workflowRequest->attachments->isNotEmpty())
            <section class="panel workflow-detail-section">
                <div class="section-title compact-section-title">
                    <h2>ไฟล์แนบ</h2>
                    <span class="status-pill">{{ $workflowRequest->attachments->count() }} ไฟล์</span>
                </div>
                <div class="workflow-attachment-grid">
                    @foreach($workflowRequest->attachments as $attachment)
                        <a class="workflow-attachment-card" href="{{ $attachment->file_path ? route('workflows.attachments.download', $attachment) : $attachment->file_url }}" @if(! $attachment->file_path) target="_blank" rel="noopener" @endif>
                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                            <span>
                                <strong>{{ $attachment->file_name }}</strong>
                                <small>
                                    {{ $attachment->mime_type ?: 'ไฟล์แนบ' }}
                                    @if($attachment->file_size)
                                        · {{ number_format($attachment->file_size / 1024, 1) }} KB
                                    @endif
                                </small>
                            </span>
                            <i class="bi bi-download" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="panel workflow-detail-section">
            <div class="section-title compact-section-title">
                <div>
                    <p class="eyebrow">Approval History</p>
                    <h2>ประวัติการดำเนินการ</h2>
                </div>
                <span class="status-pill">{{ $events->count() }} รายการ</span>
            </div>
            <ol class="workflow-event-timeline">
                @forelse($events->sortByDesc('created_at') as $event)
                    <li class="{{ $event->is_internal ? 'internal' : '' }}">
                        <span class="workflow-event-icon"><i class="bi {{ $event->is_internal ? 'bi-lock' : 'bi-check2' }}" aria-hidden="true"></i></span>
                        <div>
                            <div class="workflow-event-title">
                                <strong>{{ $eventLabels[$event->action] ?? $event->action }}</strong>
                                @if($event->is_internal)<span class="status-pill">เฉพาะผู้อนุมัติ</span>@endif
                            </div>
                            <p>{{ $event->comment ?: 'ไม่มีหมายเหตุ' }}</p>
                            <small>
                                {{ $event->user?->name ?? 'ระบบ' }} · {{ $event->created_at->format('d/m/Y H:i') }}
                                @if($event->to_status)
                                    · {{ $statusLabels[$event->to_status] ?? $event->to_status }}
                                @endif
                            </small>
                        </div>
                    </li>
                @empty
                    <li class="empty-state">ยังไม่มีประวัติการดำเนินการ</li>
                @endforelse
            </ol>
        </section>

        <section class="panel workflow-detail-section">
            <div class="section-title compact-section-title">
                <h2>ตอบกลับคำขอ</h2>
            </div>
            <form class="workflow-comment-form" method="post" action="{{ route('workflows.comments.store', $workflowRequest) }}" enctype="multipart/form-data">
                @csrf
                <label class="span-2">
                    <span>ความเห็นหรือข้อมูลเพิ่มเติม</span>
                    <textarea class="form-control" name="comment" rows="3" placeholder="พิมพ์ข้อความที่ต้องการแจ้งผู้เกี่ยวข้อง" required></textarea>
                </label>
                <label>
                    <span>ลิงก์ไฟล์แนบ</span>
                    <input class="form-control" name="attachment_links" placeholder="https://...">
                </label>
                <label>
                    <span>อัปโหลดไฟล์</span>
                    <input class="form-control" name="workflow_files[]" type="file" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                </label>
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send" aria-hidden="true"></i> ส่งความเห็น</button>
            </form>
        </section>
    </div>

    <aside class="workflow-detail-sidebar">
        <section class="panel workflow-status-card">
            <p class="eyebrow">สถานะปัจจุบัน</p>
            <h2>{{ $workflowRequest->currentStep?->name ?? $workflowRequest->statusLabel() }}</h2>
            <dl>
                <div><dt>Workflow</dt><dd>{{ $workflowRequest->template->name }}</dd></div>
                <div><dt>ผู้ขอ</dt><dd>{{ $workflowRequest->requester->name }}</dd></div>
                <div><dt>ผู้รับผิดชอบ</dt><dd>{{ $workflowRequest->assignee?->name ?? $workflowRequest->assigned_group ?? '-' }}</dd></div>
                <div><dt>ความเร่งด่วน</dt><dd>{{ $workflowRequest->priorityLabel() }}</dd></div>
                <div><dt>กำหนดเสร็จ</dt><dd>{{ $workflowRequest->due_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            </dl>
        </section>

        @if($workflowRequest->status === 'draft' && ($workflowRequest->requester_id === auth()->id() || $canManage))
            <section class="panel workflow-action-card">
                <h2>ฉบับร่าง</h2>
                <p>ตรวจข้อมูลให้ครบก่อนส่งเข้าสายอนุมัติ</p>
                <form method="post" action="{{ route('workflows.drafts.submit', $workflowRequest) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-send" aria-hidden="true"></i> ส่งคำขอ</button>
                </form>
            </section>
        @endif

        @if($canAct && $workflowRequest->status !== 'draft')
            <section class="panel workflow-action-card">
                <div class="section-title compact-section-title">
                    <div>
                        <p class="eyebrow">Approve Review</p>
                        <h2>ดำเนินการคำขอ</h2>
                    </div>
                </div>
                <form method="post" action="{{ route('workflows.status', $workflowRequest) }}" class="workflow-action-form">
                    @csrf
                    @method('PATCH')
                    <label>
                        <span>ผลดำเนินการ</span>
                        <select class="form-select" name="status" required>
                            @foreach(collect($statusLabels)->except('draft') as $key => $label)
                                <option value="{{ $key }}" @selected($workflowRequest->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($canManage)
                        <label>
                            <span>ผู้รับผิดชอบ</span>
                            <select class="form-select" name="assigned_to">
                                <option value="">ใช้ผู้รับผิดชอบเดิม</option>
                                @foreach($manageableUsers as $manageableUser)
                                    <option value="{{ $manageableUser->id }}" @selected($workflowRequest->assigned_to === $manageableUser->id)>
                                        {{ $manageableUser->name }} · {{ $manageableUser->employee?->department?->name ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label>
                        <span>กำหนดเสร็จ</span>
                        <input class="form-control" name="due_at" type="datetime-local" value="{{ $workflowRequest->due_at?->format('Y-m-d\TH:i') }}">
                    </label>
                    <label>
                        <span>ความเห็นถึงผู้ขอ</span>
                        <textarea class="form-control" name="comment" rows="3" placeholder="ผู้ขอจะเห็นข้อความนี้"></textarea>
                    </label>
                    <label>
                        <span>หมายเหตุภายใน</span>
                        <textarea class="form-control" name="internal_comment" rows="3" placeholder="เห็นเฉพาะผู้อนุมัติและทีมที่ดูแลงาน"></textarea>
                    </label>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> บันทึกผลดำเนินการ</button>
                </form>
            </section>
        @endif
    </aside>
</div>
@endsection
