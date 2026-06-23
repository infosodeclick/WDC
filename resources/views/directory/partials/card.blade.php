@php($employeeCode = $entry->employeeCode())
@php($deskPhone = $entry->extension_number)
@php($modalDisplayName = $entry->nickname ? "{$entry->display_name} ({$entry->nickname})" : $entry->display_name)
@php($isNewHire = $entry->isNewHireThisMonth())

<article class="directory-card directory-card-with-photo {{ $isNewHire ? 'is-new-hire' : '' }}" data-directory-card>
    <button class="directory-photo-button" type="button" data-directory-open aria-label="เปิดข้อมูล {{ $entry->display_name }}">
        @if($entry->image_url)
            <img class="directory-photo" src="{{ $entry->image_url }}" alt="รูป {{ $entry->display_name }}" loading="lazy" referrerpolicy="no-referrer">
        @else
            <span class="directory-avatar">{{ $entry->avatarInitials() }}</span>
        @endif

        @if($isNewHire)
            <span class="new-hire-badge">พนักงานใหม่</span>
        @endif
    </button>

    <div class="directory-card-body">
        <h2>{{ $entry->display_name }}</h2>

        @if($entry->thai_name)
            <p class="directory-card-thai">{{ $entry->thai_name }}</p>
        @endif

        @if($entry->nickname)
            <p class="directory-card-nickname">{{ $entry->nickname }}</p>
        @endif

        <div class="directory-card-tags">
            @if($entry->position)
                <span class="directory-card-position">{{ $entry->position }}</span>
            @endif
            @if($entry->department)
                <span class="directory-card-department">{{ $entry->department }}</span>
            @endif
        </div>

        <button class="directory-card-detail" type="button" data-directory-open>
            ดูรายละเอียด
        </button>
    </div>

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
                @if($isNewHire)
                    <span class="new-hire-badge modal-new-hire-badge">พนักงานใหม่</span>
                @endif
                <h2 class="directory-name-line">
                    <span>{{ $modalDisplayName }}</span>
                    <button class="copy-button" type="button" data-copy="{{ $modalDisplayName }}" title="คัดลอกชื่อ" aria-label="คัดลอกชื่อ"><i class="bi bi-copy"></i></button>
                </h2>
                @if($entry->thai_name)
                    <p class="directory-modal-thai-name">{{ $entry->thai_name }}</p>
                @endif
                <dl class="directory-modal-highlight-list">
                    <dt>ตำแหน่ง</dt>
                    <dd>{{ $entry->position ?? '-' }}</dd>
                    <dt>แผนก/BU</dt>
                    <dd>{{ $entry->department ?? '-' }}</dd>
                </dl>
            </div>
        </div>
        <dl class="detail-list directory-modal-details">
            <dt>รหัสพนักงาน</dt>
            <dd><span>{{ $employeeCode ?? '-' }}</span></dd>
            <dt>ทีม</dt>
            <dd><span>{{ $entry->team ?? '-' }}</span></dd>
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
