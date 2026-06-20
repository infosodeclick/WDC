@extends('layouts.app')

@section('title', 'เทรนนิ่ง | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Training</p>
        <h1>เทรนนิ่ง</h1>
        <p>บทความและวิดีโอสำหรับ ERP บัญชี คลัง ฝ่ายขาย และ IT</p>
    </div>
</div>

<div class="filter-row">
    <a class="filter-chip {{ $activeCategory === '' ? 'active' : '' }}" href="{{ route('knowledge.index') }}">ทั้งหมด</a>
    @foreach($categories as $category)
        <a class="filter-chip {{ $activeCategory === $category ? 'active' : '' }}" href="{{ route('knowledge.index', ['category' => $category]) }}">{{ $category }}</a>
    @endforeach
</div>

<div class="content-grid">
    <section>
        <div class="section-title"><h2>บทความเทรนนิ่ง</h2></div>
        <div class="item-list">
            @forelse($articles as $article)
                <article class="list-card">
                    <span class="tag">{{ $article->category }}</span>
                    <h3>{{ $article->title }}</h3>
                    <p>{{ $article->summary }}</p>
                    <small>{{ $article->body }}</small>
                </article>
            @empty
                <div class="empty-state">ยังไม่มีบทความในหมวดนี้</div>
            @endforelse
        </div>
    </section>

    <section>
        <div class="section-title"><h2>วิดีโอ</h2></div>
        <div class="item-list">
            @forelse($videos as $video)
                <article class="video-card">
                    <div class="video-thumb"><i class="bi bi-play-fill"></i></div>
                    <div>
                        <span class="tag">{{ $video->category }}</span>
                        <h3>{{ $video->title }}</h3>
                        <p>{{ $video->summary }}</p>
                        <a href="{{ $video->video_url }}" target="_blank" rel="noreferrer">เปิดวิดีโอ · {{ $video->duration_minutes }} นาที</a>
                    </div>
                </article>
            @empty
                <div class="empty-state">ยังไม่มีวิดีโอในหมวดนี้</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
