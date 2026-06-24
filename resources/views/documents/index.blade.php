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
                <div>
                    <p class="eyebrow">เอกสารตามแผนก</p>
                    <h2>{{ $group }}</h2>
                </div>
                <span class="status-pill">{{ $groupDocuments->count() }} ไฟล์</span>
            </div>
            <div class="item-list">
                @foreach($groupDocuments as $document)
                    @php($categoryParts = array_pad(explode('/', $document->category, 2), 2, null))
                    @php($documentDepartment = $categoryParts[0] ?: $group)
                    @php($documentTopic = $categoryParts[1] ?: $document->title)
                    <a class="document-row" href="{{ route('documents.download', $document) }}">
                        <i class="bi bi-file-earmark-arrow-down"></i>
                        <span>
                            <strong class="document-topic">{{ $documentDepartment }} / {{ $documentTopic }}</strong>
                            <small>{{ $document->title }} · {{ $document->file_name }}</small>
                        </span>
                        <i class="bi bi-download"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
