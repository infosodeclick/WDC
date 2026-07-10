@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="reports-page">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">รายงานภาพรวม</h1>
            <p class="text-muted mb-0">HR, IT Helpdesk และ INVENTORY</p>
        </div>
        @if(count($exportLinks) > 0)
            <div class="reports-export-bar">
                @foreach($exportLinks as $link)
                    <a class="btn btn-outline-dark btn-sm" href="{{ $link['url'] }}">
                        <i class="bi bi-download"></i> {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="metric-grid mb-4">
        @foreach($summaryCards as $card)
            <div class="metric-card report-metric-card">
                <span><i class="bi {{ $card['icon'] }} me-1"></i>{{ $card['label'] }}</span>
                <strong>{{ number_format($card['value']) }}</strong>
                <small>{{ $card['note'] }}</small>
            </div>
        @endforeach
    </div>

    <div class="reports-grid mb-4">
        <section class="report-panel">
            <div class="report-panel-head">
                <h2><i class="bi bi-life-preserver"></i> IT Helpdesk ตามสถานะ</h2>
                <span>{{ number_format($ticketStatusRows->sum('count')) }}</span>
            </div>
            <div class="report-row-list">
                @foreach($ticketStatusRows as $row)
                    <div class="report-row">
                        <span>{{ $row['name'] }}</span>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="report-panel">
            <div class="report-panel-head">
                <h2><i class="bi bi-people"></i> พนักงานตามแผนก</h2>
                <span>{{ number_format($employeeRows->sum('count')) }}</span>
            </div>
            <div class="report-row-list">
                @forelse($employeeRows as $row)
                    <div class="report-row">
                        <span>{{ $row->name }}</span>
                        <strong>{{ number_format($row->count) }}</strong>
                    </div>
                @empty
                    <div class="empty-state">ยังไม่มีข้อมูลพนักงาน</div>
                @endforelse
            </div>
        </section>

        <section class="report-panel">
            <div class="report-panel-head">
                <h2><i class="bi bi-box-seam"></i> INVENTORY ตามสถานะ</h2>
                <span>{{ number_format($assetRows->sum('count')) }}</span>
            </div>
            <div class="report-row-list">
                @foreach($assetRows as $row)
                    <div class="report-row">
                        <span>{{ $row['name'] }}</span>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="report-panel">
            <div class="report-panel-head">
                <h2><i class="bi bi-key"></i> Software License</h2>
                <span>{{ number_format($licenseRows->sum('count')) }}</span>
            </div>
            <div class="report-row-list">
                @foreach($licenseRows as $row)
                    <div class="report-row">
                        <span>{{ $row['name'] }}</span>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="report-panel">
            <div class="report-panel-head">
                <h2><i class="bi bi-person-plus"></i> Onboarding</h2>
                <span>{{ number_format($onboardingRows->sum('count')) }}</span>
            </div>
            <div class="report-row-list">
                @foreach($onboardingRows as $row)
                    <div class="report-row">
                        <span>{{ $row['name'] }}</span>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

</div>
@endsection
