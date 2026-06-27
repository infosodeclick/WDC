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
                    @php($fileUrl = route('announcements.files.show', $file))
                    @php($fileType = strtolower($file->file_type))
                    @php($modalId = 'announcement-attachment-'.$file->id)
                    <button class="attachment-card" type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                        @if($file->isImage())
                            <img src="{{ $fileUrl }}" alt="{{ $file->file_name }}">
                        @else
                            <i class="bi bi-file-earmark-text"></i>
                        @endif
                        <span>{{ $file->file_name }}<small>{{ strtoupper($file->file_type) }} · {{ $file->file_size_kb }} KB</small></span>
                    </button>

                    <div class="modal fade announcement-attachment-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <p class="eyebrow mb-1">ไฟล์แนบประกาศ</p>
                                        <h2 class="modal-title fs-5" id="{{ $modalId }}-label">{{ $file->file_name }}</h2>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                                </div>
                                <div class="modal-body">
                                    @if($file->isImage())
                                        <img class="announcement-attachment-preview-image" src="{{ $fileUrl }}" alt="{{ $file->file_name }}" data-announcement-preview-image data-file-url="{{ $fileUrl }}" data-file-name="{{ $file->file_name }}">
                                    @elseif($fileType === 'pdf')
                                        <iframe class="announcement-attachment-preview-frame" src="{{ $fileUrl }}" title="{{ $file->file_name }}"></iframe>
                                    @else
                                        <div class="announcement-attachment-file-preview">
                                            <i class="bi bi-file-earmark-arrow-down"></i>
                                            <h3>{{ $file->file_name }}</h3>
                                            <p>ไฟล์ {{ strtoupper($file->file_type) }} · {{ $file->file_size_kb }} KB</p>
                                            <a class="btn btn-primary" href="{{ $fileUrl }}"><i class="bi bi-download"></i> ดาวน์โหลดไฟล์</a>
                                        </div>
                                    @endif
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">ปิด</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
