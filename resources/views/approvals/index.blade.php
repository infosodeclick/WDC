@extends('layouts.app')

@section('title', 'งานรอดำเนินการ | WDC Portal')

@section('content')
<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="page-title mb-1">งานรอดำเนินการ</h1>
        <p class="text-muted mb-0">คิวงานของคุณและทีมตามสิทธิ์</p>
    </div>
    <div class="approval-total-card">
        <span>ค้างดำเนินการ</span>
        <strong>{{ number_format($totalPending) }}</strong>
        <small>รายการ</small>
    </div>
</div>

<div class="approval-section-grid">
    @forelse($sections as $section)
        <section class="approval-panel">
            <div class="approval-panel-header">
                <div>
                    <h2><i class="bi {{ $section['icon'] }}"></i>{{ $section['title'] }}</h2>
                    <p>{{ $section['subtitle'] }}</p>
                </div>
                <span>{{ number_format($section['items']->count()) }}</span>
            </div>

            <div class="approval-list">
                @forelse($section['items'] as $item)
                    <a class="approval-item" href="{{ $item['url'] }}">
                        <div class="approval-item-main">
                            <span class="status-pill">{{ $item['type'] }}</span>
                            <strong>{{ $item['title'] }}</strong>
                            <small>{{ $item['meta'] ?: 'ไม่มีรายละเอียดเพิ่มเติม' }}</small>
                        </div>
                        <div class="approval-item-side">
                            <span>{{ $item['code'] ?: '-' }}</span>
                            <small>{{ $item['status'] }}</small>
                            <em>{{ $item['owner'] }}</em>
                        </div>
                    </a>
                @empty
                    <div class="empty-state">ไม่มีรายการค้างในหมวดนี้</div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="empty-state">ยังไม่มีรายการรออนุมัติที่คุณมีสิทธิ์เข้าถึง</div>
    @endforelse
</div>
@endsection
