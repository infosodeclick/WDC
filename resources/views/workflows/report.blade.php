<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $workflowRequest->document_number ?? 'Workflow Report' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f4f4; color: #221f20; font-family: Arial, "Noto Sans Thai", sans-serif; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; max-width: 920px; margin: 20px auto 0; }
        .toolbar a, .toolbar button { padding: 9px 14px; border: 1px solid #c8a414; border-radius: 6px; background: #fff; color: #221f20; cursor: pointer; font-weight: 700; text-decoration: none; }
        .sheet { max-width: 920px; margin: 12px auto 40px; padding: 44px; background: #fff; box-shadow: 0 12px 35px rgba(0,0,0,.08); }
        .header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 22px; border-bottom: 3px solid #fbd647; }
        .brand { font-size: 28px; font-weight: 900; }
        h1 { margin: 18px 0 6px; font-size: 26px; }
        h2 { margin: 28px 0 12px; font-size: 17px; }
        p { line-height: 1.65; }
        .muted { color: #666; }
        .facts, .payload { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; background: #ddd; border: 1px solid #ddd; }
        .facts div, .payload div { padding: 12px; background: #fff; }
        dt { margin-bottom: 4px; color: #666; font-size: 12px; font-weight: 700; }
        dd { margin: 0; font-weight: 700; }
        .event { padding: 12px 0; border-bottom: 1px solid #ddd; }
        .event strong { display: block; }
        .event small { color: #666; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 18mm; box-shadow: none; }
        }
        @media (max-width: 700px) {
            .toolbar { padding: 0 12px; }
            .sheet { margin: 12px; padding: 24px; }
            .facts, .payload { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('workflows.show', $workflowRequest) }}">กลับรายละเอียด</a>
        <button type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
    </div>
    <main class="sheet">
        <header class="header">
            <div><span class="brand">WDC</span><div class="muted">Workflow Report</div></div>
            <div style="text-align:right"><strong>{{ $workflowRequest->document_number ?? '-' }}</strong><div class="muted">{{ $workflowRequest->created_at->format('d/m/Y H:i') }}</div></div>
        </header>

        <h1>{{ $workflowRequest->title }}</h1>
        <p class="muted">{{ $workflowRequest->template->name }} · {{ $workflowRequest->statusLabel() }}</p>

        <dl class="facts">
            <div><dt>ผู้ขอ</dt><dd>{{ $workflowRequest->requester->name }}</dd></div>
            <div><dt>ผู้รับผิดชอบ</dt><dd>{{ $workflowRequest->assignee?->name ?? $workflowRequest->assigned_group ?? '-' }}</dd></div>
            <div><dt>ผู้ติดตาม (CC)</dt><dd>{{ $workflowRequest->watchers->pluck('name')->join(', ') ?: '-' }}</dd></div>
            <div><dt>ขั้นตอนปัจจุบัน</dt><dd>{{ $workflowRequest->currentStep?->name ?? '-' }}</dd></div>
            <div><dt>กำหนดเสร็จ</dt><dd>{{ $workflowRequest->due_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
        </dl>

        <h2>รายละเอียด</h2>
        <p>{{ $workflowRequest->details ?: '-' }}</p>

        @if($workflowRequest->form_payload)
            <h2>ข้อมูลแบบฟอร์ม</h2>
            <dl class="payload">
                @foreach($workflowRequest->form_payload as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value === 'on' ? 'ใช่' : ($value ?: '-') }}</dd></div>
                @endforeach
            </dl>
        @endif

        <h2>ประวัติการดำเนินการ</h2>
        @forelse($events->sortBy('created_at') as $event)
            <div class="event">
                <strong>{{ $event->user?->name ?? 'ระบบ' }} · {{ $event->action }}</strong>
                <div>{{ $event->comment ?: '-' }}</div>
                <small>{{ $event->created_at->format('d/m/Y H:i') }}{{ $event->to_status ? ' · '.$event->to_status : '' }}</small>
            </div>
        @empty
            <p class="muted">ยังไม่มีประวัติ</p>
        @endforelse
    </main>
</body>
</html>
