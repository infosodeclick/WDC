<div class="item-list">
    @forelse($items as $item)
        @php
            $title = data_get($item, $titleField);
            $subtitle = data_get($item, $subtitleField);
        @endphp
        <div class="result-row">
            <strong>{{ $title }}</strong>
            <small>{{ $subtitle }}</small>
        </div>
    @empty
        <div class="empty-state">ไม่พบข้อมูล</div>
    @endforelse
</div>
