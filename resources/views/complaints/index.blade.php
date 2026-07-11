@extends('layouts.app')

@section('title', 'ร้องเรียน | WDC Portal')

@section('content')
<?php $complaintStatusLabels = ['submitted' => 'รับเรื่อง', 'reviewing' => 'ตรวจสอบ', 'resolved' => 'แก้ไขแล้ว', 'closed' => 'ปิดเรื่อง']; ?>
<div class="page-heading compact-page-heading">
    <h1>ร้องเรียน</h1>
</div>

@if($canCreate)
    <details class="panel compact-disclosure complaint-create-panel" @if($errors->any()) open @endif>
        <summary>
            <span><i class="bi bi-shield-check"></i><strong>ส่งเรื่องร้องเรียน</strong></span>
            <i class="bi bi-chevron-down"></i>
        </summary>
        <form method="post" action="{{ route('complaints.store') }}" class="form-grid compact-disclosure-body">
            @csrf
            <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="subject" required></label>
            <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="details" rows="3" required></textarea></label>
            <div class="privacy-note span-3">
                <i class="bi bi-incognito"></i>
                <span><strong>ไม่ระบุผู้ส่ง</strong> ระบบส่งเรื่องถึง HR โดยตรง</span>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งเรื่องร้องเรียน</button>
        </form>
    </details>
@endif

<div class="item-list">
    @forelse($complaints as $complaint)
        <article class="list-card compact-content-card complaint-list-card">
            <div class="meta-row">
                <span class="status-pill">{{ $complaintStatusLabels[$complaint->status] ?? $complaint->status }}</span>
                <span>{{ $complaint->created_at->format('d/m/Y') }}</span>
            </div>
            <h3>{{ $complaint->subject }}</h3>
            <details class="inline-content-disclosure">
                <summary>ดูรายละเอียด</summary>
                <p>{{ $complaint->details }}</p>
            </details>
            @if($canReview)
                <form class="inline-form" method="post" action="{{ route('complaints.status', $complaint) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                        @foreach(['submitted' => 'รับเรื่อง', 'reviewing' => 'ตรวจสอบ', 'resolved' => 'แก้ไขแล้ว', 'closed' => 'ปิดเรื่อง'] as $key => $label)
                            <option value="{{ $key }}" @selected($complaint->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">อัปเดต</button>
                </form>
            @endif
        </article>
    @empty
        <div class="empty-state">ยังไม่มีเรื่องร้องเรียน</div>
    @endforelse
</div>

{{ $complaints->links() }}
@endsection
