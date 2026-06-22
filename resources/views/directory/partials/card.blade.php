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
