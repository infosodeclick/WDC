@extends('layouts.app')

@section('title', 'รายงาน | WDC Portal')

@section('content')
@php($groupedExportLinks = collect($exportLinks)->groupBy('group'))
<div class="reports-page">
    <header class="report-page-header">
        <h1>รายงาน</h1>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-download"></i> ส่งออกรายงาน
            </button>
            <ul class="dropdown-menu dropdown-menu-end reports-export-menu">
                @foreach($groupedExportLinks as $group => $links)
                    @unless($loop->first)<li><hr class="dropdown-divider"></li>@endunless
                    <li><h2 class="dropdown-header">{{ $group }}</h2></li>
                    @foreach($links as $link)
                        <li>
                            <a class="dropdown-item" href="{{ $link['url'] }}">
                                <i class="bi {{ $link['icon'] }}"></i> {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </header>

    <nav class="metric-grid report-summary-grid" aria-label="สรุปรายงาน">
        @foreach($summaryCards as $card)
            <a class="metric-card report-metric-card" href="{{ $card['target'] }}">
                <span><i class="bi {{ $card['icon'] }}"></i>{{ $card['label'] }}</span>
                <strong>{{ number_format($card['value']) }}</strong>
                <small>{{ $card['note'] }}</small>
            </a>
        @endforeach
    </nav>

    <div class="reports-grid">
        <section class="report-panel" id="report-helpdesk">
            <div class="report-panel-head">
                <h2><i class="bi bi-life-preserver"></i> IT Helpdesk</h2>
                <div>
                    <span>{{ number_format($ticketStatusRows->sum('count')) }}</span>
                    @if($sectionLinks['helpdesk'])
                        <a class="icon-link" href="{{ $sectionLinks['helpdesk'] }}" title="เปิด IT Helpdesk" aria-label="เปิด IT Helpdesk"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @foreach($ticketStatusRows as $row)
                    <div class="report-row"><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                @endforeach
            </div>
        </section>

        <section class="report-panel" id="report-employees">
            <div class="report-panel-head">
                <h2><i class="bi bi-people"></i> พนักงานตามแผนก</h2>
                <div>
                    <span>{{ number_format($employeeRows->sum('count')) }}</span>
                    @if($sectionLinks['employees'])
                        <a class="icon-link" href="{{ $sectionLinks['employees'] }}" title="เปิดรายชื่อพนักงาน" aria-label="เปิดรายชื่อพนักงาน"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @forelse($employeeRows as $row)
                    <div class="report-row"><span>{{ $row->name }}</span><strong>{{ number_format($row->count) }}</strong></div>
                @empty
                    <div class="empty-state">ยังไม่มีข้อมูลพนักงาน</div>
                @endforelse
            </div>
        </section>

        <section class="report-panel" id="report-assets">
            <div class="report-panel-head">
                <h2><i class="bi bi-box-seam"></i> INVENTORY</h2>
                <div>
                    <span>{{ number_format($assetRows->sum('count')) }}</span>
                    @if($sectionLinks['assets'])
                        <a class="icon-link" href="{{ $sectionLinks['assets'] }}" title="เปิด INVENTORY" aria-label="เปิด INVENTORY"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @foreach($assetRows as $row)
                    <div class="report-row"><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                @endforeach
            </div>
        </section>

        <section class="report-panel" id="report-licenses">
            <div class="report-panel-head">
                <h2><i class="bi bi-key"></i> Software License</h2>
                <div>
                    <span>{{ number_format($licenseRows->sum('count')) }}</span>
                    @if($sectionLinks['assets'])
                        <a class="icon-link" href="{{ $sectionLinks['assets'] }}" title="เปิด Software License" aria-label="เปิด Software License"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @foreach($licenseRows as $row)
                    <div class="report-row"><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                @endforeach
            </div>
        </section>

        <section class="report-panel" id="report-onboarding">
            <div class="report-panel-head">
                <h2><i class="bi bi-person-plus"></i> พนักงานใหม่</h2>
                <div>
                    <span>{{ number_format($onboardingRows->sum('count')) }}</span>
                    @if($sectionLinks['onboarding'])
                        <a class="icon-link" href="{{ $sectionLinks['onboarding'] }}" title="เปิดรายการพนักงานใหม่" aria-label="เปิดรายการพนักงานใหม่"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @foreach($onboardingRows as $row)
                    <div class="report-row"><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                @endforeach
            </div>
        </section>

        <section class="report-panel" id="report-offboarding">
            <div class="report-panel-head">
                <h2><i class="bi bi-person-dash"></i> พนักงานลาออก</h2>
                <div>
                    <span>{{ number_format($offboardingRows->sum('count')) }}</span>
                    @if($sectionLinks['offboarding'])
                        <a class="icon-link" href="{{ $sectionLinks['offboarding'] }}" title="เปิดรายการพนักงานลาออก" aria-label="เปิดรายการพนักงานลาออก"><i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
            </div>
            <div class="report-row-list">
                @foreach($offboardingRows as $row)
                    <div class="report-row"><span>{{ $row['name'] }}</span><strong>{{ number_format($row['count']) }}</strong></div>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection
