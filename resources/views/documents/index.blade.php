@extends('layouts.app')

@section('title', 'แบบฟอร์ม | WDC Portal')

@section('content')
@if($canManageDocuments)
    <section class="panel document-admin-panel mb-3">
        <div class="section-title">
            <div>
                <h2>เพิ่มแบบฟอร์ม</h2>
                <p>อัปโหลดเอกสารจริงให้พนักงานดาวน์โหลดจาก WDC ได้ทันที</p>
            </div>
            <span class="status-pill"><i class="bi bi-shield-check"></i> documents.manage</span>
        </div>

        <form class="document-upload-form" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf
            <label>
                <span>แผนก</span>
                <input class="form-control" name="department" list="documentDepartments" value="{{ old('department', $activeDepartment ?: 'HR') }}" maxlength="80" required>
                <datalist id="documentDepartments">
                    @foreach($documentDepartments as $department)
                        <option value="{{ $department }}"></option>
                    @endforeach
                    <option value="HR"></option>
                    <option value="บัญชี"></option>
                    <option value="IT"></option>
                    <option value="Admin"></option>
                </datalist>
            </label>
            <label>
                <span>หัวข้อเอกสาร</span>
                <input class="form-control" name="topic" value="{{ old('topic') }}" placeholder="เช่น ใบลา / เบิกเงินสดย่อย" maxlength="120" required>
            </label>
            <label>
                <span>ชื่อที่แสดง</span>
                <input class="form-control" name="title" value="{{ old('title') }}" placeholder="เว้นว่างเพื่อใช้หัวข้อเอกสาร" maxlength="160">
            </label>
            <label class="span-2">
                <span>รายละเอียดสั้น</span>
                <input class="form-control" name="summary" value="{{ old('summary') }}" placeholder="บอกว่าเอกสารนี้ใช้ทำอะไร" maxlength="500">
            </label>
            <label>
                <span>ไฟล์เอกสาร</span>
                <input class="form-control" name="file" type="file" required>
            </label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> เพิ่มแบบฟอร์ม</button>
        </form>
    </section>
@endif

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
                    <div class="document-row document-row-manage">
                        <a class="document-download-link" href="{{ route('documents.download', $document) }}">
                            <i class="bi bi-file-earmark-arrow-down"></i>
                            <span>
                                <strong class="document-topic">{{ $documentDepartment }} / {{ $documentTopic }}</strong>
                                <small>{{ $document->title }} · {{ $document->file_name }}</small>
                            </span>
                            <i class="bi bi-download"></i>
                        </a>
                        @if($canManageDocuments)
                            <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('ลบแบบฟอร์มนี้ออกจากระบบ WDC?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i> ลบ</button>
                            </form>
                        @endif
                    </div>
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
