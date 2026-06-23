@extends('layouts.app')

@section('title', 'รายชื่อพนักงาน | WDC Portal')

@section('content')
<section class="panel directory-search-panel">
    <details class="pdpa-note">
        <summary class="pdpa-note-toggle" title="ข้อมูลการใช้งาน" aria-label="ข้อมูลการใช้งาน">
            <i class="bi bi-shield-lock"></i>
        </summary>
        <p>ข้อมูลนี้ใช้เพื่อการติดต่อและประสานงานภายในองค์กรเท่านั้น ห้ามเผยแพร่ คัดลอก หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาตจากบริษัท</p>
    </details>
    <form class="directory-filter" method="get" action="{{ route('directory.index') }}">
        <label class="span-2">
            <span>ค้นหา</span>
            <input class="form-control" name="q" value="{{ $q }}" placeholder="ชื่อไทย อังกฤษ ชื่อเล่น แผนก ทีม อีเมล เบอร์ต่อ">
        </label>
        <label>
            <span>แผนก/BU</span>
            <select class="form-select" name="department">
                <option value="">ทุกแผนก</option>
                @foreach($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>ทีม</span>
            <select class="form-select" name="team">
                <option value="">ทุกทีม</option>
                @foreach($teams as $option)
                    <option value="{{ $option }}" @selected($team === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>สาขา</span>
            <select class="form-select" name="location">
                <option value="">ทุกสาขา</option>
                @foreach($locations as $option)
                    <option value="{{ $option }}" @selected($location === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>ประเภท</span>
            <select class="form-select" name="type">
                <option value="">ทั้งหมด</option>
                @foreach($entryTypes as $key => $label)
                    <option value="{{ $key }}" @selected($entryType === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
    </form>
</section>

<div class="directory-grid">
    @forelse($entries as $entry)
        @include('directory.partials.card', ['entry' => $entry])
    @empty
        <div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
    @endforelse
</div>

{{ $entries->links() }}

<div class="directory-modal" data-directory-modal hidden aria-hidden="true">
    <div class="directory-modal-backdrop" data-directory-close></div>
    <section class="directory-modal-panel" role="dialog" aria-modal="true" aria-label="ข้อมูลพนักงาน">
        <button class="directory-modal-close" type="button" data-directory-close title="ปิด" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
        <div data-directory-modal-content></div>
    </section>
</div>
@endsection
