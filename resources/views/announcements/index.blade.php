@extends('layouts.app')

@section('title', 'ประกาศ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Announcements</p>
        <h1>ประกาศ</h1>
        <p>นโยบายและประกาศบริษัท</p>
    </div>
</div>

<div class="filter-row">
    <a class="filter-chip {{ $activeCategory === '' ? 'active' : '' }}" href="{{ route('announcements.index') }}">ทั้งหมด</a>
    @foreach($categories as $category)
        <a class="filter-chip {{ $activeCategory === $category ? 'active' : '' }}" href="{{ route('announcements.index', ['category' => $category]) }}">{{ $category }}</a>
    @endforeach
</div>

<div class="item-list">
    @foreach($announcements as $announcement)
        <article class="list-card">
            <div>
                @if($announcement->is_pinned)<span class="tag">ปักหมุด</span>@endif
                @if($announcement->is_urgent)<span class="tag tag-danger">ด่วน</span>@endif
                <span class="tag">{{ $announcement->category }}</span>
            </div>
            <h3><a href="{{ route('announcements.show', $announcement) }}">{{ $announcement->title }}</a></h3>
            <p>{{ $announcement->body }}</p>
            <div class="meta-row">
                <span>{{ $announcement->announcement_no ?? 'ไม่ระบุเลขที่' }}</span>
                <span>{{ $announcement->published_at?->format('d/m/Y') }}</span>
            </div>
            @foreach($announcement->files as $file)
                <a class="file-chip" href="{{ route('announcements.files.show', $file) }}"><i class="bi bi-paperclip"></i> {{ $file->file_name }} · {{ $file->file_size_kb }} KB</a>
            @endforeach
            <a class="btn btn-sm btn-outline-primary" href="{{ route('announcements.show', $announcement) }}">ดูประกาศ</a>
        </article>
    @endforeach
</div>

{{ $announcements->links() }}
@endsection
