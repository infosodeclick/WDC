@extends('layouts.app')

@section('title', 'แบบฟอร์ม | WDC Portal')

@section('content')
<div class="button-row mb-3">
    <a class="btn {{ $activeDepartment === '' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('documents.index') }}">
        <i class="bi bi-folder2-open"></i> ทั้งหมด
    </a>
    @foreach($documentDepartments as $department)
        <a class="btn {{ $activeDepartment === $department ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('documents.index', ['department' => $department]) }}">
            <i class="bi bi-folder"></i> {{ $department }}
        </a>
    @endforeach
</div>

<div class="document-category-grid">
    @forelse($documentGroups as $group => $groupDocuments)
        <section class="panel document-category-panel">
            <div class="section-title">
                <h2>{{ $group }}</h2>
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
    @empty
        <section class="panel">
            <div class="empty-state">ยังไม่มีเอกสารในแผนกนี้</div>
        </section>
    @endforelse
</div>
@endsection
