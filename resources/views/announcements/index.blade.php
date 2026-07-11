@extends('layouts.app')

@section('title', 'ประกาศ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <h1>ประกาศ</h1>
    </div>
</div>

<div class="filter-row">
    <a class="filter-chip {{ $activeCategory === '' ? 'active' : '' }}" href="{{ route('announcements.index') }}">ทั้งหมด</a>
    @foreach($categories as $category)
        <a class="filter-chip {{ $activeCategory === $category ? 'active' : '' }}" href="{{ route('announcements.index', ['category' => $category]) }}">{{ $category }}</a>
    @endforeach
</div>

<div class="item-list">
    @forelse($announcements as $announcement)
        <article class="list-card compact-content-card announcement-list-card">
            <div>
                @if($announcement->is_pinned)<span class="tag">ปักหมุด</span>@endif
                @if($announcement->is_urgent)<span class="tag tag-danger">ด่วน</span>@endif
                <span class="tag">{{ $announcement->category }}</span>
            </div>
            <h3><a href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h3>
            <p class="line-clamp-2">{{ $announcement->body }}</p>
            <div class="meta-row">
                <span>{{ $announcement->announcement_no ?? 'ไม่ระบุเลขที่' }}</span>
                <span>{{ $announcement->published_at?->format('d/m/Y') }}</span>
            </div>
            @if($announcement->files->isNotEmpty())
                <details class="inline-file-disclosure">
                    <summary><i class="bi bi-paperclip"></i> ไฟล์แนบ {{ $announcement->files->count() }} ไฟล์</summary>
                    <div>
                        @foreach($announcement->files as $file)
                            <a class="file-chip" href="{{ route('announcements.files.show', $file) }}">{{ $file->file_name }} · {{ $file->file_size_kb }} KB</a>
                        @endforeach
                    </div>
                </details>
            @endif
        </article>
    @empty
        <div class="empty-state">ยังไม่มีประกาศในหมวดนี้</div>
    @endforelse
</div>

{{ $announcements->links() }}
@endsection
