@extends('layouts.app')

@section('title', 'เอกสารดาวน์โหลด | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Documents</p>
        <h1>เอกสารดาวน์โหลด</h1>
        <p>ใบลา ระเบียบบริษัท คู่มือพนักงาน สัญญาจ้าง และหนังสือรับรอง</p>
    </div>
</div>

<div class="item-list">
    @foreach($documents as $document)
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
@endsection
