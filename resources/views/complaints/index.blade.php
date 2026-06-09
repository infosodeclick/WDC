@extends('layouts.app')

@section('title', 'ร้องเรียน / เสนอแนะ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Complaint & Suggestion</p>
        <h1>ร้องเรียน / เสนอแนะ</h1>
        <p>ส่งเรื่องถึง HR หรือผู้บริหาร พร้อมตัวเลือกเปิดเผยชื่อหรือไม่เปิดเผยชื่อ</p>
    </div>
</div>

<section class="panel">
    <h2>ส่งเรื่องใหม่</h2>
    <form method="post" action="{{ route('complaints.store') }}" class="form-grid">
        @csrf
        <label><span>ประเภท</span>
            <select class="form-select" name="type">
                <option>เสนอแนะ</option>
                <option>ร้องเรียน</option>
                <option>แจ้งการทุจริต</option>
                <option>แจ้งปัญหาหัวหน้างาน</option>
            </select>
        </label>
        <label><span>ส่งถึง</span>
            <select class="form-select" name="submitted_to">
                <option value="hr">HR</option>
                <option value="executive">ผู้บริหาร</option>
            </select>
        </label>
        <label class="span-2"><span>หัวข้อ</span><input class="form-control" name="subject" required></label>
        <label class="span-3"><span>รายละเอียด</span><textarea class="form-control" name="details" rows="3" required></textarea></label>
        <label class="form-check span-2">
            <input class="form-check-input" type="checkbox" name="is_anonymous" value="1">
            <span class="form-check-label">ไม่เปิดเผยชื่อ</span>
        </label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> ส่งเรื่อง</button>
    </form>
</section>

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
