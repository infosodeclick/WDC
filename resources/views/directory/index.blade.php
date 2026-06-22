@extends('layouts.app')

@section('title', 'รายชื่อพนักงาน | WDC Portal')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">WDC Information Directory</p>
        <h1>รายชื่อพนักงาน</h1>
        <p>ข้อมูลจาก Notion เดิมถูกนำเข้ามาไว้ใน WDC Portal เพื่อให้ค้นหาพนักงาน กลุ่มอีเมล ทีม และสาขาได้จากที่เดียว</p>
    </div>
</div>

@php($directoryViewOptions = [
    'location' => ['label' => 'By location', 'icon' => 'bi-grid-3x3-gap'],
    'all' => ['label' => 'All team members', 'icon' => 'bi-grid-3x3-gap-fill'],
    'team' => ['label' => 'By team', 'icon' => 'bi-grid-3x3-gap'],
    'table' => ['label' => 'Table view', 'icon' => 'bi-table'],
])

<nav class="directory-view-tabs" aria-label="Directory view">
    @foreach($directoryViewOptions as $viewKey => $option)
        <a
            class="directory-view-tab @if($directoryView === $viewKey) active @endif"
            href="{{ route('directory.index', array_merge(request()->except(['page', 'view']), ['view' => $viewKey])) }}"
            @if($directoryView === $viewKey) aria-current="page" @endif
        >
            <i class="bi {{ $option['icon'] }}"></i>
            <span>{{ $option['label'] }}</span>
        </a>
    @endforeach
</nav>

<section class="panel">
    <details class="pdpa-note">
        <summary class="pdpa-note-toggle" title="ข้อมูลการใช้งาน" aria-label="ข้อมูลการใช้งาน">
            <i class="bi bi-shield-lock"></i>
        </summary>
        <p>ข้อมูลนี้ใช้เพื่อการติดต่อและประสานงานภายในองค์กรเท่านั้น ห้ามเผยแพร่ คัดลอก หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาตจากบริษัท</p>
    </details>
    <form class="directory-filter" method="get" action="{{ route('directory.index') }}">
        <input type="hidden" name="view" value="{{ $directoryView }}">
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

