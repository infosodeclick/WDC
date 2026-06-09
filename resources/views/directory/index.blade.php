@extends('layouts.app')

@section('title', 'สมุดโทรศัพท์ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">WDC Information Directory</p>
        <h1>สมุดโทรศัพท์พนักงาน</h1>
        <p>ข้อมูลติดต่อที่นำเข้าจากระบบ Directory เดิม เพื่อให้ค้นหาได้จาก WDC Portal โดยไม่ต้องเปิด Notion แยก</p>
    </div>
    <div class="role-badge">{{ $totalEntries }} รายการ</div>
</div>

<section class="panel">
    <div class="pdpa-note">
        <i class="bi bi-shield-lock"></i>
        <span>ข้อมูลนี้ใช้เพื่อการติดต่อและประสานงานภายในองค์กรเท่านั้น ห้ามนำไปเผยแพร่หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาต</span>
    </div>
    <form class="directory-filter" method="get" action="{{ route('directory.index') }}">
        <label class="span-2">
            <span>ค้นหา</span>
            <input class="form-control" name="q" value="{{ $q }}" placeholder="ชื่อไทย อังกฤษ ชื่อเล่น แผนก อีเมล เบอร์ต่อ">
        </label>
        <label>
            <span>แผนก/ทีม</span>
            <select class="form-select" name="department">
                <option value="">ทุกแผนก</option>
                @foreach($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
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
        <article class="directory-card">
            <div class="meta-row">
                <span class="tag">{{ $entry->entryTypeLabel() }}</span>
                <span>{{ $entry->location ?? 'ไม่ระบุสาขา' }}</span>
            </div>
            <h2>{{ $entry->display_name }}</h2>
            @if($entry->thai_name || $entry->nickname)
                <p>{{ collect([$entry->thai_name, $entry->nickname ? '('.$entry->nickname.')' : null])->filter()->join(' ') }}</p>
            @endif
            <dl class="mini-detail-list">
                <dt>ทีม</dt><dd>{{ $entry->department ?? '-' }}</dd>
                <dt>ตำแหน่ง</dt><dd>{{ $entry->position ?? '-' }}</dd>
                <dt>อีเมล</dt><dd>{{ $entry->email ?? '-' }}</dd>
                <dt>โทร</dt><dd>{{ collect([$entry->phone, $entry->extension_number ? 'ต่อ '.$entry->extension_number : null])->filter()->join(' · ') ?: '-' }}</dd>
            </dl>
            @if($entry->notes)
                <div class="directory-note">{{ $entry->notes }}</div>
            @endif
        </article>
    @empty
        <div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
    @endforelse
</div>

{{ $entries->links() }}
@endsection
