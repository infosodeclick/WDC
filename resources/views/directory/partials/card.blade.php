@php($employeeCode = $entry->employeeCode())
@php($deskPhone = $entry->extension_number)
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
                </span>
            </div>
            <h2 class="directory-name-line">
                <span>{{ $entry->display_name }}</span>
                <button class="copy-button" type="button" data-copy="{{ $entry->display_name }}" title="คัดลอกชื่อ" aria-label="คัดลอกชื่อ"><i class="bi bi-copy"></i></button>
            </h2>
            @if($subtitleText)
                <p>{{ $subtitleText }}</p>
            @endif
        </div>
    </div>

    <dl class="mini-detail-list">
        <dt>รหัสพนักงาน</dt>
        <dd><span>{{ $employeeCode ?? '-' }}</span></dd>
        <dt>แผนก/BU</dt>
        <dd><span>{{ $entry->department ?? '-' }}</span></dd>
        <dt>ทีม</dt>
        <dd><span>{{ $entry->team ?? '-' }}</span></dd>
        <dt>ตำแหน่ง</dt>
        <dd><span>{{ $entry->position ?? '-' }}</span></dd>
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
        <dd><span>{{ $entry->phone ?: '-' }}</span></dd>
        <dt>เบอร์โทรโต๊ะ</dt>
        <dd><span>{{ $deskPhone ?: '-' }}</span></dd>
    </dl>

    @if($entry->notes)
        <div class="directory-note">
            <span>{{ $entry->notes }}</span>
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
                    <p>{{ $subtitleText }}</p>
                @endif
            </div>
        </div>
        <dl class="detail-list directory-modal-details">
            <dt>รหัสพนักงาน</dt>
            <dd><span>{{ $employeeCode ?? '-' }}</span></dd>
            <dt>แผนก/BU</dt>
            <dd><span>{{ $entry->department ?? '-' }}</span></dd>
            <dt>ทีม</dt>
            <dd><span>{{ $entry->team ?? '-' }}</span></dd>
            <dt>ตำแหน่ง</dt>
            <dd><span>{{ $entry->position ?? '-' }}</span></dd>
            <dt>สาขา</dt>
            <dd><span>{{ $entry->location ?? '-' }}</span></dd>
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
            <dd><span>{{ $entry->phone ?: '-' }}</span></dd>
            <dt>เบอร์โทรโต๊ะ</dt>
            <dd><span>{{ $deskPhone ?: '-' }}</span></dd>
            @if($entry->notes)
                <dt>หมายเหตุ</dt>
                <dd><span>{{ $entry->notes }}</span></dd>
            @endif
        </dl>
    </div>
</article>
