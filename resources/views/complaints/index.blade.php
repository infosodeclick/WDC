@extends('layouts.app')

@section('title', 'ร้องเรียน | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Complaint</p>
        <h1>ร้องเรียน</h1>
        <p>ส่งเรื่องถึง HR โดยตรง</p>
    </div>
</div>

@if($canCreate)
    <section class="panel">
        <h2>ส่งเรื่องร้องเรียน</h2>
        <form method="post" action="{{ route('complaints.store') }}" class="form-grid">
            @csrf
            <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="subject" required></label>
            <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="details" rows="3" required></textarea></label>
            <div class="alert-panel span-3">
                <strong>การร้องเรียนนี้จะไม่ระบุผู้ส่ง</strong>
                <p>ระบบจะส่งเรื่องถึง HR โดยตรง และไม่แสดงชื่อผู้ร้องในรายการ</p>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งเรื่องร้องเรียน</button>
        </form>
    </section>
@endif

<div class="item-list">
    @foreach($complaints as $complaint)
        <article class="list-card">
            <div class="meta-row">
                <span class="tag">{{ $complaint->type }}</span>
                <span class="status-pill">{{ $complaint->status }}</span>
            </div>
            <h3>{{ $complaint->subject }}</h3>
            <p>{{ $complaint->details }}</p>
            <div class="meta-row">
                <span>ผู้ร้อง: {{ $complaint->is_anonymous ? 'ไม่เปิดเผยชื่อ' : ($complaint->reporter?->name ?? '-') }}</span>
                <span>{{ $complaint->created_at->format('d/m/Y') }}</span>
            </div>
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
    @endforeach
</div>

{{ $complaints->links() }}
@endsection
