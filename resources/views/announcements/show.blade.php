@extends('layouts.app')

@section('title', $announcement->title.' | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">ประกาศ {{ $announcement->announcement_no ?? '-' }}</p>
        <h1>{{ $announcement->title }}</h1>
        <p>{{ $announcement->category }} · {{ $announcement->published_at?->format('d/m/Y H:i') }}</p>
    </div>
    <a class="btn btn-outline-primary" href="{{ route('announcements.index') }}"><i class="bi bi-arrow-left"></i> กลับหน้าประกาศ</a>
</div>

<section class="panel announcement-detail-panel">
    <div class="meta-row">
        @if($announcement->is_pinned)<span class="tag">ปักหมุด</span>@endif
        @if($announcement->is_urgent)<span class="tag tag-danger">ด่วน</span>@endif
        @if($announcement->popup_enabled)<span class="tag">แสดง Popup</span>@endif
    </div>

    <div class="announcement-body">
        {!! nl2br(e($announcement->body)) !!}
    </div>

    @if($announcement->files->isNotEmpty())
        <div class="announcement-attachments">
            <h2>ไฟล์แนบ</h2>
            <div class="attachment-grid">
                @foreach($announcement->files as $file)
                    <a class="attachment-card" href="{{ route('announcements.files.show', $file) }}" target="_blank" rel="noopener">
                        @if($file->isImage())
                            <img src="{{ route('announcements.files.show', $file) }}" alt="{{ $file->file_name }}">
                        @else
                            <i class="bi bi-file-earmark-text"></i>
                        @endif
                        <span>{{ $file->file_name }}<small>{{ strtoupper($file->file_type) }} · {{ $file->file_size_kb }} KB</small></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