@if($directoryView === 'all')
<div class="directory-grid">
    @forelse($entries as $entry)
        @php($phoneText = collect([$entry->phone, $entry->extension_number ? 'ต่อ '.$entry->extension_number : null])->filter()->join(' · '))
        @php($subtitleText = collect([$entry->thai_name, $entry->nickname ? '('.$entry->nickname.')' : null])->filter()->join(' '))
        <article class="directory-card directory-card-with-photo" data-directory-card>
            <div class="directory-card-top">
                <button class="directory-photo-button" type="button" data-directory-open aria-label="เปิดข้อมูล {{ $entry->display_name }}">
                    @if($entry->image_url)
                        <img class="directory-photo" src="{{ $entry->image_url }}" alt="รูป {{ $entry->display_name }}" loading="lazy" referrerpolicy="no-referrer">
                    @else
                        <span class="directory-avatar">{{ $entry->avatarInitials() }}</span>
                    @endif
                </button>
                <div class="directory-card-title">
                    <div class="meta-row compact-meta">
                        <span class="copy-line compact-copy">
                            <span>{{ $entry->location ?? 'ไม่ระบุสาขา' }}</span>
                            @if($entry->location)
                                <button class="copy-button" type="button" data-copy="{{ $entry->location }}" title="คัดลอกสาขา" aria-label="คัดลอกสาขา"><i class="bi bi-copy"></i></button>
                            @endif
                        </span>
                    </div>
                    <h2 class="directory-name-line">
                        <span>{{ $entry->display_name }}</span>
                        <button class="copy-button" type="button" data-copy="{{ $entry->display_name }}" title="คัดลอกชื่อ" aria-label="คัดลอกชื่อ"><i class="bi bi-copy"></i></button>
                    </h2>
                    @if($subtitleText)
                        <p class="copy-line">
                            <span>{{ $subtitleText }}</span>
                            <button class="copy-button" type="button" data-copy="{{ $subtitleText }}" title="คัดลอกชื่อไทย/ชื่อเล่น" aria-label="คัดลอกชื่อไทยหรือชื่อเล่น"><i class="bi bi-copy"></i></button>
                        </p>
                    @endif
                </div>
            </div>

            <dl class="mini-detail-list">
                <dt>แผนก/BU</dt>
                <dd class="copy-line">
                    <span>{{ $entry->department ?? '-' }}</span>
                    @if($entry->department)
                        <button class="copy-button" type="button" data-copy="{{ $entry->department }}" title="คัดลอกแผนก" aria-label="คัดลอกแผนก"><i class="bi bi-copy"></i></button>
                    @endif
                </dd>
                <dt>ทีม</dt>
                <dd class="copy-line">
                    <span>{{ $entry->team ?? '-' }}</span>
                    @if($entry->team)
                        <button class="copy-button" type="button" data-copy="{{ $entry->team }}" title="คัดลอกทีม" aria-label="คัดลอกทีม"><i class="bi bi-copy"></i></button>
                    @endif
                </dd>
                <dt>ตำแหน่ง</dt>
                <dd class="copy-line">
                    <span>{{ $entry->position ?? '-' }}</span>
                    @if($entry->position)
                        <button class="copy-button" type="button" data-copy="{{ $entry->position }}" title="คัดลอกตำแหน่ง" aria-label="คัดลอกตำแหน่ง"><i class="bi bi-copy"></i></button>
                    @endif
                </dd>
                <dt>อีเมล</dt>
                <dd class="copy-line">
                    @if($entry->email)
                        <a href="mailto:{{ $entry->email }}">{{ $entry->email }}</a>
                        <button class="copy-button" type="button" data-copy="{{ $entry->email }}" title="คัดลอกอีเมล" aria-label="คัดลอกอีเมล"><i class="bi bi-copy"></i></button>
                    @else
                        <span>-</span>
                    @endif
                </dd>
                <dt>โทร</dt>
                <dd class="copy-line">
                    <span>{{ $phoneText ?: '-' }}</span>
                    @if($phoneText)
                        <button class="copy-button" type="button" data-copy="{{ $phoneText }}" title="คัดลอกเบอร์โทร" aria-label="คัดลอกเบอร์โทร"><i class="bi bi-copy"></i></button>
                    @endif
                </dd>
            </dl>

            @if($entry->notes)
                <div class="directory-note copy-line">
                    <span>{{ $entry->notes }}</span>
                    <button class="copy-button" type="button" data-copy="{{ $entry->notes }}" title="คัดลอกหมายเหตุ" aria-label="คัดลอกหมายเหตุ"><i class="bi bi-copy"></i></button>
                </div>
            @endif

            @if($entry->source_url)
                <div class="source-actions">
                    <a class="source-link" href="{{ $entry->source_url }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right"></i> เปิดข้อมูลต้นทาง
                    </a>
                    <button class="copy-button" type="button" data-copy="{{ $entry->source_url }}" title="คัดลอกลิงก์ต้นทาง" aria-label="คัดลอกลิงก์ต้นทาง"><i class="bi bi-copy"></i></button>
                </div>
            @endif

            <div class="directory-modal-source" hidden>
                <div class="directory-modal-profile">
                    <div class="directory-modal-media">
                        @if($entry->image_url)
                            <img src="{{ $entry->image_url }}" alt="รูป {{ $entry->display_name }}" referrerpolicy="no-referrer">
                        @else
                            <span class="directory-modal-avatar">{{ $entry->avatarInitials() }}</span>
                        @endif
                    </div>
                    <div class="directory-modal-summary">
                        <h2 class="directory-name-line">
                            <span>{{ $entry->display_name }}</span>
                            <button class="copy-button" type="button" data-copy="{{ $entry->display_name }}" title="คัดลอกชื่อ" aria-label="คัดลอกชื่อ"><i class="bi bi-copy"></i></button>
                        </h2>
                        @if($subtitleText)
                            <p class="copy-line">
                                <span>{{ $subtitleText }}</span>
                                <button class="copy-button" type="button" data-copy="{{ $subtitleText }}" title="คัดลอกชื่อไทย/ชื่อเล่น" aria-label="คัดลอกชื่อไทยหรือชื่อเล่น"><i class="bi bi-copy"></i></button>
                            </p>
                        @endif
                    </div>
                </div>
                <dl class="detail-list directory-modal-details">
                    <dt>แผนก/BU</dt>
                    <dd class="copy-line">
                        <span>{{ $entry->department ?? '-' }}</span>
                        @if($entry->department)
                            <button class="copy-button" type="button" data-copy="{{ $entry->department }}" title="คัดลอกแผนก" aria-label="คัดลอกแผนก"><i class="bi bi-copy"></i></button>
                        @endif
                    </dd>
                    <dt>ทีม</dt>
                    <dd class="copy-line">
                        <span>{{ $entry->team ?? '-' }}</span>
                        @if($entry->team)
                            <button class="copy-button" type="button" data-copy="{{ $entry->team }}" title="คัดลอกทีม" aria-label="คัดลอกทีม"><i class="bi bi-copy"></i></button>
                        @endif
                    </dd>
                    <dt>ตำแหน่ง</dt>
                    <dd class="copy-line">
                        <span>{{ $entry->position ?? '-' }}</span>
                        @if($entry->position)
                            <button class="copy-button" type="button" data-copy="{{ $entry->position }}" title="คัดลอกตำแหน่ง" aria-label="คัดลอกตำแหน่ง"><i class="bi bi-copy"></i></button>
                        @endif
                    </dd>
                    <dt>สาขา</dt>
                    <dd class="copy-line">
                        <span>{{ $entry->location ?? '-' }}</span>
                        @if($entry->location)
                            <button class="copy-button" type="button" data-copy="{{ $entry->location }}" title="คัดลอกสาขา" aria-label="คัดลอกสาขา"><i class="bi bi-copy"></i></button>
                        @endif
                    </dd>
                    <dt>อีเมล</dt>
                    <dd class="copy-line">
                        @if($entry->email)
                            <a href="mailto:{{ $entry->email }}">{{ $entry->email }}</a>
                            <button class="copy-button" type="button" data-copy="{{ $entry->email }}" title="คัดลอกอีเมล" aria-label="คัดลอกอีเมล"><i class="bi bi-copy"></i></button>
                        @else
                            <span>-</span>
                        @endif
                    </dd>
                    <dt>โทร</dt>
                    <dd class="copy-line">
                        <span>{{ $phoneText ?: '-' }}</span>
                        @if($phoneText)
                            <button class="copy-button" type="button" data-copy="{{ $phoneText }}" title="คัดลอกเบอร์โทร" aria-label="คัดลอกเบอร์โทร"><i class="bi bi-copy"></i></button>
                        @endif
                    </dd>
                    @if($entry->notes)
                        <dt>หมายเหตุ</dt>
                        <dd class="copy-line">
                            <span>{{ $entry->notes }}</span>
                            <button class="copy-button" type="button" data-copy="{{ $entry->notes }}" title="คัดลอกหมายเหตุ" aria-label="คัดลอกหมายเหตุ"><i class="bi bi-copy"></i></button>
                        </dd>
                    @endif
                    @if($entry->source_url)
                        <dt>ข้อมูลต้นทาง</dt>
                        <dd class="copy-line">
                            <a href="{{ $entry->source_url }}" target="_blank" rel="noopener">{{ $entry->source_url }}</a>
                            <button class="copy-button" type="button" data-copy="{{ $entry->source_url }}" title="คัดลอกลิงก์ต้นทาง" aria-label="คัดลอกลิงก์ต้นทาง"><i class="bi bi-copy"></i></button>
                        </dd>
                    @endif
                </dl>
            </div>
        </article>
    @empty
        <div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
    @endforelse
