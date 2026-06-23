@extends('layouts.app')

@section('title', 'แบบฟอร์ม | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Forms</p>
        <h1>แบบฟอร์ม</h1>
        <p>ใบลา ระเบียบบริษัท คู่มือพนักงาน สัญญาจ้าง และหนังสือรับรอง</p>
    </div>
</div>

<div class="document-category-grid">
    @foreach($documentGroups as $group => $groupDocuments)
        <section class="panel document-category-panel">
            <div class="section-title">
                <h2>{{ $group }}</h2>
                <span class="status-pill">{{ $groupDocuments->count() }} ไฟล์</span>
            </div>
            <div class="item-list">
                @foreach($groupDocuments as $document)
                    <a class="document-row" href="{{ route('documents.download', $document) }}">
                        <i class="bi bi-file-earmark-arrow-down"></i>
                        <span>
                            {{ $document->title }}
                            <small>{{ $document->category }} · {{ $document->file_name }}</small>
                        </span>
                        <i class="bi bi-download"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
