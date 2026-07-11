<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงานภาพรวม WDC</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #221f20; background: #fff; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 13px; }
        main { width: min(1100px, calc(100% - 40px)); margin: 0 auto; padding: 28px 0 40px; }
        header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding-bottom: 18px; border-bottom: 3px solid #fbd647; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 4px; font-size: 26px; }
        h2 { margin-bottom: 10px; font-size: 16px; }
        .muted { color: #666; }
        .print-actions { display: flex; gap: 8px; }
        .print-actions a, .print-actions button { min-height: 38px; padding: 8px 14px; color: #221f20; border: 1px solid #c9b13b; border-radius: 6px; background: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
        .print-actions button { background: #fbd647; }
        .summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 18px 0; }
        .summary article { min-width: 0; padding: 12px; border: 1px solid #ddd8c9; border-radius: 6px; }
        .summary span, .summary small { display: block; color: #666; }
        .summary strong { display: block; margin: 3px 0; font-size: 24px; font-variant-numeric: tabular-nums; }
        .report-section { break-inside: avoid; margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 10px; text-align: left; border: 1px solid #d8d8d8; }
        th { background: #fff8d5; }
        td:last-child, th:last-child { width: 110px; text-align: right; font-variant-numeric: tabular-nums; }
        @media (max-width: 700px) {
            main { width: min(100% - 24px, 1100px); padding-top: 16px; }
            header { flex-direction: column; }
            .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media print {
            @page { size: A4; margin: 12mm; }
            main { width: 100%; padding: 0; }
            .print-actions { display: none; }
            .summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<?php
    $printSections = [
        ['title' => 'IT Helpdesk', 'rows' => $ticketStatusRows],
        ['title' => 'พนักงานตามแผนก', 'rows' => $employeeRows],
        ['title' => 'INVENTORY', 'rows' => $assetRows],
        ['title' => 'Software License', 'rows' => $licenseRows],
        ['title' => 'พนักงานใหม่', 'rows' => $onboardingRows],
        ['title' => 'พนักงานลาออก', 'rows' => $offboardingRows],
    ];
?>
<main>
    <header>
        <div>
            <h1>รายงานภาพรวม WDC</h1>
            <p class="muted">ข้อมูล ณ {{ $generatedAt->format('d/m/Y H:i') }}</p>
        </div>
        <div class="print-actions">
            <a href="{{ route('reports.index') }}">กลับหน้ารายงาน</a>
            <button type="button" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
        </div>
    </header>

    <section class="summary" aria-label="สรุปรายงาน">
        @foreach($summaryCards as $card)
            <article>
                <span>{{ $card['label'] }}</span>
                <strong>{{ number_format($card['value']) }}</strong>
                <small>{{ $card['note'] }}</small>
            </article>
        @endforeach
    </section>

    @foreach($printSections as $section)
        <section class="report-section">
            <h2>{{ $section['title'] }}</h2>
            <table>
                <thead><tr><th>รายการ</th><th>จำนวน</th></tr></thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            <td>{{ is_array($row) ? $row['name'] : $row->name }}</td>
                            <td>{{ number_format(is_array($row) ? $row['count'] : $row->count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach
</main>
</body>
</html>
