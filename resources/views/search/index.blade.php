@extends('layouts.app')

@section('title', 'ค้นหา | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Search</p>
        <h1>ผลการค้นหา</h1>
        <p>{{ $q === '' ? 'พิมพ์คำค้นจากช่องด้านบน' : 'คำค้น: '.$q }}</p>
    </div>
</div>

<div class="content-grid">
    <section class="panel"><h2>พนักงาน</h2>@include('search.partials.simple-list', ['items' => $employees, 'titleField' => 'user.name', 'subtitleField' => 'position'])</section>
    <section class="panel"><h2>รายชื่อพนักงาน</h2>@include('search.partials.simple-list', ['items' => $directoryEntries, 'titleField' => 'display_name', 'subtitleField' => 'department'])</section>
    <section class="panel"><h2>คำขอ/อนุมัติ</h2>@include('search.partials.simple-list', ['items' => $workflowRequests, 'titleField' => 'title', 'subtitleField' => 'template.name'])</section>
    <section class="panel"><h2>ประกาศ</h2>@include('search.partials.simple-list', ['items' => $announcements, 'titleField' => 'title', 'subtitleField' => 'category'])</section>
    <section class="panel"><h2>คู่มือ</h2>@include('search.partials.simple-list', ['items' => $articles, 'titleField' => 'title', 'subtitleField' => 'category'])</section>
    <section class="panel"><h2>วิดีโอ</h2>@include('search.partials.simple-list', ['items' => $videos, 'titleField' => 'title', 'subtitleField' => 'category'])</section>
    @if(auth()->user()?->canAccessAny(['assets.view', 'assets.manage', 'assets.reports']))
        <section class="panel"><h2>ทรัพย์สิน IT</h2>@include('search.partials.simple-list', ['items' => $assets, 'titleField' => 'code', 'subtitleField' => 'name'])</section>
    @endif
</div>
@endsection
