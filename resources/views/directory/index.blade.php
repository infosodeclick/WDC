@extends('layouts.app')

@section('title', 'สมุดโทรศัพท์ | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">WDC Information Directory</p>
        <h1>สมุดโทรศัพท์พนักงาน</h1>
        <p>ข้อมูลจาก Notion เดิมถูกนำเข้ามาไว้ใน WDC Portal เพื่อให้ค้นหาพนักงาน กลุ่มอีเมล ทีม และสาขาได้จากที่เดียว</p>
    </div>
    <div class="role-badge">{{ $totalEntries }} รายการ</div>
</div>

<div class="metric-grid directory-metrics">
    <div class="metric-card">
        <span>ข้อมูลทั้งหมด</span>
        <strong>{{ $totalEntries }}</strong>
        <small>พนักงาน กลุ่มอีเมล และโชว์รูม</small>
    </div>
    <div class="metric-card">
        <span>นำเข้าจาก Notion</span>
        <strong>{{ $importedEntries }}</strong>
        <small>มี source record พร้อม sync ซ้ำได้</small>
    </div>
    <div class="metric-card">
        <span>อัปเดตล่าสุด</span>
        <strong class="metric-date">{{ $lastImportedAt ? \Illuminate\Support\Carbon::parse($lastImportedAt)->format('d/m/Y H:i') : '-' }}</strong>
        <small>เวลาประเทศไทย</small>
    </div>
</div>

<section class="panel">
    <div class="pdpa-note">
        <i class="bi bi-shield-lock"></i>
        <span>ข้อมูลนี้ใช้เพื่อการติดต่อและประสานงานภายในองค์กรเท่านั้น ห้ามเผยแพร่ คัดลอก หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาตจากบริษัท</span>
    </div>
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
        <article class="directory-card directory-card-with-photo">
            <div class="directory-card-top">
                @if($entry->image_url)
                    <img class="directory-photo" src="{{ $entry->image_url }}" alt="รูป {{ $entry->display_name }}" loading="lazy" referrerpolicy="no-referrer">
                @else
                    <div class="directory-avatar">{{ $entry->avatarInitials() }}</div>
                @endif
                <div class="directory-card-title">
                    <div class="meta-row compact-meta">
                        <span class="tag">{{ $entry->entryTypeLabel() }}</span>
                        <span>{{ $entry->location ?? 'ไม่ระบุสาขา' }}</span>
                    </div>
                    <h2>{{ $entry->display_name }}</h2>
                    @if($entry->thai_name || $entry->nickname)
                        <p>{{ collect([$entry->thai_name, $entry->nickname ? '('.$entry->nickname.')' : null])->filter()->join(' ') }}</p>
                    @endif
                </div>
            </div>

            <dl class="mini-detail-list">
                <dt>แผนก/BU</dt><dd>{{ $entry->department ?? '-' }}</dd>
                <dt>ทีม</dt><dd>{{ $entry->team ?? '-' }}</dd>
                <dt>ตำแหน่ง</dt><dd>{{ $entry->position ?? '-' }}</dd>
                <dt>อีเมล</dt>
                <dd>
                    @if($entry->email)
                        <a href="mailto:{{ $entry->email }}">{{ $entry->email }}</a>
                    @else
                        -
                    @endif
                </dd>
                <dt>โทร</dt><dd>{{ collect([$entry->phone, $entry->extension_number ? 'ต่อ '.$entry->extension_number : null])->filter()->join(' · ') ?: '-' }}</dd>
            </dl>

            @if($entry->notes)
                <div class="directory-note">{{ $entry->notes }}</div>
            @endif

            @if($entry->source_url)
                <a class="source-link" href="{{ $entry->source_url }}" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> เปิดข้อมูลต้นทาง
                </a>
            @endif
        </article>
    @empty
        <div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
    @endforelse
</div>

{{ $entries->links() }}
@endsection