</div>

{{ $entries->links() }}
@elseif(in_array($directoryView, ['location', 'team'], true))
    <div class="directory-groups">
        @forelse($groupedEntries as $groupName => $groupEntries)
            <details class="directory-group" open>
                <summary>
                    <span class="directory-group-caret"><i class="bi bi-caret-right-fill"></i></span>
                    <span class="directory-group-name">{{ $groupName }}</span>
                    <span class="directory-group-count">{{ $groupEntries->count() }}</span>
                </summary>
                <div class="directory-grid directory-group-grid">
                    @foreach($groupEntries as $entry)
                        @include('directory.partials.card', ['entry' => $entry])
                    @endforeach
                </div>
            </details>
        @empty
            <div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div>
        @endforelse
    </div>
@else
    <div class="table-responsive directory-table-wrap">
        <table class="table align-middle directory-table">
            <thead>
                <tr>
                    <th>ชื่อ</th>
                    <th>แผนก/BU</th>
                    <th>ทีม</th>
                    <th>ตำแหน่ง</th>
                    <th>สาขา</th>
                    <th>อีเมล</th>
                    <th>โทร</th>
                </tr>
            </thead>
            <tbody>
                @forelse($viewEntries as $entry)
                    @php($phoneText = collect([$entry->phone, $entry->extension_number ? 'ต่อ '.$entry->extension_number : null])->filter()->join(' · '))
                    @php($subtitleText = collect([$entry->thai_name, $entry->nickname ? '('.$entry->nickname.')' : null])->filter()->join(' '))
                    <tr>
                        <td>
                            <strong>{{ $entry->display_name }}</strong>
                            @if($subtitleText)
                                <small>{{ $subtitleText }}</small>
                            @endif
                        </td>
                        <td>{{ $entry->department ?? '-' }}</td>
                        <td>{{ $entry->team ?? '-' }}</td>
                        <td>{{ $entry->position ?? '-' }}</td>
                        <td>{{ $entry->location ?? '-' }}</td>
                        <td>
                            @if($entry->email)
                                <a href="mailto:{{ $entry->email }}">{{ $entry->email }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $phoneText ?: '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><div class="empty-state">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="directory-modal" data-directory-modal hidden aria-hidden="true">
    <div class="directory-modal-backdrop" data-directory-close></div>
    <section class="directory-modal-panel" role="dialog" aria-modal="true" aria-label="ข้อมูลพนักงาน">
        <button class="directory-modal-close" type="button" data-directory-close title="ปิด" aria-label="ปิด"><i class="bi bi-x-lg"></i></button>
        <div data-directory-modal-content></div>
    </section>
</div>
@endsection
