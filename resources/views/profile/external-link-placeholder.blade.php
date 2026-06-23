@extends('layouts.app')

@section('title', $title.' | WDC Portal')

@section('content')
<section class="panel placeholder-link-panel">
    <i class="bi {{ $icon }}"></i>
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
    <a class="btn btn-primary" href="{{ route('profile') }}"><i class="bi bi-arrow-left"></i> กลับโปรไฟล์</a>
</section>
@endsection
